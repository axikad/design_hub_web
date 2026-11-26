<?php

namespace Tests\Feature\Admin;

use App\Models\Catalog;
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

class CatalogManagementTest extends TestCase
{
    use RefreshDatabase;

    public function test_catalog_archive_validation_accepts_zip_and_rar_extensions(): void
    {
        foreach (['catalog.zip', 'catalog.rar'] as $name) {
            $validator = Validator::make([
                'archive' => UploadedFile::fake()->create($name, 1),
            ], [
                'archive' => ['required', 'file', 'extensions:zip,rar'],
            ]);

            $this->assertTrue($validator->passes(), $validator->errors()->first('archive'));
        }
    }

    public function test_admin_can_create_replace_and_delete_an_extracted_catalog(): void
    {
        Storage::fake('local');
        $admin = User::factory()->superAdmin()->create();
        $seller = User::factory()->create([
            'country_code' => '+91',
            'mobile_number' => '9876543210',
            'phone_key' => '+919876543210',
        ]);

        $this->actingAs($admin)->get('/admin/catalogs/create')->assertOk();

        $this->actingAs($admin)->post('/admin/catalogs', [
            'ownerId' => $seller->public_id,
            'name' => 'Bridal Pack',
            'priceRupees' => '299.50',
            'zipFile' => $this->zipUpload('bridal.zip', [
                'front/preview.svg' => '<svg xmlns="http://www.w3.org/2000/svg"><rect width="20" height="20" fill="red"/></svg>',
                'notes.txt' => 'Read me',
            ]),
        ])->assertRedirect(route('admin.catalogs.index'));

        $catalog = Catalog::query()->where('name', 'Bridal Pack')->firstOrFail();
        $this->assertSame($seller->id, $catalog->user_id);
        $this->assertSame(29950, $catalog->price_paise);
        $this->assertSame(2, $catalog->file_count);
        $this->assertSame(['front/preview.svg', 'notes.txt'], $catalog->files()->pluck('relative_path')->all());
        Storage::disk('local')->assertExists($catalog->zip_path);
        Storage::disk('local')->assertExists($catalog->files()->firstOrFail()->storage_path);

        $this->actingAs($admin)->get("/admin/catalogs/{$catalog->public_id}")
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('admin/catalogs/show')
                ->where('catalog.name', 'Bridal Pack')
                ->has('catalog.files', 2)
                ->where('catalog.files.0.previewUrl', route('admin.catalogs.files.preview', [$catalog->public_id, $catalog->files()->first()->id])));

        $previewFile = $catalog->files()->where('extension', 'svg')->firstOrFail();
        $this->actingAs($admin)
            ->get(route('admin.catalogs.files.preview', [$catalog->public_id, $previewFile->id]))
            ->assertOk()
            ->assertHeader('content-type', 'image/svg+xml');

        $oldZipPath = $catalog->zip_path;

        $this->actingAs($admin)->put("/admin/catalogs/{$catalog->public_id}", [
            'ownerId' => $seller->public_id,
            'name' => 'Updated Bridal Pack',
            'priceRupees' => '399',
            'zipFile' => $this->zipUpload('updated.zip', [
                'updated.dst' => 'new-data',
            ]),
        ])->assertRedirect(route('admin.catalogs.index'));

        $catalog->refresh();
        $this->assertSame('Updated Bridal Pack', $catalog->name);
        $this->assertSame(39900, $catalog->price_paise);
        $this->assertSame('updated.zip', $catalog->zip_name);
        $this->assertSame(['updated.dst'], $catalog->files()->pluck('relative_path')->all());
        Storage::disk('local')->assertMissing($oldZipPath);
        Storage::disk('local')->assertExists($catalog->zip_path);

        $catalogRoot = "catalogs/{$catalog->public_id}";
        $this->actingAs($admin)->delete("/admin/catalogs/{$catalog->public_id}")
            ->assertRedirect(route('admin.catalogs.index'));

        $this->assertDatabaseMissing('catalogs', ['id' => $catalog->id]);
        Storage::disk('local')->assertMissing($catalogRoot);
    }

    public function test_non_super_admin_cannot_assign_a_catalog_to_another_seller(): void
    {
        Storage::fake('local');
        $this->seed(RoleAndPermissionSeeder::class);
        $adminRole = Role::query()->where('slug', 'admin')->firstOrFail();
        $admin = User::factory()->create(['role_id' => $adminRole->id]);
        $otherSeller = User::factory()->create();

        $this->actingAs($admin)
            ->get('/admin/catalogs/create')
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->where('canChooseSeller', false)
                ->where('owners.0.id', $admin->public_id));

        $this->actingAs($admin)->post('/admin/catalogs', [
            'ownerId' => $otherSeller->public_id,
            'name' => 'Admin-owned Pack',
            'priceRupees' => '100',
            'zipFile' => $this->zipUpload('admin.zip', ['design.dst' => 'data']),
        ])->assertRedirect(route('admin.catalogs.index'));

        $this->assertDatabaseHas('catalogs', [
            'name' => 'Admin-owned Pack',
            'user_id' => $admin->id,
        ]);
    }

    public function test_marketplace_user_only_sees_and_manages_their_own_catalogs(): void
    {
        $marketplaceRole = Role::query()->where('slug', 'marketplace_user')->firstOrFail();
        $seller = User::factory()->create(['role_id' => $marketplaceRole->id]);
        $otherSeller = User::factory()->create();
        $ownCatalog = Catalog::query()->create([
            'user_id' => $seller->id,
            'name' => 'My Pack',
            'price_paise' => 10000,
            'zip_name' => 'mine.zip',
            'zip_path' => 'catalogs/mine.zip',
        ]);
        $otherCatalog = Catalog::query()->create([
            'user_id' => $otherSeller->id,
            'name' => 'Private Other Pack',
            'price_paise' => 20000,
            'zip_name' => 'other.zip',
            'zip_path' => 'catalogs/other.zip',
        ]);

        $this->actingAs($seller)
            ->get(route('admin.catalogs.index'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->where('ownContentOnly', true)
                ->has('catalogs.data', 1)
                ->where('catalogs.data.0.id', $ownCatalog->public_id));

        $this->actingAs($seller)->get(route('admin.catalogs.show', $otherCatalog->public_id))->assertNotFound();
        $this->actingAs($seller)->get(route('admin.catalogs.edit', $otherCatalog->public_id))->assertNotFound();
        $this->actingAs($seller)->delete(route('admin.catalogs.destroy', $otherCatalog->public_id))->assertNotFound();
        $this->assertDatabaseHas('catalogs', ['id' => $otherCatalog->id]);
    }

    /** @param array<string, string> $files */
    private function zipUpload(string $name, array $files): UploadedFile
    {
        $path = tempnam(sys_get_temp_dir(), 'catalog-zip-');
        $zip = new ZipArchive;
        $zip->open($path, ZipArchive::CREATE | ZipArchive::OVERWRITE);

        foreach ($files as $relativePath => $contents) {
            $zip->addFromString($relativePath, $contents);
        }

        $zip->close();

        return new UploadedFile($path, $name, 'application/zip', null, true);
    }
}
