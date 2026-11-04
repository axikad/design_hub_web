<?php

namespace Tests\Feature\Admin;

use App\Models\Role;
use App\Models\SaleEarning;
use App\Models\SellerLedgerEntry;
use App\Models\SiteSetting;
use App\Models\User;
use App\Models\Withdrawal;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class SellerWalletManagementTest extends TestCase
{
    use RefreshDatabase;

    public function test_marketplace_user_can_manage_only_their_own_wallet_and_withdrawals(): void
    {
        $role = Role::query()->where('slug', 'marketplace_user')->firstOrFail();
        $seller = User::factory()->create(['role_id' => $role->id]);
        $otherSeller = User::factory()->create(['role_id' => $role->id]);
        $earning = $this->earning($seller, 'My Sale');
        $otherEarning = $this->earning($otherSeller, 'Other Sale');
        $this->ledger($seller, $earning, 75000);
        $this->ledger($otherSeller, $otherEarning, 99000);
        SiteSetting::current()->update([
            'seller_payouts_enabled' => true,
            'minimum_withdrawal_paise' => 50000,
        ]);
        SiteSetting::forgetCache();

        $this->actingAs($seller)
            ->get(route('admin.wallet.index'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('admin/wallet/index')
                ->where('wallet.balances.availablePaise', 75000)
                ->where('wallet.canWithdraw', true)
                ->has('earnings.data', 1)
                ->where('earnings.data.0.itemName', 'My Sale')
                ->has('transactions.data', 1)
                ->has('withdrawals.data', 0));

        $this->actingAs($seller)
            ->post(route('admin.wallet.withdrawals.store'), ['amountRupees' => '500'])
            ->assertRedirect()
            ->assertSessionHasNoErrors();

        $withdrawal = Withdrawal::query()->where('seller_id', $seller->id)->firstOrFail();
        $this->assertSame(50000, $withdrawal->amount_paise);
        $this->assertSame(25000, $this->balance($seller, 'available'));
        $this->assertSame(50000, $this->balance($seller, 'reserved'));

        $this->actingAs($otherSeller)
            ->post(route('admin.wallet.withdrawals.cancel', $withdrawal->public_id))
            ->assertNotFound();

        $this->actingAs($seller)
            ->post(route('admin.wallet.withdrawals.cancel', $withdrawal->public_id))
            ->assertRedirect()
            ->assertSessionHasNoErrors();

        $this->assertSame('cancelled', $withdrawal->fresh()->status);
        $this->assertSame(75000, $this->balance($seller, 'available'));
        $this->assertSame(0, $this->balance($seller, 'reserved'));
    }

    private function earning(User $seller, string $name): SaleEarning
    {
        return SaleEarning::query()->create([
            'seller_id' => $seller->id,
            'buyer_id' => User::factory()->create()->id,
            'item_type' => 'design',
            'item_id' => fake()->unique()->numberBetween(1, 100000),
            'item_public_id' => 'design_'.fake()->unique()->numerify('########'),
            'item_name' => $name,
            'currency' => 'INR',
            'payment_environment' => 'test',
            'taxable_amount_paise' => 100000,
            'buyer_gst_paise' => 0,
            'buyer_paid_paise' => 100000,
            'commission_basis_points' => 2500,
            'commission_paise' => 25000,
            'seller_gross_paise' => 75000,
            'commission_gst_paise' => 0,
            'tds_paise' => 0,
            'gst_tcs_paise' => 0,
            'provider_fee_paise' => 0,
            'refund_paise' => 0,
            'seller_net_paise' => 75000,
            'status' => 'available',
            'available_at' => now(),
            'calculation_snapshot' => [],
        ]);
    }

    private function ledger(User $seller, SaleEarning $earning, int $amount): void
    {
        SellerLedgerEntry::query()->create([
            'seller_id' => $seller->id,
            'sale_earning_id' => $earning->id,
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
