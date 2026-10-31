<?php

namespace Tests\Feature\Api;

use App\Models\Design;
use App\Models\DesignCategory;
use App\Models\DesignFileType;
use App\Models\DesignType;
use App\Models\Machine;
use App\Models\MachineType;
use App\Models\Upload;
use App\Models\User;
use App\Services\UploadService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class MachineDesignFormConfigApiTest extends TestCase
{
    use RefreshDatabase;

    public function test_catalog_returns_normalized_design_form_config(): void
    {
        $machine = Machine::query()->create([
            'public_id' => 'simple-machine',
            'name' => 'Simple Machine',
            'is_active' => true,
            'sort_order' => 1,
            'design_form_config' => [
                'machineType' => false,
                'stitchFiles' => false,
            ],
        ]);
        $user = User::factory()->create();
        $user->machines()->attach($machine);
        Sanctum::actingAs($user);

        $this->getJson('/api/v1/catalog/machines')
            ->assertOk()
            ->assertJsonPath('data.0.id', 'simple-machine')
            ->assertJsonPath('data.0.designFormConfig.machineType', false)
            ->assertJsonPath('data.0.designFormConfig.category', true)
            ->assertJsonPath('data.0.designFormConfig.designTypes', true)
            ->assertJsonPath('data.0.designFormConfig.designSpecifications', true)
            ->assertJsonPath('data.0.designFormConfig.stitchFiles', false)
            ->assertJsonPath('data.0.designFormConfig.designFileType', true)
            ->assertJsonPath('data.0.designFormConfig.previewImages', true)
            ->assertJsonPath('data.0.designFormConfig.required.machineType', false)
            ->assertJsonPath('data.0.designFormConfig.required.category', true)
            ->assertJsonPath('data.0.designFormConfig.required.stitchFiles', false)
            ->assertJsonPath('data.0.designFormConfig.required.previewImages', true)
            ->assertJsonPath('data.0.designFormConfig.specificationFields.height', true)
            ->assertJsonPath('data.0.designFormConfig.specificationFields.required.height', true);
    }

    public function test_visible_fields_can_be_optional_for_a_machine(): void
    {
        $machine = Machine::query()->create([
            'public_id' => 'optional-fields-machine',
            'name' => 'Optional Fields Machine',
            'is_active' => true,
            'sort_order' => 1,
            'design_form_config' => [
                'machineType' => true,
                'category' => true,
                'designTypes' => true,
                'designSpecifications' => true,
                'sequenceType' => true,
                'designFileType' => true,
                'previewImages' => true,
                'stitchFiles' => true,
                'pricing' => true,
                'required' => array_fill_keys([
                    'machineType',
                    'category',
                    'designTypes',
                    'designSpecifications',
                    'sequenceType',
                    'designFileType',
                    'previewImages',
                    'stitchFiles',
                    'pricing',
                ], false),
            ],
        ]);
        $user = User::factory()->create();
        Sanctum::actingAs($user);

        $this->postJson('/api/v1/designs', [
            'machineId' => $machine->public_id,
        ])
            ->assertCreated()
            ->assertJsonPath('data.machineId', $machine->public_id)
            ->assertJsonPath('data.machineTypeId', null)
            ->assertJsonPath('data.categoryId', null)
            ->assertJsonPath('data.pricePaise', 0)
            ->assertJsonCount(0, 'data.designTypes')
            ->assertJsonCount(0, 'data.images')
            ->assertJsonCount(0, 'data.files');
    }

    public function test_design_can_be_created_with_only_fields_enabled_for_its_machine(): void
    {
        $machine = Machine::query()->create([
            'public_id' => 'minimal-machine',
            'name' => 'Minimal Machine',
            'is_active' => true,
            'sort_order' => 1,
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
        $user = User::factory()->create();
        Sanctum::actingAs($user);

        $this->postJson('/api/v1/designs', [
            'machineId' => $machine->public_id,
        ])
            ->assertCreated()
            ->assertJsonPath('data.title', 'Untitled design')
            ->assertJsonPath('data.machineId', 'minimal-machine')
            ->assertJsonPath('data.machineTypeId', null)
            ->assertJsonPath('data.categoryId', null)
            ->assertJsonPath('data.designFileTypeId', null)
            ->assertJsonPath('data.pricePaise', 0)
            ->assertJsonPath('data.hasFile', false)
            ->assertJsonCount(0, 'data.designTypes')
            ->assertJsonCount(0, 'data.images')
            ->assertJsonCount(0, 'data.files');

        $design = Design::query()->firstOrFail();

        $this->assertSame($machine->id, $design->machine_id);
        $this->assertNull($design->machine_type_id);
        $this->assertNull($design->category_id);
        $this->assertNull($design->design_file_type_id);
        $this->assertNull($design->stitch_path);
    }

    public function test_design_without_specifications_uses_preview_filename_as_its_title(): void
    {
        $machine = Machine::query()->create([
            'public_id' => 'sketch-images',
            'name' => 'Sketch images',
            'is_active' => true,
            'sort_order' => 1,
            'design_form_config' => [
                'machineType' => false,
                'category' => false,
                'designTypes' => false,
                'designSpecifications' => false,
                'sequenceType' => false,
                'designFileType' => false,
                'previewImages' => true,
                'stitchFiles' => false,
                'pricing' => true,
            ],
        ]);
        $user = User::factory()->create();
        $preview = Upload::query()->create([
            'user_id' => $user->id,
            'disk' => 'public',
            'path' => 'previews/'.$user->public_id.'/1000000838.jpg',
            'kind' => 'photo',
            'original_name' => '1000000838.jpg',
            'mime' => 'image/jpeg',
            'size_bytes' => 100,
        ]);
        Sanctum::actingAs($user);

        $this->postJson('/api/v1/designs', [
            'machineId' => $machine->public_id,
            'pricePaise' => 39900,
            'images' => [[
                'name' => '1000000838.jpg',
                'uri' => app(UploadService::class)->publicUrl($preview),
            ]],
        ])
            ->assertCreated()
            ->assertJsonPath('data.title', '1000000838')
            ->assertJsonPath('data.pricePaise', 39900);

        $this->assertDatabaseHas('designs', [
            'machine_id' => $machine->id,
            'title' => '1000000838',
            'price_paise' => 39900,
        ]);
    }

    public function test_category_types_and_file_type_work_without_machine_type_or_stitch_file(): void
    {
        $machine = Machine::query()->create([
            'public_id' => 'category-machine',
            'name' => 'Category Machine',
            'is_active' => true,
            'sort_order' => 1,
            'design_form_config' => [
                'machineType' => false,
                'stitchFiles' => false,
                'specificationFields' => [
                    'nameOrNumber' => true,
                    'stitchCount' => false,
                    'needle' => true,
                    'area' => false,
                    'height' => true,
                    'width' => false,
                    'required' => [
                        'nameOrNumber' => true,
                        'stitchCount' => false,
                        'needle' => false,
                        'area' => false,
                        'height' => false,
                        'width' => false,
                    ],
                ],
            ],
        ]);
        $machineType = MachineType::query()->create([
            'machine_id' => $machine->id,
            'public_id' => 'internal-type',
            'name' => 'Internal Type',
            'is_active' => true,
            'sort_order' => 1,
        ]);
        $category = DesignCategory::query()->create([
            'public_id' => 'saree',
            'name' => 'Saree',
            'is_active' => true,
            'sort_order' => 1,
        ]);
        $category->machineTypes()->attach($machineType);
        $designType = DesignType::query()->create([
            'public_id' => 'pallu',
            'design_category_id' => $category->id,
            'name' => 'Pallu',
            'is_active' => true,
            'sort_order' => 1,
        ]);
        $fileType = DesignFileType::query()->create([
            'public_id' => 'dst',
            'name' => 'DST',
            'extension' => 'dst',
            'is_active' => true,
            'sort_order' => 1,
        ]);
        $fileType->machines()->attach($machine);
        $user = User::factory()->create();
        $preview = Upload::query()->create([
            'user_id' => $user->id,
            'disk' => 'public',
            'path' => 'previews/'.$user->public_id.'/preview.jpg',
            'kind' => 'photo',
            'original_name' => 'preview.jpg',
            'mime' => 'image/jpeg',
            'size_bytes' => 100,
        ]);
        Sanctum::actingAs($user);

        $this->getJson('/api/v1/catalog/machines/'.$machine->public_id.'/categories')
            ->assertOk()
            ->assertJsonPath('data.0.id', $category->public_id);

        $this->postJson('/api/v1/designs', [
            'machineId' => $machine->public_id,
            'categoryId' => $category->public_id,
            'designTypes' => [[
                'typeId' => $designType->public_id,
                'details' => [
                    'nameOrNumber' => 'Pallu 101',
                ],
            ]],
            'designFileTypeId' => $fileType->public_id,
            'pricePaise' => 1000,
            'images' => [[
                'name' => 'preview.jpg',
                'uri' => app(UploadService::class)->publicUrl($preview),
            ]],
        ])
            ->assertCreated()
            ->assertJsonPath('data.machineId', $machine->public_id)
            ->assertJsonPath('data.machineTypeId', null)
            ->assertJsonPath('data.categoryId', $category->public_id)
            ->assertJsonPath('data.designFileTypeId', $fileType->public_id)
            ->assertJsonPath('data.hasFile', false)
            ->assertJsonPath('data.designTypes.0.typeId', $designType->public_id);

        $variant = Design::query()->firstOrFail()->variants()->firstOrFail();
        $this->assertSame('Pallu 101', $variant->name_or_number);
        $this->assertSame(0, $variant->stitch_count);
        $this->assertSame(0, $variant->needle);
        $this->assertEquals(0, $variant->area);
        $this->assertEquals(0, $variant->height);
        $this->assertEquals(0, $variant->width);
    }
}
