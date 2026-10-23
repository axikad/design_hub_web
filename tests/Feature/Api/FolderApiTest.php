<?php

namespace Tests\Feature\Api;

use App\Models\Folder;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class FolderApiTest extends TestCase
{
    use RefreshDatabase;

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

        $response = $this->getJson('/api/v1/folders/'.$folder->public_id)
            ->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('message', 'Folder retrieved successfully.')
            ->assertJsonPath('data.zipName', 'bridal.zip')
            ->assertJsonPath('data.files.0.relativePath', 'design.emb')
            ->assertJsonPath('data.files.0.previewUri', null)
            ->assertJsonPath('data.files.1.id', $image->public_id)
            ->assertJsonPath('data.files.1.relativePath', 'images/preview.svg');

        $previewUrl = $response->json('data.files.1.previewUri');
        $this->assertNotNull($previewUrl);
        $this->get($previewUrl)
            ->assertOk()
            ->assertHeader('content-type', 'image/svg+xml');
    }

    public function test_folder_api_returns_standard_authentication_and_not_found_errors(): void
    {
        $this->getJson('/api/v1/folders')
            ->assertUnauthorized()
            ->assertJsonPath('code', 'unauthenticated');

        Sanctum::actingAs(User::factory()->create());

        $this->getJson('/api/v1/folders/folder_missing')
            ->assertNotFound()
            ->assertJson([
                'success' => false,
                'message' => 'Folder not found.',
                'code' => 'folder_not_found',
            ]);
    }
}
