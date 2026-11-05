<?php

namespace Tests\Feature\Admin;

use App\Models\Permission;
use App\Models\Role;
use App\Models\SaleEarning;
use App\Models\SellerLedgerEntry;
use App\Models\SiteSetting;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class SellerPaymentSettingManagementTest extends TestCase
{
    use RefreshDatabase;

    public function test_super_admin_can_view_seller_payment_settings(): void
    {
        $admin = User::factory()->superAdmin()->create();

        $this->actingAs($admin)
            ->get(route('admin.settings.seller-payments.edit'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('admin/settings/seller-payments')
                ->where('paymentEnvironment', 'test')
                ->where('settings.platformCommissionPercent', '25.00')
                ->where('settings.earningHoldDays', 7)
                ->where('settings.minimumWithdrawalRupees', '500.00')
                ->where('settings.sellerPayoutsEnabled', false));
    }

    public function test_super_admin_can_update_seller_payment_settings_in_integer_units(): void
    {
        $admin = User::factory()->superAdmin()->create();

        $this->actingAs($admin)
            ->put(route('admin.settings.seller-payments.update'), $this->validPayload([
                'sellerPayoutsEnabled' => true,
                'platformCommissionPercent' => '20.50',
                'earningHoldDays' => 10,
                'minimumWithdrawalRupees' => '750.25',
            ]))
            ->assertRedirect(route('admin.settings.seller-payments.edit'))
            ->assertSessionHas('success');

        $settings = SiteSetting::query()->firstOrFail()->fresh();

        $this->assertTrue($settings->seller_payouts_enabled);
        $this->assertSame(2050, $settings->platform_commission_basis_points);
        $this->assertSame(10, $settings->earning_hold_days);
        $this->assertSame(75025, $settings->minimum_withdrawal_paise);
        $this->assertSame(0, $settings->buyer_gst_basis_points);
    }

    public function test_read_only_finance_admin_cannot_update_settings(): void
    {
        $role = Role::query()->create(['name' => 'Finance Viewer', 'slug' => 'finance_viewer']);
        $role->permissions()->attach(Permission::query()
            ->whereIn('slug', ['dashboard.read', 'seller_payments.read'])
            ->pluck('id'));
        $user = User::factory()->create(['role_id' => $role->id]);

        $this->actingAs($user)
            ->get(route('admin.settings.seller-payments.edit'))
            ->assertOk();

        $this->actingAs($user)
            ->put(route('admin.settings.seller-payments.update'), $this->validPayload())
            ->assertForbidden();
    }

    public function test_admin_without_finance_permission_cannot_view_settings(): void
    {
        $role = Role::query()->create(['name' => 'Catalog Admin', 'slug' => 'catalog_admin']);
        $role->permissions()->attach(Permission::query()->where('slug', 'dashboard.read')->value('id'));
        $user = User::factory()->create(['role_id' => $role->id]);

        $this->actingAs($user)
            ->get(route('admin.settings.seller-payments.edit'))
            ->assertForbidden();
    }

    public function test_payment_setting_validation_rejects_invalid_financial_values(): void
    {
        $admin = User::factory()->superAdmin()->create();

        $this->actingAs($admin)
            ->from(route('admin.settings.seller-payments.edit'))
            ->put(route('admin.settings.seller-payments.update'), $this->validPayload([
                'platformCommissionPercent' => '101',
                'earningHoldDays' => -1,
                'minimumWithdrawalRupees' => '0',
            ]))
            ->assertRedirect(route('admin.settings.seller-payments.edit'))
            ->assertSessionHasErrors([
                'platformCommissionPercent',
                'earningHoldDays',
                'minimumWithdrawalRupees',
            ]);
    }

    public function test_settlement_cycles_must_cover_the_month_without_gaps(): void
    {
        $admin = User::factory()->superAdmin()->create();

        $this->actingAs($admin)->put(route('admin.settings.seller-payments.update'), $this->validPayload([
            'settlementCycles' => [
                ['startDay' => 1, 'endDay' => 4, 'settlementDay' => 6],
                ['startDay' => 6, 'endDay' => 31, 'settlementDay' => 3],
            ],
        ]))->assertSessionHasErrors('settlementCycles.1.startDay');

        $this->actingAs($admin)->put(route('admin.settings.seller-payments.update'), $this->validPayload([
            'settlementCycles' => [
                ['startDay' => 1, 'endDay' => 4, 'settlementDay' => 6],
                ['startDay' => 5, 'endDay' => 31, 'settlementDay' => 3],
            ],
        ]))->assertSessionHasNoErrors();

        $this->assertDatabaseHas('settlement_cycles', ['start_day' => 1, 'end_day' => 4, 'settlement_day' => 6]);
        $this->assertDatabaseHas('settlement_cycles', ['start_day' => 5, 'end_day' => 31, 'settlement_day' => 3]);
    }

    public function test_admin_can_review_the_cycle_and_scheduled_time_for_each_earning(): void
    {
        $admin = User::factory()->superAdmin()->create();
        $seller = User::factory()->create(['name' => 'Cycle Seller', 'seller_settlement_mode' => 'manual']);
        $earning = $this->createPendingEarning($seller);

        $this->actingAs($admin)
            ->get(route('admin.settlement-earnings.index'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('admin/settlement-earnings/index')
                ->where('marketplaceTimezone', 'Asia/Kolkata')
                ->where('earnings.data.0.id', $earning->public_id)
                ->where('earnings.data.0.seller.name', 'Cycle Seller')
                ->where('earnings.data.0.seller.settlementMode', 'manual')
                ->where('earnings.data.0.cycle.type', 'monthly_cycle')
                ->where('earnings.data.0.cycle.startDay', 5)
                ->where('earnings.data.0.cycle.endDay', 10)
                ->where('earnings.data.0.cycle.settlementDay', 12)
                ->where('earnings.data.0.canProcessNow', true));
    }

    public function test_admin_can_filter_settlement_earnings_by_applied_cycle(): void
    {
        $admin = User::factory()->superAdmin()->create();
        $seller = User::factory()->create();
        $matching = $this->createPendingEarning($seller);
        $legacy = $this->createPendingEarning($seller);
        $legacy->update(['calculation_snapshot' => ['settlement' => ['type' => 'legacy_hold', 'earning_hold_days' => 7]]]);

        $this->actingAs($admin)
            ->get(route('admin.settlement-earnings.index', ['cycle' => '5-10-12']))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->where('filters.cycle', '5-10-12')
                ->has('earnings.data', 1)
                ->where('earnings.data.0.id', $matching->public_id));

        $this->actingAs($admin)
            ->get(route('admin.settlement-earnings.index', ['cycle' => 'legacy']))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->where('filters.cycle', 'legacy')
                ->has('earnings.data', 1)
                ->where('earnings.data.0.id', $legacy->public_id));
    }

    public function test_super_admin_can_process_an_automatic_test_settlement_now(): void
    {
        $admin = User::factory()->superAdmin()->create();
        $seller = User::factory()->create(['seller_settlement_mode' => 'automatic']);
        $earning = $this->createPendingEarning($seller);
        SiteSetting::current()->update(['seller_payouts_enabled' => true]);
        SiteSetting::forgetCache();

        $this->actingAs($admin)
            ->from(route('admin.settings.seller-payments.edit'))
            ->post(route('admin.settings.seller-payments.earnings.process-now', $earning->public_id))
            ->assertRedirect(route('admin.settings.seller-payments.edit'))
            ->assertSessionHas('success');

        $this->assertDatabaseHas('sale_earnings', ['id' => $earning->id, 'status' => 'available']);
        $this->assertDatabaseHas('withdrawals', [
            'seller_id' => $seller->id,
            'amount_paise' => 7500,
            'request_source' => 'automatic',
            'payout_mode' => 'simulated',
            'status' => 'paid',
        ]);
        $this->assertSame(0, (int) SellerLedgerEntry::query()->where('seller_id', $seller->id)->where('balance_bucket', 'available')->sum('amount_paise'));
        $this->assertSame(7500, (int) SellerLedgerEntry::query()->where('seller_id', $seller->id)->where('balance_bucket', 'paid')->sum('amount_paise'));
    }

    public function test_process_now_is_never_available_in_live_mode(): void
    {
        config()->set('designhub.payment_environment', 'live');
        $admin = User::factory()->superAdmin()->create();
        $seller = User::factory()->create(['seller_settlement_mode' => 'automatic']);
        $earning = $this->createPendingEarning($seller, 'live');

        $this->actingAs($admin)
            ->post(route('admin.settings.seller-payments.earnings.process-now', $earning->public_id))
            ->assertForbidden();

        $this->assertDatabaseHas('sale_earnings', ['id' => $earning->id, 'status' => 'pending']);
        $this->assertDatabaseCount('withdrawals', 0);
    }

    private function createPendingEarning(User $seller, string $environment = 'test'): SaleEarning
    {
        $buyer = User::factory()->create();
        $earning = SaleEarning::query()->create([
            'seller_id' => $seller->id,
            'buyer_id' => $buyer->id,
            'item_type' => 'design',
            'item_id' => 1001,
            'item_public_id' => 'design_cycle_admin_test',
            'item_name' => 'Cycle Admin Test',
            'currency' => 'INR',
            'payment_environment' => $environment,
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
            'available_at' => now()->addWeek(),
            'calculation_snapshot' => [
                'settlement' => [
                    'type' => 'monthly_cycle',
                    'start_day' => 5,
                    'end_day' => 10,
                    'settlement_day' => 12,
                    'timezone' => 'Asia/Kolkata',
                ],
            ],
        ]);

        SellerLedgerEntry::query()->create([
            'seller_id' => $seller->id,
            'sale_earning_id' => $earning->id,
            'entry_type' => 'sale_earning_pending',
            'balance_bucket' => 'pending',
            'amount_paise' => 7500,
            'currency' => 'INR',
            'payment_environment' => $environment,
            'idempotency_key' => 'admin-cycle-test-'.$earning->public_id,
        ]);

        return $earning;
    }

    /** @param array<string, mixed> $overrides */
    private function validPayload(array $overrides = []): array
    {
        return [
            'sellerPayoutsEnabled' => false,
            'platformCommissionPercent' => '25.00',
            'earningHoldDays' => 7,
            'minimumWithdrawalRupees' => '500.00',
            'buyerGstEnabled' => false,
            'buyerGstPercent' => '0.00',
            'commissionGstEnabled' => false,
            'commissionGstPercent' => '0.00',
            'tdsEnabled' => false,
            'tdsPercent' => '0.00',
            'gstTcsEnabled' => false,
            'gstTcsPercent' => '0.00',
            ...$overrides,
        ];
    }
}
