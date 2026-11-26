<?php

namespace Tests\Feature\Api;

use App\Models\Catalog;
use App\Models\CatalogPurchase;
use App\Models\User;
use App\Services\RazorpayCatalogPaymentService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Laravel\Sanctum\Sanctum;
use Mockery;
use Tests\TestCase;

class CatalogPaymentApiTest extends TestCase
{
    use RefreshDatabase;

    public function test_catalog_order_uses_catalog_price_and_backend_key(): void
    {
        $buyer = User::factory()->create();
        $catalog = $this->catalog(29900);
        Sanctum::actingAs($buyer);

        $service = Mockery::mock(RazorpayCatalogPaymentService::class);
        $service->shouldReceive('isConfigured')->once()->andReturnTrue();
        $service->shouldReceive('createOrder')->once()->with($buyer, Mockery::on(
            fn (Catalog $argument) => $argument->is($catalog)
        ), null)->andReturn([
            'catalogId' => $catalog->public_id,
            'orderId' => 'order_catalog_test',
            'keyId' => 'rzp_test_public_key',
            'amount' => 29900,
            'currency' => 'INR',
        ]);
        $this->app->instance(RazorpayCatalogPaymentService::class, $service);

        $response = $this->postJson('/api/v1/payments/orders', ['catalogId' => $catalog->public_id])
            ->assertOk()
            ->assertJsonPath('data.catalogId', $catalog->public_id)
            ->assertJsonPath('data.orderId', 'order_catalog_test')
            ->assertJsonPath('data.amount', 29900)
            ->assertJsonPath('data.currency', 'INR')
            ->assertJsonPath('data.checkoutBreakdown.item.type', 'catalog')
            ->assertJsonPath('data.checkoutBreakdown.item.id', $catalog->public_id)
            ->assertJsonPath('data.checkoutBreakdown.itemPricePaise', 29900)
            ->assertJsonPath('data.checkoutBreakdown.taxes.enabled', false)
            ->assertJsonPath('data.checkoutBreakdown.taxes.totalPaise', 0)
            ->assertJsonPath('data.checkoutBreakdown.buyerFee.amountPaise', 0)
            ->assertJsonPath('data.checkoutBreakdown.totalPayablePaise', 29900);

        $this->assertIsInt($response->json('data.amount'));
        $this->assertSame(
            $response->json('data.amount'),
            $response->json('data.checkoutBreakdown.totalPayablePaise'),
        );
    }

    public function test_catalog_status_supports_unpaid_pending_and_paid_payloads(): void
    {
        $buyer = User::factory()->create();
        $catalog = $this->catalog();
        Sanctum::actingAs($buyer);

        $service = Mockery::mock(RazorpayCatalogPaymentService::class);
        $service->shouldReceive('isConfigured')->once()->andReturnTrue();
        $service->shouldReceive('purchaseStatus')->once()->with($buyer, Mockery::type(Catalog::class))->andReturn([
            'catalogId' => $catalog->public_id,
            'status' => 'pending',
        ]);
        $this->app->instance(RazorpayCatalogPaymentService::class, $service);

        $this->getJson('/api/v1/purchases/catalogs/'.$catalog->public_id)
            ->assertOk()
            ->assertJsonPath('data.catalogId', $catalog->public_id)
            ->assertJsonPath('data.status', 'pending');
    }

    public function test_catalog_payment_verification_uses_catalog_identifier(): void
    {
        $buyer = User::factory()->create();
        $catalog = $this->catalog();
        Sanctum::actingAs($buyer);

        $service = Mockery::mock(RazorpayCatalogPaymentService::class);
        $service->shouldReceive('isConfigured')->once()->andReturnTrue();
        $service->shouldReceive('verify')->once()->with(
            $buyer,
            Mockery::on(fn (Catalog $argument) => $argument->is($catalog)),
            'order_catalog_test',
            'pay_catalog_test',
            'valid_signature',
        )->andReturn([
            'catalogId' => $catalog->public_id,
            'status' => 'paid',
            'purchaseId' => 'catalog_purchase_test',
        ]);
        $this->app->instance(RazorpayCatalogPaymentService::class, $service);

        $this->postJson('/api/v1/payments/verify', [
            'catalogId' => $catalog->public_id,
            'razorpay_order_id' => 'order_catalog_test',
            'razorpay_payment_id' => 'pay_catalog_test',
            'razorpay_signature' => 'valid_signature',
        ])->assertOk()
            ->assertJsonPath('data.status', 'paid')
            ->assertJsonPath('data.purchaseId', 'catalog_purchase_test');
    }

    public function test_only_paid_buyer_can_create_and_use_catalog_zip_download_link(): void
    {
        Storage::fake('local');
        $buyer = User::factory()->create();
        $catalog = $this->catalog();
        Storage::disk('local')->put($catalog->zip_path, 'zip-content');
        Sanctum::actingAs($buyer);

        $this->postJson('/api/v1/catalogs/'.$catalog->public_id.'/download')
            ->assertForbidden()
            ->assertJsonPath('code', 'catalog_download_forbidden');

        CatalogPurchase::query()->create([
            'buyer_id' => $buyer->id,
            'catalog_id' => $catalog->id,
            'amount_paise' => $catalog->price_paise,
            'currency' => 'INR',
            'status' => 'paid',
            'purchased_at' => now(),
        ]);

        $response = $this->postJson('/api/v1/catalogs/'.$catalog->public_id.'/download')
            ->assertOk()
            ->assertJsonPath('data.catalogId', $catalog->public_id)
            ->assertJsonStructure(['data' => ['downloadUri', 'expiresAt']]);

        $this->get($response->json('data.downloadUri'))
            ->assertOk()
            ->assertDownload($catalog->zip_name);
    }

    public function test_payment_request_requires_exactly_one_item_identifier(): void
    {
        Sanctum::actingAs(User::factory()->create());

        $this->postJson('/api/v1/payments/orders', [])
            ->assertUnprocessable()
            ->assertJsonPath('code', 'validation_error');

        $this->postJson('/api/v1/payments/orders', [
            'designId' => 'design_one',
            'catalogId' => 'catalog_one',
        ])->assertUnprocessable()
            ->assertJsonPath('code', 'validation_error');
    }

    public function test_catalog_owner_cannot_purchase_their_own_catalog(): void
    {
        config([
            'designhub.razorpay_key_id' => 'rzp_test_key',
            'designhub.razorpay_key_secret' => 'test_secret',
        ]);

        $owner = User::factory()->create();
        $catalog = $this->catalog();
        $catalog->update(['user_id' => $owner->id]);
        Sanctum::actingAs($owner);

        $this->postJson('/api/v1/payments/orders', ['catalogId' => $catalog->public_id])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('catalogId');
    }

    private function catalog(int $pricePaise = 29900): Catalog
    {
        return Catalog::query()->create([
            'name' => 'Bridal Pack '.uniqid(),
            'price_paise' => $pricePaise,
            'zip_name' => 'bridal.zip',
            'zip_path' => 'catalogs/bridal/archive/source.zip',
            'file_count' => 2,
            'total_size' => 120,
        ]);
    }
}
