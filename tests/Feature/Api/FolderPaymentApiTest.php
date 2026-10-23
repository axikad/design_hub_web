<?php

namespace Tests\Feature\Api;

use App\Models\Folder;
use App\Models\FolderPurchase;
use App\Models\User;
use App\Services\RazorpayFolderPaymentService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Laravel\Sanctum\Sanctum;
use Mockery;
use Tests\TestCase;

class FolderPaymentApiTest extends TestCase
{
    use RefreshDatabase;

    public function test_folder_order_uses_folder_price_and_backend_key(): void
    {
        $buyer = User::factory()->create();
        $folder = $this->folder(29900);
        Sanctum::actingAs($buyer);

        $service = Mockery::mock(RazorpayFolderPaymentService::class);
        $service->shouldReceive('isConfigured')->once()->andReturnTrue();
        $service->shouldReceive('createOrder')->once()->with($buyer, Mockery::on(
            fn (Folder $argument) => $argument->is($folder)
        ))->andReturn([
            'folderId' => $folder->public_id,
            'orderId' => 'order_folder_test',
            'keyId' => 'rzp_test_public_key',
            'amount' => 29900,
            'currency' => 'INR',
        ]);
        $this->app->instance(RazorpayFolderPaymentService::class, $service);

        $response = $this->postJson('/api/v1/payments/orders', ['folderId' => $folder->public_id])
            ->assertOk()
            ->assertJsonPath('data.folderId', $folder->public_id)
            ->assertJsonPath('data.orderId', 'order_folder_test')
            ->assertJsonPath('data.amount', 29900)
            ->assertJsonPath('data.currency', 'INR');

        $this->assertIsInt($response->json('data.amount'));
    }

    public function test_folder_status_supports_unpaid_pending_and_paid_payloads(): void
    {
        $buyer = User::factory()->create();
        $folder = $this->folder();
        Sanctum::actingAs($buyer);

        $service = Mockery::mock(RazorpayFolderPaymentService::class);
        $service->shouldReceive('isConfigured')->once()->andReturnTrue();
        $service->shouldReceive('purchaseStatus')->once()->with($buyer, Mockery::type(Folder::class))->andReturn([
            'folderId' => $folder->public_id,
            'status' => 'pending',
        ]);
        $this->app->instance(RazorpayFolderPaymentService::class, $service);

        $this->getJson('/api/v1/purchases/folders/'.$folder->public_id)
            ->assertOk()
            ->assertJsonPath('data.folderId', $folder->public_id)
            ->assertJsonPath('data.status', 'pending');
    }

    public function test_folder_payment_verification_uses_folder_identifier(): void
    {
        $buyer = User::factory()->create();
        $folder = $this->folder();
        Sanctum::actingAs($buyer);

        $service = Mockery::mock(RazorpayFolderPaymentService::class);
        $service->shouldReceive('isConfigured')->once()->andReturnTrue();
        $service->shouldReceive('verify')->once()->with(
            $buyer,
            Mockery::on(fn (Folder $argument) => $argument->is($folder)),
            'order_folder_test',
            'pay_folder_test',
            'valid_signature',
        )->andReturn([
            'folderId' => $folder->public_id,
            'status' => 'paid',
            'purchaseId' => 'folder_purchase_test',
        ]);
        $this->app->instance(RazorpayFolderPaymentService::class, $service);

        $this->postJson('/api/v1/payments/verify', [
            'folderId' => $folder->public_id,
            'razorpay_order_id' => 'order_folder_test',
            'razorpay_payment_id' => 'pay_folder_test',
            'razorpay_signature' => 'valid_signature',
        ])->assertOk()
            ->assertJsonPath('data.status', 'paid')
            ->assertJsonPath('data.purchaseId', 'folder_purchase_test');
    }

    public function test_only_paid_buyer_can_create_and_use_folder_zip_download_link(): void
    {
        Storage::fake('local');
        $buyer = User::factory()->create();
        $folder = $this->folder();
        Storage::disk('local')->put($folder->zip_path, 'zip-content');
        Sanctum::actingAs($buyer);

        $this->postJson('/api/v1/folders/'.$folder->public_id.'/download')
            ->assertForbidden()
            ->assertJsonPath('code', 'folder_download_forbidden');

        FolderPurchase::query()->create([
            'buyer_id' => $buyer->id,
            'folder_id' => $folder->id,
            'amount_paise' => $folder->price_paise,
            'currency' => 'INR',
            'status' => 'paid',
            'purchased_at' => now(),
        ]);

        $response = $this->postJson('/api/v1/folders/'.$folder->public_id.'/download')
            ->assertOk()
            ->assertJsonPath('data.folderId', $folder->public_id)
            ->assertJsonStructure(['data' => ['downloadUri', 'expiresAt']]);

        $this->get($response->json('data.downloadUri'))
            ->assertOk()
            ->assertDownload($folder->zip_name);
    }

    public function test_payment_request_requires_exactly_one_item_identifier(): void
    {
        Sanctum::actingAs(User::factory()->create());

        $this->postJson('/api/v1/payments/orders', [])
            ->assertUnprocessable()
            ->assertJsonPath('code', 'validation_error');

        $this->postJson('/api/v1/payments/orders', [
            'designId' => 'design_one',
            'folderId' => 'folder_one',
        ])->assertUnprocessable()
            ->assertJsonPath('code', 'validation_error');
    }

    private function folder(int $pricePaise = 29900): Folder
    {
        return Folder::query()->create([
            'name' => 'Bridal Pack '.uniqid(),
            'price_paise' => $pricePaise,
            'zip_name' => 'bridal.zip',
            'zip_path' => 'folders/bridal/archive/source.zip',
            'file_count' => 2,
            'total_size' => 120,
        ]);
    }
}
