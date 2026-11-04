<?php

namespace Tests\Feature\Api;

use App\Models\Permission;
use App\Models\Role;
use App\Models\SellerLedgerEntry;
use App\Models\SiteSetting;
use App\Models\User;
use App\Models\Withdrawal;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class SellerWithdrawalApiTest extends TestCase
{
    use RefreshDatabase;

    public function test_seller_can_request_withdrawal_and_available_money_is_reserved_atomically(): void
    {
        $seller = $this->seller();
        $this->enableWithdrawals();
        $this->creditAvailable($seller, 75000);
        Sanctum::actingAs($seller);

        $response = $this->postJson('/api/v1/seller/withdrawals', [
            'amountPaise' => 50000,
            'idempotencyKey' => 'mobile-request-0001',
        ]);

        $response->assertCreated()
            ->assertJsonPath('data.amountPaise', 50000)
            ->assertJsonPath('data.status', 'requested')
            ->assertJsonPath('data.paymentEnvironment', 'test')
            ->assertJsonPath('data.isTestMode', true)
            ->assertJsonPath('data.payoutMode', 'simulated')
            ->assertJsonPath('data.canCancel', true);

        $withdrawal = Withdrawal::query()->firstOrFail();
        $this->assertDatabaseHas('withdrawal_status_histories', [
            'withdrawal_id' => $withdrawal->id,
            'from_status' => null,
            'to_status' => 'requested',
            'actor_id' => $seller->id,
        ]);
        $this->assertSame(25000, $this->balance($seller, 'available'));
        $this->assertSame(50000, $this->balance($seller, 'reserved'));

        $this->getJson('/api/v1/seller/wallet')
            ->assertOk()
            ->assertJsonPath('data.hasOpenWithdrawal', true)
            ->assertJsonPath('data.canWithdraw', false)
            ->assertJsonPath('data.balances.availablePaise', 25000)
            ->assertJsonPath('data.balances.reservedPaise', 50000);
    }

    public function test_idempotent_retry_returns_same_withdrawal_without_reserving_twice(): void
    {
        $seller = $this->seller();
        $this->enableWithdrawals();
        $this->creditAvailable($seller, 100000);
        Sanctum::actingAs($seller);
        $payload = ['amountPaise' => 50000, 'idempotencyKey' => 'retry-safe-0001'];

        $firstId = $this->postJson('/api/v1/seller/withdrawals', $payload)
            ->assertCreated()
            ->json('data.id');

        $this->postJson('/api/v1/seller/withdrawals', $payload)
            ->assertOk()
            ->assertJsonPath('data.id', $firstId);

        $this->assertDatabaseCount('withdrawals', 1);
        $this->assertSame(2, SellerLedgerEntry::query()->whereNotNull('withdrawal_id')->count());
        $this->assertSame(50000, $this->balance($seller, 'available'));
        $this->assertSame(50000, $this->balance($seller, 'reserved'));
    }

    public function test_second_open_request_and_insufficient_or_below_minimum_amount_are_rejected(): void
    {
        $seller = $this->seller();
        $this->enableWithdrawals();
        $this->creditAvailable($seller, 150000);
        Sanctum::actingAs($seller);

        $this->postJson('/api/v1/seller/withdrawals', [
            'amountPaise' => 50000,
            'idempotencyKey' => 'first-open-request',
        ])->assertCreated();

        $this->postJson('/api/v1/seller/withdrawals', [
            'amountPaise' => 50000,
            'idempotencyKey' => 'second-open-request',
        ])->assertUnprocessable()
            ->assertJsonPath('code', 'validation_error');

        $other = $this->seller();
        $this->creditAvailable($other, 40000);
        Sanctum::actingAs($other);

        $this->postJson('/api/v1/seller/withdrawals', [
            'amountPaise' => 40000,
            'idempotencyKey' => 'below-minimum-001',
        ])->assertUnprocessable()
            ->assertJsonValidationErrors('amountPaise');

        $this->creditAvailable($other, 20000);
        $this->postJson('/api/v1/seller/withdrawals', [
            'amountPaise' => 70000,
            'idempotencyKey' => 'insufficient-0001',
        ])->assertUnprocessable()
            ->assertJsonValidationErrors('amountPaise');
    }

    public function test_seller_can_cancel_requested_withdrawal_and_money_returns_to_available(): void
    {
        $seller = $this->seller();
        $this->enableWithdrawals();
        $this->creditAvailable($seller, 75000);
        Sanctum::actingAs($seller);

        $withdrawalId = $this->postJson('/api/v1/seller/withdrawals', [
            'amountPaise' => 50000,
            'idempotencyKey' => 'cancel-request-01',
        ])->assertCreated()->json('data.id');

        $this->postJson("/api/v1/seller/withdrawals/{$withdrawalId}/cancel")
            ->assertOk()
            ->assertJsonPath('data.status', 'cancelled')
            ->assertJsonPath('data.canCancel', false);
        $this->postJson("/api/v1/seller/withdrawals/{$withdrawalId}/cancel")
            ->assertOk();

        $this->assertSame(75000, $this->balance($seller, 'available'));
        $this->assertSame(0, $this->balance($seller, 'reserved'));
        $this->assertSame(4, SellerLedgerEntry::query()->whereNotNull('withdrawal_id')->count());
    }

    public function test_withdrawal_history_and_detail_are_scoped_to_authenticated_seller(): void
    {
        $seller = $this->seller();
        $other = $this->seller();
        $this->enableWithdrawals();
        $this->creditAvailable($seller, 75000);
        Sanctum::actingAs($seller);

        $withdrawalId = $this->postJson('/api/v1/seller/withdrawals', [
            'amountPaise' => 50000,
            'idempotencyKey' => 'owner-scope-0001',
        ])->assertCreated()->json('data.id');

        $this->getJson('/api/v1/seller/withdrawals')
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.id', $withdrawalId);
        $this->getJson("/api/v1/seller/withdrawals/{$withdrawalId}")
            ->assertOk()
            ->assertJsonCount(1, 'data.history');

        Sanctum::actingAs($other);
        $this->getJson("/api/v1/seller/withdrawals/{$withdrawalId}")->assertNotFound();
        $this->postJson("/api/v1/seller/withdrawals/{$withdrawalId}/cancel")->assertNotFound();
    }

    public function test_disabled_payouts_and_missing_permissions_are_rejected(): void
    {
        $seller = $this->seller();
        $this->creditAvailable($seller, 75000);
        Sanctum::actingAs($seller);

        $this->postJson('/api/v1/seller/withdrawals', [
            'amountPaise' => 50000,
            'idempotencyKey' => 'disabled-payout-01',
        ])->assertUnprocessable();

        $role = Role::query()->create(['name' => 'Buyer Only', 'slug' => 'buyer_only_withdrawal']);
        $buyer = User::factory()->create(['role_id' => $role->id]);
        Sanctum::actingAs($buyer);
        $this->getJson('/api/v1/seller/withdrawals')->assertForbidden();
    }

    private function seller(): User
    {
        $role = Role::query()->firstOrCreate(
            ['slug' => 'marketplace_user'],
            ['name' => 'Marketplace User'],
        );
        $role->permissions()->syncWithoutDetaching(Permission::query()
            ->whereIn('slug', [
                'wallet.view_own',
                'withdrawals.view_own',
                'withdrawals.create_own',
                'withdrawals.cancel_own',
            ])
            ->pluck('id'));

        return User::factory()->create(['role_id' => $role->id]);
    }

    private function enableWithdrawals(): void
    {
        SiteSetting::current()->update([
            'seller_payouts_enabled' => true,
            'minimum_withdrawal_paise' => 50000,
        ]);
        SiteSetting::forgetCache();
    }

    private function creditAvailable(User $seller, int $amount): void
    {
        SellerLedgerEntry::query()->create([
            'seller_id' => $seller->id,
            'entry_type' => 'test_available_credit',
            'balance_bucket' => 'available',
            'amount_paise' => $amount,
            'currency' => 'INR',
            'payment_environment' => 'test',
            'idempotency_key' => fake()->unique()->uuid(),
        ]);
    }

    private function balance(User $seller, string $bucket): int
    {
        return (int) SellerLedgerEntry::query()
            ->where('seller_id', $seller->id)
            ->where('payment_environment', 'test')
            ->where('balance_bucket', $bucket)
            ->sum('amount_paise');
    }
}
