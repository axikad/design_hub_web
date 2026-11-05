<?php

namespace Tests\Feature\Admin;

use App\Models\Design;
use App\Models\DesignCategory;
use App\Models\DesignFileType;
use App\Models\DesignType;
use App\Models\Machine;
use App\Models\MachineType;
use App\Models\Role;
use App\Models\User;
use Database\Seeders\RoleAndPermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class DesignManagementTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_can_create_a_design_with_the_complete_catalog_selection(): void
    {
        Storage::fake('public');
        Storage::fake('local');
        $admin = User::factory()->superAdmin()->create();
        $owner = User::factory()->create();
        [$machine, $machineType, $category, $type, $fileType] = $this->catalog();
        $secondType = DesignType::query()->create([
            'design_category_id' => $category->id,
            'name' => 'Border',
        ]);

        $this->actingAs($admin)->get('/admin/designs/create')->assertOk();

        $this->actingAs($admin)->post('/admin/designs', [
            'ownerId' => $owner->public_id,
            'machineId' => $machine->public_id,
            'machineTypeId' => $machineType->public_id,
            'categoryId' => $category->public_id,
            'designFileTypeId' => $fileType->public_id,
            'priceRupees' => '299.00',
            'sequenceType' => 5.5,
            'variants' => [
                [
                    'typeId' => $type->public_id,
                    'nameOrNumber' => 'Pallu 101',
                    'stitchCount' => 12000,
                    'needle' => 12,
                    'area' => 36,
                    'height' => 6,
                    'width' => 6,
                ],
                [
                    'typeId' => $secondType->public_id,
                    'nameOrNumber' => 'Border 102',
                    'stitchCount' => 8000,
                    'needle' => 8,
                    'area' => 36,
                    'height' => 3,
                    'width' => 12,
                ],
                [
                    'typeId' => '',
                    'customTypeName' => 'Custom Sleeve',
                    'nameOrNumber' => 'Sleeve 103',
                    'stitchCount' => 6000,
                    'needle' => 6,
                    'area' => 18,
                    'height' => 4,
                    'width' => 7,
                ],
            ],
            'previewImages' => [
                UploadedFile::fake()->image('front.jpg'),
                UploadedFile::fake()->image('back.png'),
            ],
            'stitchFiles' => [
                UploadedFile::fake()->create('original.emb', 6000, 'application/octet-stream'),
                UploadedFile::fake()->create('alternate.dst', 100, 'application/octet-stream'),
            ],
        ])->assertRedirect(route('admin.designs.index'));

        $this->assertDatabaseHas('designs', [
            'user_id' => $owner->id,
            'design_type_id' => $type->id,
            'design_file_type_id' => $fileType->id,
            'machine_type_id' => $machineType->public_id,
            'category_id' => $category->public_id,
            'title' => 'Pallu 101',
            'file_name' => 'original.emb',
            'sequence_type' => 5.5,
        ]);
        $this->assertDatabaseHas('design_variants', [
            'design_type_id' => $type->id,
            'name_or_number' => 'Pallu 101',
            'needle' => 12,
        ]);
        $this->assertDatabaseHas('design_variants', [
            'design_type_id' => $secondType->id,
            'name_or_number' => 'Border 102',
            'needle' => 8,
        ]);
        $this->assertDatabaseHas('design_variants', [
            'design_type_id' => null,
            'custom_type_name' => 'Custom Sleeve',
            'name_or_number' => 'Sleeve 103',
        ]);
        $createdDesign = Design::query()->where('title', 'Pallu 101')->firstOrFail();
        $this->assertCount(3, $createdDesign->variants);
        $this->assertCount(2, $createdDesign->images);
        $this->assertSame(['front.jpg', 'back.png'], collect($createdDesign->images)->pluck('name')->all());
        $this->assertSame($createdDesign->images[0]['path'], $createdDesign->preview_path);
        $this->assertCount(2, $createdDesign->files);
    }

    public function test_marketplace_user_only_sees_and_manages_their_own_designs(): void
    {
        $marketplaceRole = Role::query()->where('slug', 'marketplace_user')->firstOrFail();
        $seller = User::factory()->create(['role_id' => $marketplaceRole->id]);
        $otherSeller = User::factory()->create();
        $ownDesign = Design::query()->create([
            'user_id' => $seller->id,
            'title' => 'My Design',
            'price_paise' => 10000,
            'size' => '',
            'color' => '',
            'details' => [],
            'images' => [],
            'files' => [],
        ]);
        $otherDesign = Design::query()->create([
            'user_id' => $otherSeller->id,
            'title' => 'Private Other Design',
            'price_paise' => 20000,
            'size' => '',
            'color' => '',
            'details' => [],
            'images' => [],
            'files' => [],
        ]);

        $this->actingAs($seller)
            ->get(route('admin.designs.index'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->where('ownContentOnly', true)
                ->has('designs.data', 1)
                ->where('designs.data.0.id', $ownDesign->public_id));

        $this->actingAs($seller)->get(route('admin.designs.edit', $otherDesign->public_id))->assertNotFound();
        $this->actingAs($seller)->delete(route('admin.designs.destroy', $otherDesign->public_id))->assertNotFound();
        $this->assertDatabaseHas('designs', ['id' => $otherDesign->id, 'deleted_at' => null]);
    }

    public function test_admin_design_rejects_a_machine_type_from_another_machine(): void
    {
        Storage::fake('public');
        Storage::fake('local');
        $admin = User::factory()->superAdmin()->create();
        $owner = User::factory()->create();
        [, $machineType, $category, $type, $fileType] = $this->catalog();
        $differentMachine = Machine::query()->create(['name' => 'Different Machine']);

        $this->actingAs($admin)->post('/admin/designs', [
            'ownerId' => $owner->public_id,
            'machineId' => $differentMachine->public_id,
            'machineTypeId' => $machineType->public_id,
            'categoryId' => $category->public_id,
            'designFileTypeId' => $fileType->public_id,
            'priceRupees' => '299.00',
            'sequenceType' => 5.5,
            'variants' => [[
                'typeId' => $type->public_id,
                'nameOrNumber' => 'Invalid selection',
                'stitchCount' => 12000,
                'needle' => 12,
                'area' => 36,
                'height' => 6,
                'width' => 6,
            ]],
            'previewImage' => UploadedFile::fake()->image('preview.jpg'),
            'stitchFiles' => [UploadedFile::fake()->create('original.emb', 100, 'application/octet-stream')],
        ])->assertSessionHasErrors('machineTypeId');

        $this->assertDatabaseMissing('designs', ['title' => 'Invalid selection']);
    }

    public function test_admin_can_create_a_design_with_only_fields_enabled_for_the_machine(): void
    {
        Storage::fake('public');
        Storage::fake('local');
        $admin = User::factory()->superAdmin()->create();
        $owner = User::factory()->create();
        $machine = Machine::query()->create([
            'name' => 'Simple Machine',
            'design_form_config' => [
                'machineType' => false,
                'category' => false,
                'designTypes' => false,
                'designSpecifications' => false,
                'sequenceType' => false,
                'designFileType' => false,
                'previewImages' => false,
                'stitchFiles' => false,
                'pricing' => false,
            ],
        ]);

        $this->actingAs($admin)->post('/admin/designs', [
            'ownerId' => $owner->public_id,
            'machineId' => $machine->public_id,
        ])->assertRedirect(route('admin.designs.index'));

        $design = Design::query()->firstOrFail();
        $this->assertSame($machine->id, $design->machine_id);
        $this->assertNull($design->machine_type_id);
        $this->assertNull($design->category_id);
        $this->assertNull($design->design_file_type_id);
        $this->assertSame('Untitled design', $design->title);
        $this->assertSame(0, $design->price_paise);
        $this->assertNull($design->preview_path);
        $this->assertNull($design->stitch_path);
        $this->assertCount(0, $design->variants);
    }

    public function test_non_super_admin_is_the_seller_and_cannot_submit_another_owner(): void
    {
        Storage::fake('public');
        Storage::fake('local');
        $this->seed(RoleAndPermissionSeeder::class);
        $adminRole = Role::query()->where('slug', 'admin')->firstOrFail();
        $admin = User::factory()->create(['role_id' => $adminRole->id]);
        $otherSeller = User::factory()->create();
        $machine = Machine::query()->create([
            'name' => 'Admin Simple Machine',
            'design_form_config' => [
                'machineType' => false,
                'category' => false,
                'designTypes' => false,
                'designSpecifications' => false,
                'sequenceType' => false,
                'designFileType' => false,
                'previewImages' => false,
                'stitchFiles' => false,
                'pricing' => false,
            ],
        ]);

        $this->actingAs($admin)->post('/admin/designs', [
            'ownerId' => $otherSeller->public_id,
            'machineId' => $machine->public_id,
        ])->assertRedirect(route('admin.designs.index'));

        $this->assertDatabaseHas('designs', [
            'user_id' => $admin->id,
            'machine_id' => $machine->id,
        ]);
    }

    public function test_admin_can_replace_design_type_variants_when_editing(): void
    {
        Storage::fake('public');
        Storage::fake('local');
        $admin = User::factory()->superAdmin()->create();
        $owner = User::factory()->create();
        [$machine, $machineType, $category, $type, $fileType] = $this->catalog();
        $secondType = DesignType::query()->create([
            'design_category_id' => $category->id,
            'name' => 'Border',
        ]);
        $design = Design::query()->create([
            'user_id' => $owner->id,
            'design_type_id' => $type->id,
            'design_file_type_id' => $fileType->id,
            'machine_type_id' => $machineType->public_id,
            'category_id' => $category->public_id,
            'title' => 'Old pallu',
            'price_paise' => 29900,
            'size' => '6 x 6',
            'color' => '',
            'preview_path' => 'previews/'.$owner->public_id.'/old.jpg',
            'images' => [[
                'name' => 'old.jpg',
                'path' => 'previews/'.$owner->public_id.'/old.jpg',
            ]],
            'stitch_path' => 'stitch/'.$owner->public_id.'/old.emb',
            'file_name' => 'old.emb',
            'files' => [[
                'name' => 'old.emb',
                'path' => 'stitch/'.$owner->public_id.'/old.emb',
            ]],
        ]);
        $design->variants()->create([
            'design_type_id' => $type->id,
            'name_or_number' => 'Old pallu',
            'stitch_count' => 10000,
            'needle' => 10,
            'area' => 36,
            'height' => 6,
            'width' => 6,
        ]);

        $this->actingAs($admin)
            ->get('/admin/designs/'.$design->public_id.'/edit')
            ->assertOk();

        $this->actingAs($admin)->put('/admin/designs/'.$design->public_id, [
            'ownerId' => $owner->public_id,
            'machineId' => $machine->public_id,
            'machineTypeId' => $machineType->public_id,
            'categoryId' => $category->public_id,
            'designFileTypeId' => $fileType->public_id,
            'priceRupees' => '349.00',
            'sequenceType' => 7.25,
            'variants' => [[
                'typeId' => $secondType->public_id,
                'nameOrNumber' => 'New border',
                'stitchCount' => 8000,
                'needle' => 8,
                'area' => 36,
                'height' => 3,
                'width' => 12,
            ]],
            'previewImages' => [
                UploadedFile::fake()->image('new-front.jpg'),
                UploadedFile::fake()->image('new-back.webp'),
            ],
            'stitchFiles' => [
                UploadedFile::fake()->create('new-border.dst', 100, 'application/octet-stream'),
                UploadedFile::fake()->create('new-border.emb', 100, 'application/octet-stream'),
            ],
        ])->assertRedirect(route('admin.designs.index'));

        $this->assertDatabaseMissing('design_variants', [
            'design_id' => $design->id,
            'design_type_id' => $type->id,
        ]);
        $this->assertDatabaseHas('design_variants', [
            'design_id' => $design->id,
            'design_type_id' => $secondType->id,
            'name_or_number' => 'New border',
        ]);
        $design->refresh();
        $this->assertSame(['new-front.jpg', 'new-back.webp'], collect($design->images)->pluck('name')->all());
        $this->assertSame($design->images[0]['path'], $design->preview_path);
        $this->assertSame(['new-border.dst', 'new-border.emb'], collect($design->files)->pluck('name')->all());
        $this->assertSame('new-border.dst', $design->file_name);
    }

    /** @return array{Machine, MachineType, DesignCategory, DesignType, DesignFileType} */
    private function catalog(): array
    {
        $machine = Machine::query()->create(['name' => 'Embroidery']);
        $machineType = MachineType::query()->create([
            'machine_id' => $machine->id,
            'name' => 'Multi Head',
        ]);
        $category = DesignCategory::query()->create(['name' => 'Saree']);
        $type = DesignType::query()->create([
            'design_category_id' => $category->id,
            'name' => 'Pallu',
        ]);
        $machineType->categories()->attach($category);
        $fileType = DesignFileType::query()->create([
            'name' => 'DST',
            'extension' => 'dst',
        ]);
        $fileType->machines()->attach($machine);

        return [$machine, $machineType, $category, $type, $fileType];
    }
}
