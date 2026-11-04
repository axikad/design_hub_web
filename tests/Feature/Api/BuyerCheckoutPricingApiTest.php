<?php

namespace Tests\Feature\Api;

use App\Models\Design;
use App\Models\Folder;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class BuyerCheckoutPricingApiTest extends TestCase
{
    use RefreshDatabase;

    public function test_buyer_can_preview_design_checkout_without_tax_or_buyer_fee(): void
    {
        $seller = User::factory()->create();
        $buyer = User::factory()->create();
        $design = $this->designOwnedBy($seller, 10000);
        Sanctum::actingAs($buyer);

        $this->getJson('/api/v1/payments/checkout-preview?designId='.$design->public_id)
            ->assertOk()
            ->assertJsonPath('message', 'Buyer checkout breakdown calculated successfully.')
            ->assertJsonPath('data.currency', 'INR')
            ->assertJsonPath('data.paymentEnvironment', 'test')
            ->assertJsonPath('data.isTestMode', true)
            ->assertJsonPath('data.item.type', 'design')
            ->assertJsonPath('data.item.id', $design->public_id)
            ->assertJsonPath('data.item.name', 'Buyer Preview Design')
            ->assertJsonPath('data.itemPricePaise', 10000)
            ->assertJsonPath('data.taxes.enabled', false)
            ->assertJsonPath('data.taxes.totalPaise', 0)
            ->assertJsonCount(0, 'data.taxes.lines')
            ->assertJsonPath('data.buyerFee.enabled', false)
            ->assertJsonPath('data.buyerFee.amountPaise', 0)
            ->assertJsonPath('data.totalPayablePaise', 10000);
    }

    public function test_buyer_can_preview_folder_checkout_without_payment_provider_keys(): void
    {
        config([
            'designhub.razorpay_key_id' => null,
            'designhub.razorpay_key_secret' => null,
        ]);

        $seller = User::factory()->create();
        $buyer = User::factory()->create();
        $folder = $this->folderOwnedBy($seller, 29900);
        Sanctum::actingAs($buyer);

        $this->getJson('/api/v1/payments/checkout-preview?folderId='.$folder->public_id)
            ->assertOk()
            ->assertJsonPath('data.item.type', 'folder')
            ->assertJsonPath('data.item.id', $folder->public_id)
            ->assertJsonPath('data.item.name', 'Buyer Preview Folder')
            ->assertJsonPath('data.itemPricePaise', 29900)
            ->assertJsonPath('data.totalPayablePaise', 29900);
    }

    public function test_checkout_preview_requires_exactly_one_item_identifier(): void
    {
        Sanctum::actingAs(User::factory()->create());

        $this->getJson('/api/v1/payments/checkout-preview')
            ->assertUnprocessable()
            ->assertJsonPath('code', 'validation_error');

        $this->getJson('/api/v1/payments/checkout-preview?designId=design_one&folderId=folder_one')
            ->assertUnprocessable()
            ->assertJsonPath('code', 'validation_error')
            ->assertJsonValidationErrors('folderId');
    }

    public function test_checkout_preview_rejects_an_owner_and_an_unavailable_folder(): void
    {
        $owner = User::factory()->create();
        $design = $this->designOwnedBy($owner, 10000);
        $folder = $this->folderOwnedBy($owner, 0);
        Sanctum::actingAs($owner);

        $this->getJson('/api/v1/payments/checkout-preview?designId='.$design->public_id)
            ->assertUnprocessable()
            ->assertJsonValidationErrors('designId');

        $this->getJson('/api/v1/payments/checkout-preview?folderId='.$folder->public_id)
            ->assertUnprocessable()
            ->assertJsonValidationErrors('folderId');
    }

    public function test_checkout_preview_requires_authentication(): void
    {
        $this->getJson('/api/v1/payments/checkout-preview?designId=design_example')
            ->assertUnauthorized();
    }

    private function designOwnedBy(User $owner, int $pricePaise): Design
    {
        return Design::query()->create([
            'user_id' => $owner->id,
            'title' => 'Buyer Preview Design',
            'price_paise' => $pricePaise,
            'size' => '6 x 6',
            'color' => 'Gold',
        ]);
    }

    private function folderOwnedBy(User $owner, int $pricePaise): Folder
    {
        return Folder::query()->create([
            'user_id' => $owner->id,
            'name' => 'Buyer Preview Folder',
            'price_paise' => $pricePaise,
            'zip_name' => 'buyer-preview.zip',
            'zip_path' => 'folders/buyer-preview/archive/source.zip',
            'file_count' => 2,
            'total_size' => 120,
        ]);
    }
}
