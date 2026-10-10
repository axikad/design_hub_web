<?php

namespace Tests\Feature\Api;

use App\Models\Design;
use App\Models\User;
use App\Services\UploadService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class DesignThumbnailTest extends TestCase
{
    use RefreshDatabase;

    public function test_each_design_image_has_an_ordered_thumbnail_without_changing_the_original(): void
    {
        Storage::fake('public');
        $owner = User::factory()->create();
        $uploads = app(UploadService::class);

        $front = $uploads->store($owner, UploadedFile::fake()->image('front.jpg', 1200, 800));
        $back = $uploads->store($owner, UploadedFile::fake()->image('back.png', 800, 1200));
        $frontHash = hash('sha256', Storage::disk('public')->get($front->path));

        $design = Design::query()->create([
            'user_id' => $owner->id,
            'title' => 'Thumbnail Design',
            'price_paise' => 29900,
            'size' => '6 x 6',
            'color' => 'Gold',
            'preview_path' => $front->path,
            'images' => [
                ['name' => 'front.jpg', 'path' => $front->path],
                ['name' => 'back.png', 'path' => $back->path],
            ],
        ]);

        Sanctum::actingAs(User::factory()->create());
        $response = $this->getJson('/api/v1/designs/'.$design->public_id)
            ->assertOk()
            ->assertJsonPath('data.images.0.name', 'front.jpg')
            ->assertJsonPath('data.images.1.name', 'back.png')
            ->assertJsonStructure(['data' => ['images' => ['*' => ['uri', 'thumbnailUri']]]]);

        $this->assertNotSame($response->json('data.images.0.uri'), $response->json('data.images.0.thumbnailUri'));
        $this->assertNotSame($response->json('data.images.1.uri'), $response->json('data.images.1.thumbnailUri'));
        $this->assertSame($frontHash, hash('sha256', Storage::disk('public')->get($front->path)));

        $frontThumbnailPath = 'thumbnails/'.pathinfo($front->path, PATHINFO_DIRNAME).'/'
            .pathinfo($front->path, PATHINFO_FILENAME).'-thumb.jpg';
        $frontThumbnail = imagecreatefromstring(Storage::disk('public')->get($frontThumbnailPath));
        $this->assertLessThanOrEqual(480, imagesx($frontThumbnail));
        $this->assertLessThanOrEqual(480, imagesy($frontThumbnail));
        imagedestroy($frontThumbnail);
    }
}
