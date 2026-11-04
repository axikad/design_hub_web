<?php

namespace Tests\Feature\Api;

use App\Models\SiteSetting;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class SellerPricingPreviewApiTest extends TestCase
{
    use RefreshDatabase;

    public function test_authenticated_seller_can_preview_commission_without_tax_rows(): void
    {
        SiteSetting::current()->update([
            'platform_commission_basis_points' => 2500,
            'buyer_gst_enabled' => false,
            'commission_gst_enabled' => false,
            'tds_enabled' => false,
            'gst_tcs_enabled' => false,
        ]);
        SiteSetting::forgetCache();
        Sanctum::actingAs(User::factory()->create());

        $this->postJson('/api/v1/seller/pricing-preview', ['pricePaise' => 10000])
            ->assertOk()
            ->assertJsonPath('data.itemPricePaise', 10000)
            ->assertJsonPath('data.buyerPayablePaise', 10000)
            ->assertJsonPath('data.platformCommission.basisPoints', 2500)
            ->assertJsonPath('data.platformCommission.percent', 25)
            ->assertJsonPath('data.platformCommission.amountPaise', 2500)
            ->assertJsonPath('data.estimatedSellerEarningPaise', 7500)
            ->assertJsonPath('data.taxes.buyerGst.enabled', false)
            ->assertJsonPath('data.taxes.commissionGst.enabled', false)
            ->assertJsonPath('data.taxes.tds.enabled', false)
            ->assertJsonPath('data.taxes.gstTcs.enabled', false);
    }

    public function test_preview_uses_current_commission_and_validates_integer_paise(): void
    {
        SiteSetting::current()->update(['platform_commission_basis_points' => 2000]);
        SiteSetting::forgetCache();
        Sanctum::actingAs(User::factory()->create());

        $this->postJson('/api/v1/seller/pricing-preview', ['pricePaise' => 9999])
            ->assertOk()
            ->assertJsonPath('data.platformCommission.amountPaise', 2000)
            ->assertJsonPath('data.estimatedSellerEarningPaise', 7999);

        $this->postJson('/api/v1/seller/pricing-preview', ['pricePaise' => '99.99'])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('pricePaise');
    }

    public function test_pricing_preview_requires_authentication(): void
    {
        $this->postJson('/api/v1/seller/pricing-preview', ['pricePaise' => 10000])
            ->assertUnauthorized();
    }

    public function test_admin_web_session_can_request_a_pricing_preview(): void
    {
        $this->actingAs(User::factory()->create())
            ->getJson('/api/v1/seller/pricing-preview?pricePaise=10000')
            ->assertOk()
            ->assertJsonPath('data.itemPricePaise', 10000);
    }
}
