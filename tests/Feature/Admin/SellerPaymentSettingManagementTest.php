<?php

namespace Tests\Feature\Admin;

use App\Models\Permission;
use App\Models\Role;
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
