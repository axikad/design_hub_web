<?php

namespace Tests\Feature\Api;

use App\Models\Design;
use App\Models\User;
use App\Services\DesignImageMatchingService;
use App\Services\GeminiImageEmbeddingService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class DesignImageSearchApiTest extends TestCase
{
    use RefreshDatabase;

    public function test_authenticated_user_can_search_published_designs_by_image_without_ai(): void
    {
        if (! function_exists('imagecreatetruecolor')) {
            $this->markTestSkipped('GD is not installed.');
        }

        config([
            'designhub.image_matching.gemini.enabled' => false,
        ]);
        Storage::fake('public');
        $owner = User::factory()->create();
        $image = UploadedFile::fake()->image('flower.png', 320, 240);
        $contents = file_get_contents($image->getRealPath());
        $path = $image->store('previews/'.$owner->public_id, 'public');
        $design = Design::query()->create([
            'user_id' => $owner->id,
            'title' => 'Flower design',
            'price_paise' => 2500,
            'size' => '10 x 10',
            'color' => '',
            'preview_path' => $path,
            'images' => [['name' => 'flower.png', 'path' => $path]],
        ]);
        app(DesignImageMatchingService::class)->syncDesign($design);

        Sanctum::actingAs(User::factory()->create());
        $queryPath = tempnam(sys_get_temp_dir(), 'design-search-');
        file_put_contents($queryPath, $contents);
        $query = new UploadedFile($queryPath, 'query.png', 'image/png', null, true);

        $this->post('/api/v1/designs/search-by-image', ['image' => $query], ['Accept' => 'application/json'])
            ->assertOk()
            ->assertJsonPath('data.engine', 'local_visual')
            ->assertJsonPath('data.results.0.id', $design->public_id)
            ->assertJsonPath('data.results.0.similarityScore', 100)
            ->assertJsonPath('data.results.0.similarityLabel', 'Very close');
    }

    public function test_image_search_returns_a_standard_validation_error_for_an_oversized_image(): void
    {
        Sanctum::actingAs(User::factory()->create());

        $this->post('/api/v1/designs/search-by-image', [
            'image' => UploadedFile::fake()->create('large.jpg', 10241, 'image/jpeg'),
        ], ['Accept' => 'application/json'])
            ->assertUnprocessable()
            ->assertJsonPath('code', 'validation_error')
            ->assertJsonStructure(['errors' => ['image']]);
    }

    public function test_design_sync_stores_gemini_embedding_in_the_database(): void
    {
        if (! function_exists('imagecreatetruecolor')) {
            $this->markTestSkipped('GD is not installed.');
        }

        Storage::fake('public');
        $owner = User::factory()->create();
        $image = UploadedFile::fake()->image('reference.png', 320, 240);
        $contents = file_get_contents($image->getRealPath());
        $path = $image->store('previews/'.$owner->public_id, 'public');
        $design = Design::query()->create([
            'user_id' => $owner->id,
            'title' => 'Indexed design',
            'price_paise' => 2500,
            'size' => '10 x 10',
            'color' => '',
            'images' => [['name' => 'reference.png', 'path' => $path]],
        ]);

        $embeddings = $this->mock(GeminiImageEmbeddingService::class);
        $embeddings->shouldReceive('enabled')->andReturnTrue();
        $embeddings->shouldReceive('embedImage')
            ->twice()
            ->withArgs(function (string $contents, string $mime): bool {
                return $contents !== '' && $mime === 'image/png';
            })
            ->andReturn(array_fill(0, 512, 0.01));

        app(DesignImageMatchingService::class)->syncDesign($design);

        $record = $design->imageFingerprints()->firstOrFail();

        $this->assertDatabaseHas('design_image_fingerprints', [
            'design_id' => $design->id,
            'image_index' => 0,
            'embedding_model' => 'gemini-embedding-2',
            'embedding_status' => 'ready',
            'embedding_error' => null,
        ]);
        $this->assertCount(512, $record->embedding);

        $search = app(DesignImageMatchingService::class)->searchWithMeta($contents, 'image/png');

        $this->assertSame('gemini_embedding_2', $search['engine']);
        $this->assertSame($design->public_id, $search['matches'][0]['designId']);
        $this->assertSame(100.0, $search['matches'][0]['score']);
    }
}
