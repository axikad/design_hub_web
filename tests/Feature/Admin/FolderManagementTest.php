<?php

namespace Tests\Feature\Admin;

use App\Models\Folder;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;
use ZipArchive;

class FolderManagementTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_can_create_replace_and_delete_an_extracted_folder(): void
    {
        Storage::fake('local');
        $admin = User::factory()->superAdmin()->create();

        $this->actingAs($admin)->get('/admin/folders/create')->assertOk();

        $this->actingAs($admin)->post('/admin/folders', [
            'name' => 'Bridal Pack',
            'priceRupees' => '299.50',
            'zipFile' => $this->zipUpload('bridal.zip', [
                'front/design.emb' => 'embroidery-data',
                'notes.txt' => 'Read me',
            ]),
        ])->assertRedirect(route('admin.folders.index'));

        $folder = Folder::query()->where('name', 'Bridal Pack')->firstOrFail();
        $this->assertSame(29950, $folder->price_paise);
        $this->assertSame(2, $folder->file_count);
        $this->assertSame(['front/design.emb', 'notes.txt'], $folder->files()->pluck('relative_path')->all());
        Storage::disk('local')->assertExists($folder->zip_path);
        Storage::disk('local')->assertExists($folder->files()->firstOrFail()->storage_path);

        $this->actingAs($admin)->get("/admin/folders/{$folder->public_id}")
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('admin/folders/show')
                ->where('folder.name', 'Bridal Pack')
                ->has('folder.files', 2));

        $oldZipPath = $folder->zip_path;

        $this->actingAs($admin)->put("/admin/folders/{$folder->public_id}", [
            'name' => 'Updated Bridal Pack',
            'priceRupees' => '399',
            'zipFile' => $this->zipUpload('updated.zip', [
                'updated.dst' => 'new-data',
            ]),
        ])->assertRedirect(route('admin.folders.index'));

        $folder->refresh();
        $this->assertSame('Updated Bridal Pack', $folder->name);
        $this->assertSame(39900, $folder->price_paise);
        $this->assertSame('updated.zip', $folder->zip_name);
        $this->assertSame(['updated.dst'], $folder->files()->pluck('relative_path')->all());
        Storage::disk('local')->assertMissing($oldZipPath);
        Storage::disk('local')->assertExists($folder->zip_path);

        $folderRoot = "folders/{$folder->public_id}";
        $this->actingAs($admin)->delete("/admin/folders/{$folder->public_id}")
            ->assertRedirect(route('admin.folders.index'));

        $this->assertDatabaseMissing('folders', ['id' => $folder->id]);
        Storage::disk('local')->assertMissing($folderRoot);
    }

    /** @param array<string, string> $files */
    private function zipUpload(string $name, array $files): UploadedFile
    {
        $path = tempnam(sys_get_temp_dir(), 'folder-zip-');
        $zip = new ZipArchive;
        $zip->open($path, ZipArchive::CREATE | ZipArchive::OVERWRITE);

        foreach ($files as $relativePath => $contents) {
            $zip->addFromString($relativePath, $contents);
        }

        $zip->close();

        return new UploadedFile($path, $name, 'application/zip', null, true);
    }
}
