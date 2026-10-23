<?php

namespace Tests\Feature\Admin;

use App\Models\Design;
use App\Models\Folder;
use App\Models\FolderPurchase;
use App\Models\Purchase;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class PurchaseManagementTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_can_review_design_and_folder_purchases_together(): void
    {
        $admin = User::factory()->superAdmin()->create();
        $buyer = User::factory()->create();
        $design = Design::query()->create([
            'user_id' => User::factory()->create()->id,
            'title' => 'Floral Design',
            'price_paise' => 15000,
            'size' => '6 x 6',
            'color' => 'Gold',
        ]);
        $folder = Folder::query()->create([
            'name' => 'Bridal Folder',
            'price_paise' => 29900,
            'zip_name' => 'bridal.zip',
            'zip_path' => 'folders/bridal/archive/source.zip',
            'file_count' => 2,
            'total_size' => 100,
        ]);

        Purchase::query()->create([
            'buyer_id' => $buyer->id,
            'design_id' => $design->id,
            'amount_paise' => 15000,
            'currency' => 'INR',
            'status' => 'paid',
            'purchased_at' => now()->subMinute(),
        ]);
        $folderPurchase = FolderPurchase::query()->create([
            'buyer_id' => $buyer->id,
            'folder_id' => $folder->id,
            'amount_paise' => 29900,
            'currency' => 'INR',
            'status' => 'paid',
            'purchased_at' => now(),
        ]);

        $this->actingAs($admin)->get(route('admin.purchases.index'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('admin/purchases/index')
                ->has('purchases.data', 2)
                ->where('purchases.data.0.itemType', 'folder')
                ->where('purchases.data.0.itemName', 'Bridal Folder')
                ->where('purchases.data.1.itemType', 'design'));

        $this->actingAs($admin)->get(route('admin.purchases.index', ['type' => 'folder']))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->has('purchases.data', 1)
                ->where('purchases.data.0.id', $folderPurchase->public_id));

        $this->actingAs($admin)->get(route('admin.purchases.edit', $folderPurchase->public_id))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('admin/purchases/form')
                ->where('purchase.itemType', 'folder')
                ->where('purchase.item.name', 'Bridal Folder'));

        $this->actingAs($admin)->put(route('admin.purchases.update', $folderPurchase->public_id), [
            'status' => 'refunded',
        ])->assertRedirect(route('admin.purchases.index'));

        $this->assertDatabaseHas('folder_purchases', [
            'id' => $folderPurchase->id,
            'status' => 'refunded',
        ]);
    }

    public function test_folder_details_include_purchase_summary_and_buyers(): void
    {
        $admin = User::factory()->superAdmin()->create();
        $buyer = User::factory()->create(['name' => 'Folder Buyer']);
        $folder = Folder::query()->create([
            'name' => 'Collection Pack',
            'price_paise' => 50000,
            'zip_name' => 'collection.zip',
            'zip_path' => 'folders/collection/archive/source.zip',
            'file_count' => 0,
            'total_size' => 0,
        ]);
        FolderPurchase::query()->create([
            'buyer_id' => $buyer->id,
            'folder_id' => $folder->id,
            'amount_paise' => 50000,
            'currency' => 'INR',
            'status' => 'paid',
            'purchased_at' => now(),
        ]);

        $this->actingAs($admin)->get(route('admin.folders.show', $folder->public_id))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->where('folder.paidPurchaseCount', 1)
                ->where('folder.revenuePaise', 50000)
                ->where('folder.purchases.0.buyerName', 'Folder Buyer'));
    }
}
