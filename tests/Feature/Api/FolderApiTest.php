<?php

namespace Tests\Feature\Api;

use App\Models\Folder;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;
use ZipArchive;

class FolderApiTest extends TestCase
{
    use RefreshDatabase;

    public function test_authenticated_user_can_create_a_folder_from_a_zip_archive(): void
    {
        Storage::fake('local');
        $owner = User::factory()->create();
        Sanctum::actingAs($owner);

        $response = $this->post('/api/v1/folders', [
            'name' => 'Mobile Pack',
            'pricePaise' => 39900,
            'file' => $this->zipUpload('mobile-pack.zip', [
                'images/preview.svg' => '<svg xmlns="http://www.w3.org/2000/svg"></svg>',
                'design.emb' => 'embroidery-data',
            ]),
        ], ['Accept' => 'application/json']);

        $response
            ->assertCreated()
            ->assertJsonPath('success', true)
            ->assertJsonPath('message', 'Folder created successfully.')
            ->assertJsonPath('data.name', 'Mobile Pack')
            ->assertJsonPath('data.pricePaise', 39900)
            ->assertJsonPath('data.fileCount', 2)
            ->assertJsonPath('data.zipName', 'mobile-pack.zip')
            ->assertJsonCount(2, 'data.files');

        $folder = Folder::query()->where('name', 'Mobile Pack')->firstOrFail();
        $this->assertSame($owner->id, $folder->user_id);
        Storage::disk('local')->assertExists($folder->zip_path);
        $this->assertSame(2, $folder->files()->count());
    }

    public function test_folder_create_accepts_mobile_compatible_archive_and_rupee_aliases(): void
    {
        Storage::fake('local');
        Sanctum::actingAs(User::factory()->create());

        $this->post('/api/v1/folders', [
            'name' => 'Compatible Pack',
            'price' => '399',
            'zipFile' => $this->zipUpload('compatible.zip', ['design.dst' => 'data']),
        ], ['Accept' => 'application/json'])
            ->assertCreated()
            ->assertJsonPath('data.pricePaise', 39900);
    }

    public function test_folder_create_requires_an_archive_and_authentication(): void
    {
        $this->postJson('/api/v1/folders')->assertUnauthorized();

        Sanctum::actingAs(User::factory()->create());

        $this->postJson('/api/v1/folders', [
            'name' => 'Missing Archive',
            'pricePaise' => 39900,
        ])
            ->assertUnprocessable()
            ->assertJsonPath('code', 'validation_error')
            ->assertJsonValidationErrors('file');
    }

    public function test_folder_list_is_paginated_and_folder_details_include_signed_image_previews(): void
    {
        Storage::fake('local');
        Sanctum::actingAs(User::factory()->create());

        $folder = Folder::query()->create([
            'name' => 'Bridal Pack',
            'price_paise' => 29900,
            'zip_name' => 'bridal.zip',
            'zip_path' => 'folders/bridal/archive/source.zip',
            'file_count' => 2,
            'total_size' => 120,
        ]);
        $image = $folder->files()->create([
            'name' => 'preview.svg',
            'relative_path' => 'images/preview.svg',
            'storage_path' => 'folders/bridal/files/images/preview.svg',
            'extension' => 'svg',
            'mime_type' => 'image/svg+xml',
            'size' => 100,
        ]);
        $folder->files()->create([
            'name' => 'design.emb',
            'relative_path' => 'design.emb',
            'storage_path' => 'folders/bridal/files/design.emb',
            'extension' => 'emb',
            'mime_type' => 'application/octet-stream',
            'size' => 20,
        ]);
        Storage::disk('local')->put($image->storage_path, '<svg xmlns="http://www.w3.org/2000/svg"></svg>');

        $this->getJson('/api/v1/folders?per_page=1')
            ->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('message', 'Folders retrieved successfully.')
            ->assertJsonPath('data.0.id', $folder->public_id)
            ->assertJsonPath('data.0.pricePaise', 29900)
            ->assertJsonPath('data.0.currency', 'INR')
            ->assertJsonPath('meta.per_page', 1)
            ->assertJsonStructure(['data' => [['coverImageUri']], 'meta', 'links']);

        $this->getJson('/api/v1/folders/'.$folder->public_id.'?per_page=1&page=1')
            ->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('message', 'Folder retrieved successfully.')
            ->assertJsonPath('data.zipName', 'bridal.zip')
            ->assertJsonPath('data.files.0.relativePath', 'design.emb')
            ->assertJsonPath('data.files.0.previewUri', null)
            ->assertJsonCount(1, 'data.files')
            ->assertJsonPath('meta.current_page', 1)
            ->assertJsonPath('meta.per_page', 1)
            ->assertJsonPath('meta.total', 2)
            ->assertJsonPath('meta.last_page', 2)
            ->assertJsonPath('links.prev', null)
            ->assertJsonPath('links.next', fn ($url) => is_string($url) && str_contains($url, 'page=2'));

        $response = $this->getJson('/api/v1/folders/'.$folder->public_id.'?per_page=1&page=2')
            ->assertOk()
            ->assertJsonPath('data.files.0.id', $image->public_id)
            ->assertJsonPath('data.files.0.relativePath', 'images/preview.svg')
            ->assertJsonCount(1, 'data.files')
            ->assertJsonPath('meta.current_page', 2)
            ->assertJsonPath('links.next', null);

        $previewUrl = $response->json('data.files.0.previewUri');
        $this->assertNotNull($previewUrl);
        $this->get($previewUrl)
            ->assertOk()
            ->assertHeader('content-type', 'image/svg+xml');
    }

    public function test_authenticated_user_can_only_list_their_own_folders(): void
    {
        $owner = User::factory()->create();
        $otherUser = User::factory()->create();

        $ownFolder = Folder::query()->create([
            'user_id' => $owner->id,
            'name' => 'My Bridal Pack',
            'price_paise' => 39900,
            'zip_name' => 'mine.zip',
            'zip_path' => 'folders/mine.zip',
            'file_count' => 4,
            'total_size' => 1200,
        ]);
        Folder::query()->create([
            'user_id' => $otherUser->id,
            'name' => 'Another Seller Pack',
            'price_paise' => 49900,
            'zip_name' => 'other.zip',
            'zip_path' => 'folders/other.zip',
        ]);

        $this->getJson('/api/v1/users/me/folders')->assertUnauthorized();

        Sanctum::actingAs($owner);

        $this->getJson('/api/v1/users/me/folders?search=Bridal&per_page=10')
            ->assertOk()
            ->assertJsonPath('message', 'User folders retrieved successfully.')
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.id', $ownFolder->public_id)
            ->assertJsonPath('meta.total', 1)
            ->assertJsonMissing(['name' => 'Another Seller Pack']);
    }

    public function test_folder_api_returns_standard_not_found_errors(): void
    {
        $this->getJson('/api/v1/folders/folder_missing')
            ->assertNotFound()
            ->assertJson([
                'success' => false,
                'message' => 'Folder not found.',
                'code' => 'folder_not_found',
            ]);
    }

    /** @param array<string, string> $files */
    private function zipUpload(string $name, array $files): UploadedFile
    {
        $path = tempnam(sys_get_temp_dir(), 'folder-api-zip-');
        $zip = new ZipArchive;
        $zip->open($path, ZipArchive::CREATE | ZipArchive::OVERWRITE);

        foreach ($files as $relativePath => $contents) {
            $zip->addFromString($relativePath, $contents);
        }

        $zip->close();

        return new UploadedFile($path, $name, 'application/zip', null, true);
    }
}
