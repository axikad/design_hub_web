<?php

namespace Tests\Feature\Admin;

use App\Models\Permission;
use App\Models\Role;
use App\Models\SellerLedgerEntry;
use App\Models\SiteSetting;
use App\Models\User;
use App\Models\Withdrawal;
use App\Services\SellerWithdrawalService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class WithdrawalManagementTest extends TestCase
{
    use RefreshDatabase;

    public function test_finance_admin_can_view_withdrawal_queue_and_detail(): void
    {
        $withdrawal = $this->requestedWithdrawal();
        $admin = $this->adminWith(['withdrawals.view_any']);

        $this->actingAs($admin)
            ->get(route('admin.withdrawals.index'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('admin/withdrawals/index')
                ->has('withdrawals.data', 1)
                ->where('withdrawals.data.0.id', $withdrawal->public_id)
                ->where('withdrawals.data.0.status', 'requested'));

        $this->actingAs($admin)
            ->get(route('admin.withdrawals.show', $withdrawal->public_id))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('admin/withdrawals/show')
                ->where('withdrawal.id', $withdrawal->public_id)
                ->where('withdrawal.actions.canApprove', true)
                ->has('withdrawal.history', 1)
                ->has('withdrawal.ledgerEntries', 2));
    }

    public function test_admin_without_view_permission_cannot_access_withdrawals(): void
    {
        $withdrawal = $this->requestedWithdrawal();
        $admin = $this->adminWith([]);

        $this->actingAs($admin)->get(route('admin.withdrawals.index'))->assertForbidden();
        $this->actingAs($admin)->get(route('admin.withdrawals.show', $withdrawal->public_id))->assertForbidden();
    }

    public function test_read_only_finance_admin_cannot_manage_withdrawals(): void
    {
        $withdrawal = $this->requestedWithdrawal();
        $admin = $this->adminWith(['withdrawals.view_any']);

        $this->actingAs($admin)
            ->post(route('admin.withdrawals.approve', $withdrawal->public_id), ['note' => 'Checked request.'])
            ->assertForbidden();
        $this->assertSame('requested', $withdrawal->fresh()->status);
    }

    public function test_approve_keeps_funds_reserved_and_records_admin_audit_history(): void
    {
        $withdrawal = $this->requestedWithdrawal();
        $admin = $this->adminWith(['withdrawals.view_any', 'withdrawals.manage']);

        $this->actingAs($admin)
            ->post(route('admin.withdrawals.approve', $withdrawal->public_id), [
                'note' => 'Seller details and balance verified.',
            ])
            ->assertRedirect()
            ->assertSessionHas('success');

        $withdrawal->refresh();
        $this->assertSame('approved', $withdrawal->status);
        $this->assertSame($admin->id, $withdrawal->approved_by);
        $this->assertNotNull($withdrawal->approved_at);
        $this->assertSame(50000, $this->balance($withdrawal->seller, 'reserved'));
        $this->assertSame(0, $this->balance($withdrawal->seller, 'paid'));
        $this->assertDatabaseHas('withdrawal_status_histories', [
            'withdrawal_id' => $withdrawal->id,
            'from_status' => 'requested',
            'to_status' => 'approved',
            'actor_id' => $admin->id,
            'note' => 'Seller details and balance verified.',
        ]);
    }

    public function test_reject_requires_reason_and_returns_reserved_funds_to_available(): void
    {
        $withdrawal = $this->requestedWithdrawal();
        $admin = $this->adminWith(['withdrawals.view_any', 'withdrawals.manage']);

        $this->actingAs($admin)
            ->post(route('admin.withdrawals.reject', $withdrawal->public_id), ['note' => ''])
            ->assertSessionHasErrors('note');

        $this->actingAs($admin)
            ->post(route('admin.withdrawals.reject', $withdrawal->public_id), [
                'note' => 'Bank details need correction.',
            ])
            ->assertRedirect()
            ->assertSessionHas('success');

        $withdrawal->refresh();
        $this->assertSame('rejected', $withdrawal->status);
        $this->assertSame('Bank details need correction.', $withdrawal->rejection_reason);
        $this->assertSame(75000, $this->balance($withdrawal->seller, 'available'));
        $this->assertSame(0, $this->balance($withdrawal->seller, 'reserved'));
        $this->assertDatabaseHas('withdrawal_status_histories', [
            'withdrawal_id' => $withdrawal->id,
            'to_status' => 'rejected',
            'actor_id' => $admin->id,
        ]);
    }

    public function test_approved_test_withdrawal_can_complete_simulated_payout(): void
    {
        $withdrawal = $this->requestedWithdrawal();
        $admin = $this->adminWith(['withdrawals.view_any', 'withdrawals.manage']);

        $this->actingAs($admin)->post(route('admin.withdrawals.approve', $withdrawal->public_id), [
            'note' => 'Approved for test payout.',
        ])->assertRedirect();

        $this->actingAs($admin)->post(route('admin.withdrawals.complete-simulated', $withdrawal->public_id), [
            'note' => 'Simulated transfer completed.',
        ])->assertRedirect()->assertSessionHas('success');

        $withdrawal->refresh();
        $this->assertSame('paid', $withdrawal->status);
        $this->assertSame('internal_simulator', $withdrawal->provider);
        $this->assertSame('simulated_paid', $withdrawal->provider_status);
        $this->assertNotNull($withdrawal->processed_at);
        $this->assertSame(0, $this->balance($withdrawal->seller, 'reserved'));
        $this->assertSame(50000, $this->balance($withdrawal->seller, 'paid'));
        $this->assertDatabaseHas('withdrawal_status_histories', [
            'withdrawal_id' => $withdrawal->id,
            'from_status' => 'approved',
            'to_status' => 'paid',
            'actor_id' => $admin->id,
        ]);
    }

    public function test_live_withdrawal_cannot_use_simulated_completion(): void
    {
        $withdrawal = $this->requestedWithdrawal();
        $admin = $this->adminWith(['withdrawals.view_any', 'withdrawals.manage']);
        $withdrawal->update([
            'status' => 'approved',
            'payment_environment' => 'live',
            'payout_mode' => 'manual',
        ]);

        $this->actingAs($admin)
            ->post(route('admin.withdrawals.complete-simulated', $withdrawal->public_id), [
                'note' => 'Should not complete.',
            ])
            ->assertSessionHasErrors('withdrawal');

        $this->assertSame('approved', $withdrawal->fresh()->status);
    }

    private function requestedWithdrawal(): Withdrawal
    {
        SiteSetting::current()->update([
            'seller_payouts_enabled' => true,
            'minimum_withdrawal_paise' => 50000,
        ]);
        SiteSetting::forgetCache();

        $seller = User::factory()->create();
        SellerLedgerEntry::query()->create([
            'seller_id' => $seller->id,
            'entry_type' => 'test_available_credit',
            'balance_bucket' => 'available',
            'amount_paise' => 75000,
            'currency' => 'INR',
            'payment_environment' => 'test',
            'idempotency_key' => fake()->unique()->uuid(),
        ]);

        return app(SellerWithdrawalService::class)
            ->create($seller, 50000, fake()->unique()->uuid())['withdrawal'];
    }

    /** @param list<string> $permissions */
    private function adminWith(array $permissions): User
    {
        $role = Role::query()->create([
            'name' => 'Finance '.fake()->unique()->word(),
            'slug' => 'finance_'.fake()->unique()->numerify('#####'),
        ]);
        $role->permissions()->attach(Permission::query()
            ->whereIn('slug', ['dashboard.read', ...$permissions])
            ->pluck('id'));

        return User::factory()->create(['role_id' => $role->id]);
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
