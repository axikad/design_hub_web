<?php

namespace Tests\Feature;

use App\Exceptions\DuplicateDesignImageException;
use App\Models\Design;
use App\Models\User;
use App\Services\DesignImageMatchingService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use PHPUnit\Framework\Attributes\RequiresPhpExtension;
use Tests\TestCase;

#[RequiresPhpExtension('gd')]
class DesignDuplicateImageValidationTest extends TestCase
{
    use RefreshDatabase;

    public function test_an_exact_image_used_by_another_design_is_rejected(): void
    {
        $upload = UploadedFile::fake()->image('design.jpg', 600, 400);
        $design = Design::query()->create([
            'user_id' => User::factory()->create()->id,
            'title' => 'Existing design',
            'size' => '',
            'color' => '',
            'images' => [],
            'files' => [],
        ]);

        $design->imageFingerprints()->create([
            'image_index' => 0,
            'image_path' => 'previews/existing.jpg',
            'sha256' => hash_file('sha256', $upload->getRealPath()),
            'embedding_status' => 'ready',
        ]);

        try {
            app(DesignImageMatchingService::class)->assertUploadedImagesAreUnique([$upload]);
            $this->fail('The duplicate image was not rejected.');
        } catch (DuplicateDesignImageException $exception) {
            $this->assertSame(100.0, $exception->similarityScore);
            $this->assertSame($design->public_id, $exception->matchedDesignId);
        }
    }

    public function test_the_same_design_is_ignored_during_an_update(): void
    {
        $upload = UploadedFile::fake()->image('design.jpg', 600, 400);
        $design = Design::query()->create([
            'user_id' => User::factory()->create()->id,
            'title' => 'Existing design',
            'size' => '',
            'color' => '',
            'images' => [],
            'files' => [],
        ]);

        $design->imageFingerprints()->create([
            'image_index' => 0,
            'image_path' => 'previews/existing.jpg',
            'sha256' => hash_file('sha256', $upload->getRealPath()),
            'embedding_status' => 'ready',
        ]);

        config(['designhub.image_matching.gemini.enabled' => false]);

        app(DesignImageMatchingService::class)->assertUploadedImagesAreUnique([$upload], $design);

        $this->assertTrue(true);
    }
}
