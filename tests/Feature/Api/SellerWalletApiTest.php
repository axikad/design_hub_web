<?php

namespace Tests\Feature\Api;

use App\Models\Permission;
use App\Models\Role;
use App\Models\SaleEarning;
use App\Models\SellerLedgerEntry;
use App\Models\SiteSetting;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class SellerWalletApiTest extends TestCase
{
    use RefreshDatabase;

    public function test_seller_wallet_returns_only_authenticated_seller_current_environment_balances(): void
    {
        $seller = $this->marketplaceUser();
        $otherSeller = $this->marketplaceUser();
        $earning = $this->createEarning($seller);
        $otherEarning = $this->createEarning($otherSeller);
        $this->createLedger($earning, 'pending', 7500, 'test');
        $this->createLedger($earning, 'available', 60000, 'test');
        $this->createLedger($earning, 'available', 99999, 'live');
        $this->createLedger($otherEarning, 'available', 88888, 'test');
        SiteSetting::current()->update([
            'seller_payouts_enabled' => true,
            'minimum_withdrawal_paise' => 50000,
        ]);
        SiteSetting::forgetCache();
        Sanctum::actingAs($seller);

        $this->getJson('/api/v1/seller/wallet')
            ->assertOk()
            ->assertJsonPath('data.paymentEnvironment', 'test')
            ->assertJsonPath('data.isTestMode', true)
            ->assertJsonPath('data.balances.pendingPaise', 7500)
            ->assertJsonPath('data.balances.availablePaise', 60000)
            ->assertJsonPath('data.minimumWithdrawalPaise', 50000)
            ->assertJsonPath('data.canWithdraw', true);
    }

    public function test_earnings_and_transactions_are_paginated_and_owner_scoped(): void
    {
        $seller = $this->marketplaceUser();
        $otherSeller = $this->marketplaceUser();
        $earning = $this->createEarning($seller);
        $otherEarning = $this->createEarning($otherSeller);
        $entry = $this->createLedger($earning, 'pending', 7500, 'test');
        $this->createLedger($otherEarning, 'pending', 7500, 'test');
        Sanctum::actingAs($seller);

        $this->getJson('/api/v1/seller/earnings?per_page=20')
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.id', $earning->public_id)
            ->assertJsonPath('data.0.sellerNetPaise', 7500)
            ->assertJsonPath('meta.total', 1);

        $this->getJson('/api/v1/seller/transactions?per_page=20')
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.id', $entry->public_id)
            ->assertJsonPath('data.0.earningId', $earning->public_id)
            ->assertJsonPath('meta.total', 1);
    }

    public function test_user_without_own_finance_permissions_is_forbidden(): void
    {
        $role = Role::query()->create(['name' => 'Buyer Only', 'slug' => 'buyer_only']);
        $user = User::factory()->create(['role_id' => $role->id]);
        Sanctum::actingAs($user);

        $this->getJson('/api/v1/seller/wallet')
            ->assertForbidden()
            ->assertJsonPath('code', 'forbidden');
    }

    private function marketplaceUser(): User
    {
        $role = Role::query()->firstOrCreate(
            ['slug' => 'marketplace_user'],
            ['name' => 'Marketplace User'],
        );

        $role->permissions()->syncWithoutDetaching(Permission::query()
            ->whereIn('slug', ['wallet.view_own', 'earnings.view_own', 'ledger.view_own'])
            ->pluck('id'));

        return User::factory()->create(['role_id' => $role->id]);
    }

    private function createEarning(User $seller): SaleEarning
    {
        return SaleEarning::query()->create([
            'seller_id' => $seller->id,
            'buyer_id' => User::factory()->create()->id,
            'item_type' => 'design',
            'item_id' => fake()->unique()->numberBetween(1, 100000),
            'item_public_id' => 'design_'.fake()->unique()->numerify('########'),
            'item_name' => 'Wallet Test Design',
            'currency' => 'INR',
            'payment_environment' => 'test',
            'taxable_amount_paise' => 10000,
            'buyer_gst_paise' => 0,
            'buyer_paid_paise' => 10000,
            'commission_basis_points' => 2500,
            'commission_paise' => 2500,
            'seller_gross_paise' => 7500,
            'commission_gst_paise' => 0,
            'tds_paise' => 0,
            'gst_tcs_paise' => 0,
            'provider_fee_paise' => 0,
            'refund_paise' => 0,
            'seller_net_paise' => 7500,
            'status' => 'pending',
            'available_at' => now()->addDays(7),
            'calculation_snapshot' => [],
        ]);
    }

    private function createLedger(
        SaleEarning $earning,
        string $bucket,
        int $amount,
        string $environment,
    ): SellerLedgerEntry {
        return SellerLedgerEntry::query()->create([
            'seller_id' => $earning->seller_id,
            'sale_earning_id' => $earning->id,
            'entry_type' => 'test_'.$bucket,
            'balance_bucket' => $bucket,
            'amount_paise' => $amount,
            'currency' => 'INR',
            'payment_environment' => $environment,
            'idempotency_key' => fake()->unique()->uuid(),
        ]);
    }
}
