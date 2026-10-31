<?php

namespace Tests\Feature\Api;

use App\Models\Design;
use App\Models\Machine;
use App\Models\Purchase;
use App\Models\User;
use App\Services\DesignWatermarkService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class DesignWatermarkApiTest extends TestCase
{
    use RefreshDatabase;

    public function test_watermarked_machine_exposes_protected_preview_and_owner_can_download_clean_image(): void
    {
        Storage::fake('public');
        $owner = User::factory()->create();
        $machine = Machine::query()->create([
            'name' => 'Sketch Images',
            'watermark_config' => ['enabled' => true, 'text' => 'DesignHub', 'opacity' => 28],
        ]);
        $image = UploadedFile::fake()->image('original.png', 800, 600);
        $originalPath = $image->store('previews/'.$owner->public_id, 'public');
        $design = Design::query()->create([
            'user_id' => $owner->id,
            'machine_id' => $machine->id,
            'title' => 'Sketch One',
            'price_paise' => 39900,
            'size' => '',
            'color' => '',
            'images' => [['name' => 'original.png', 'path' => $originalPath]],
            'preview_path' => $originalPath,
        ]);

        app(DesignWatermarkService::class)->syncDesign($design);
        $design->refresh();
        $watermarkedPath = $design->images[0]['watermarkedPath'];

        Storage::disk('public')->assertExists($originalPath);
        Storage::disk('public')->assertExists($watermarkedPath);
        $this->assertNotSame(Storage::disk('public')->get($originalPath), Storage::disk('public')->get($watermarkedPath));

        Sanctum::actingAs($owner);
        $this->getJson('/api/v1/designs/'.$design->public_id)
            ->assertOk()
            ->assertJsonPath('data.images.0.isWatermarked', true)
            ->assertJsonPath('data.images.0.uri', Storage::disk('public')->url($watermarkedPath))
            ->assertJsonPath('data.images.0.downloadUri', url('/api/v1/designs/'.$design->public_id.'/images/0/download'));

        $download = $this->postJson('/api/v1/designs/'.$design->public_id.'/images/0/download')
            ->assertOk()
            ->assertJsonPath('data.fileName', 'original.png');

        $this->get($download->json('data.url'))
            ->assertOk()
            ->assertDownload('original.png');
    }

    public function test_only_owner_or_paid_buyer_can_download_clean_image(): void
    {
        Storage::fake('public');
        $owner = User::factory()->create();
        $buyer = User::factory()->create();
        $machine = Machine::query()->create(['name' => 'Sketch Images']);
        $path = UploadedFile::fake()->image('clean.jpg')->store('previews/'.$owner->public_id, 'public');
        $design = Design::query()->create([
            'user_id' => $owner->id,
            'machine_id' => $machine->id,
            'title' => 'Clean Sketch',
            'price_paise' => 19900,
            'size' => '',
            'color' => '',
            'images' => [['name' => 'clean.jpg', 'path' => $path]],
            'preview_path' => $path,
        ]);

        Sanctum::actingAs($buyer);
        $this->postJson('/api/v1/designs/'.$design->public_id.'/images/0/download')
            ->assertForbidden()
            ->assertJsonPath('code', 'design_image_download_forbidden');

        Purchase::query()->create([
            'buyer_id' => $buyer->id,
            'design_id' => $design->id,
            'amount_paise' => 19900,
            'currency' => 'INR',
            'status' => 'paid',
            'purchased_at' => now(),
        ]);

        $this->postJson('/api/v1/designs/'.$design->public_id.'/images/0/download')
            ->assertOk()
            ->assertJsonPath('data.fileName', 'clean.jpg');
    }
}
