<?php

namespace Tests\Feature\Api;

use App\Models\Design;
use App\Models\User;
use App\Services\RazorpayPaymentService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Mockery;
use Tests\TestCase;

class RazorpayPaymentApiTest extends TestCase
{
    use RefreshDatabase;

    public function test_order_endpoint_returns_payments_unavailable_only_when_backend_keys_are_missing(): void
    {
        config([
            'designhub.razorpay_key_id' => null,
            'designhub.razorpay_key_secret' => null,
        ]);

        $buyer = User::factory()->create();
        $design = $this->designOwnedBy(User::factory()->create());
        Sanctum::actingAs($buyer);

        $this->postJson('/api/v1/payments/orders', ['designId' => $design->public_id])
            ->assertStatus(503)
            ->assertJson([
                'success' => false,
                'message' => 'Payments are not configured yet.',
                'code' => 'payments_unavailable',
            ]);
    }

    public function test_order_endpoint_returns_backend_key_and_integer_amount_in_standard_envelope(): void
    {
        $buyer = User::factory()->create();
        $design = $this->designOwnedBy(User::factory()->create(), 29900);
        Sanctum::actingAs($buyer);

        $service = Mockery::mock(RazorpayPaymentService::class);
        $service->shouldReceive('isConfigured')->once()->andReturnTrue();
        $service->shouldReceive('createOrder')->once()->with($buyer, Mockery::on(
            fn (Design $argument) => $argument->is($design)
        ))->andReturn([
            'designId' => $design->public_id,
            'orderId' => 'order_test123',
            'keyId' => 'rzp_test_public_key',
            'amount' => 29900,
            'currency' => 'INR',
        ]);
        $this->app->instance(RazorpayPaymentService::class, $service);

        $response = $this->postJson('/api/v1/payments/orders', ['designId' => $design->public_id])
            ->assertOk()
            ->assertExactJson([
                'success' => true,
                'message' => 'Payment order created successfully.',
                'data' => [
                    'designId' => $design->public_id,
                    'orderId' => 'order_test123',
                    'keyId' => 'rzp_test_public_key',
                    'amount' => 29900,
                    'currency' => 'INR',
                ],
            ]);

        $this->assertIsInt($response->json('data.amount'));
    }

    public function test_status_endpoint_returns_reconciled_payment_state(): void
    {
        $buyer = User::factory()->create();
        $design = $this->designOwnedBy(User::factory()->create());
        Sanctum::actingAs($buyer);

        $service = Mockery::mock(RazorpayPaymentService::class);
        $service->shouldReceive('isConfigured')->once()->andReturnTrue();
        $service->shouldReceive('purchaseStatus')->once()->andReturn([
            'designId' => $design->public_id,
            'status' => 'pending',
        ]);
        $this->app->instance(RazorpayPaymentService::class, $service);

        $this->getJson('/api/v1/purchases/designs/'.$design->public_id)
            ->assertOk()
            ->assertJsonPath('data.status', 'pending');
    }

    public function test_order_endpoint_maps_provider_network_failures_to_a_safe_503_response(): void
    {
        $buyer = User::factory()->create();
        $design = $this->designOwnedBy(User::factory()->create());
        Sanctum::actingAs($buyer);

        $service = Mockery::mock(RazorpayPaymentService::class);
        $service->shouldReceive('isConfigured')->once()->andReturnTrue();
        $service->shouldReceive('createOrder')->once()->andThrow(new \Exception('Sensitive provider error'));
        $this->app->instance(RazorpayPaymentService::class, $service);

        $this->postJson('/api/v1/payments/orders', ['designId' => $design->public_id])
            ->assertStatus(503)
            ->assertJson([
                'success' => false,
                'message' => 'Payment provider is temporarily unavailable.',
                'code' => 'payment_provider_unavailable',
            ])
            ->assertDontSee('Sensitive provider error');
    }

    private function designOwnedBy(User $owner, int $amount = 29900): Design
    {
        return Design::query()->create([
            'user_id' => $owner->id,
            'title' => 'Paid Design',
            'price_paise' => $amount,
            'size' => '6 x 6',
            'color' => 'Gold',
        ]);
    }
}
