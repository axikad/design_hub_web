<?php

namespace Tests\Feature\Admin;

use App\Models\Folder;
use App\Models\Role;
use App\Models\User;
use Database\Seeders\RoleAndPermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Validator;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;
use ZipArchive;

class FolderManagementTest extends TestCase
{
    use RefreshDatabase;

    public function test_folder_archive_validation_accepts_zip_and_rar_extensions(): void
    {
        foreach (['folder.zip', 'folder.rar'] as $name) {
            $validator = Validator::make([
                'archive' => UploadedFile::fake()->create($name, 1),
            ], [
                'archive' => ['required', 'file', 'extensions:zip,rar'],
            ]);

            $this->assertTrue($validator->passes(), $validator->errors()->first('archive'));
        }
    }

    public function test_admin_can_create_replace_and_delete_an_extracted_folder(): void
    {
        Storage::fake('local');
        $admin = User::factory()->superAdmin()->create();
        $seller = User::factory()->create([
            'country_code' => '+91',
            'mobile_number' => '9876543210',
            'phone_key' => '+919876543210',
        ]);

        $this->actingAs($admin)->get('/admin/folders/create')->assertOk();

        $this->actingAs($admin)->post('/admin/folders', [
            'ownerId' => $seller->public_id,
            'name' => 'Bridal Pack',
            'priceRupees' => '299.50',
            'zipFile' => $this->zipUpload('bridal.zip', [
                'front/preview.svg' => '<svg xmlns="http://www.w3.org/2000/svg"><rect width="20" height="20" fill="red"/></svg>',
                'notes.txt' => 'Read me',
            ]),
        ])->assertRedirect(route('admin.folders.index'));

        $folder = Folder::query()->where('name', 'Bridal Pack')->firstOrFail();
        $this->assertSame($seller->id, $folder->user_id);
        $this->assertSame(29950, $folder->price_paise);
        $this->assertSame(2, $folder->file_count);
        $this->assertSame(['front/preview.svg', 'notes.txt'], $folder->files()->pluck('relative_path')->all());
        Storage::disk('local')->assertExists($folder->zip_path);
        Storage::disk('local')->assertExists($folder->files()->firstOrFail()->storage_path);

        $this->actingAs($admin)->get("/admin/folders/{$folder->public_id}")
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('admin/folders/show')
                ->where('folder.name', 'Bridal Pack')
                ->has('folder.files', 2)
                ->where('folder.files.0.previewUrl', route('admin.folders.files.preview', [$folder->public_id, $folder->files()->first()->id])));

        $previewFile = $folder->files()->where('extension', 'svg')->firstOrFail();
        $this->actingAs($admin)
            ->get(route('admin.folders.files.preview', [$folder->public_id, $previewFile->id]))
            ->assertOk()
            ->assertHeader('content-type', 'image/svg+xml');

        $oldZipPath = $folder->zip_path;

        $this->actingAs($admin)->put("/admin/folders/{$folder->public_id}", [
            'ownerId' => $seller->public_id,
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

    public function test_non_super_admin_cannot_assign_a_folder_to_another_seller(): void
    {
        Storage::fake('local');
        $this->seed(RoleAndPermissionSeeder::class);
        $adminRole = Role::query()->where('slug', 'admin')->firstOrFail();
        $admin = User::factory()->create(['role_id' => $adminRole->id]);
        $otherSeller = User::factory()->create();

        $this->actingAs($admin)
            ->get('/admin/folders/create')
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->where('canChooseSeller', false)
                ->where('owners.0.id', $admin->public_id));

        $this->actingAs($admin)->post('/admin/folders', [
            'ownerId' => $otherSeller->public_id,
            'name' => 'Admin-owned Pack',
            'priceRupees' => '100',
            'zipFile' => $this->zipUpload('admin.zip', ['design.dst' => 'data']),
        ])->assertRedirect(route('admin.folders.index'));

        $this->assertDatabaseHas('folders', [
            'name' => 'Admin-owned Pack',
            'user_id' => $admin->id,
        ]);
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
