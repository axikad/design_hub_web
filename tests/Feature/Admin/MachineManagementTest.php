<?php

namespace Tests\Feature\Admin;

use App\Models\DesignCategory;
use App\Models\Machine;
use App\Models\MachineType;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Inertia\Testing\AssertableInertia as Assert;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class MachineManagementTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_can_create_a_machine(): void
    {
        $admin = User::factory()->superAdmin()->create();

        $this->actingAs($admin)->post('/admin/machines', [
            'name' => 'Embroidery',
            'isActive' => true,
            'sortOrder' => 1,
            'watermarkConfig' => [
                'enabled' => true,
                'text' => 'Protected DesignHub',
                'opacity' => 30,
                'textSize' => 135,
                'spacing' => 160,
            ],
            'designFormConfig' => [
                'machineType' => false,
                'category' => true,
                'designTypes' => true,
                'designSpecifications' => true,
                'sequenceType' => false,
                'designFileType' => true,
                'previewImages' => true,
                'stitchFiles' => false,
                'pricing' => true,
                'required' => [
                    'machineType' => false,
                    'category' => true,
                    'designTypes' => true,
                    'designSpecifications' => false,
                    'sequenceType' => false,
                    'designFileType' => true,
                    'previewImages' => false,
                    'stitchFiles' => false,
                    'pricing' => true,
                ],
                'specificationFields' => [
                    'nameOrNumber' => true,
                    'stitchCount' => true,
                    'needle' => false,
                    'area' => true,
                    'height' => true,
                    'width' => true,
                    'required' => [
                        'nameOrNumber' => true,
                        'stitchCount' => false,
                        'needle' => false,
                        'area' => false,
                        'height' => true,
                        'width' => true,
                    ],
                ],
            ],
        ])->assertRedirect(route('admin.machines.index'));

        $this->assertDatabaseHas('machines', [
            'name' => 'Embroidery',
            'is_active' => true,
            'sort_order' => 1,
        ]);

        $config = Machine::query()->where('name', 'Embroidery')->firstOrFail()->normalizedDesignFormConfig();
        $watermark = Machine::query()->where('name', 'Embroidery')->firstOrFail()->normalizedWatermarkConfig();
        $this->assertFalse($config['machineType']);
        $this->assertTrue($config['category']);
        $this->assertTrue($config['designTypes']);
        $this->assertTrue($config['designSpecifications']);
        $this->assertFalse($config['stitchFiles']);
        $this->assertTrue($config['designFileType']);
        $this->assertTrue($config['previewImages']);
        $this->assertTrue($config['required']['designSpecifications']);
        $this->assertFalse($config['required']['previewImages']);
        $this->assertTrue($config['required']['designFileType']);
        $this->assertFalse($config['specificationFields']['needle']);
        $this->assertFalse($config['specificationFields']['required']['stitchCount']);
        $this->assertTrue($config['specificationFields']['required']['height']);
        $this->assertTrue($watermark['enabled']);
        $this->assertSame('Protected DesignHub', $watermark['text']);
        $this->assertSame(30, $watermark['opacity']);
        $this->assertSame(135, $watermark['textSize']);
        $this->assertSame(160, $watermark['spacing']);
    }

    public function test_admin_can_upload_remove_and_expose_a_machine_image_in_the_catalog_api(): void
    {
        Storage::fake('public');
        $admin = User::factory()->superAdmin()->create();

        $this->actingAs($admin)->post('/admin/machines', [
            'name' => 'Embroidery',
            'image' => UploadedFile::fake()->image('embroidery.png', 400, 400),
            'isActive' => true,
            'sortOrder' => 1,
        ])->assertRedirect(route('admin.machines.index'));

        $machine = Machine::query()->where('name', 'Embroidery')->firstOrFail();
        $this->assertNotNull($machine->image_path);
        Storage::disk('public')->assertExists($machine->image_path);

        Sanctum::actingAs($admin);
        $this->getJson('/api/v1/catalog/machines')
            ->assertOk()
            ->assertJsonPath('data.0.imageUri', Storage::disk('public')->url($machine->image_path));

        $oldPath = $machine->image_path;
        $this->actingAs($admin)->post('/admin/machines/'.$machine->public_id, [
            '_method' => 'put',
            'name' => $machine->name,
            'removeImage' => true,
            'isActive' => true,
            'sortOrder' => 1,
        ])->assertRedirect(route('admin.machines.index'));

        $this->assertNull($machine->fresh()->image_path);
        Storage::disk('public')->assertMissing($oldPath);
    }

    public function test_admin_can_create_a_machine_type_and_map_categories(): void
    {
        $admin = User::factory()->superAdmin()->create();
        $machine = Machine::query()->create(['name' => 'Embroidery']);
        $neckline = DesignCategory::query()->create(['name' => 'Neckline', 'sort_order' => 1]);
        $saree = DesignCategory::query()->create(['name' => 'Saree', 'sort_order' => 2]);

        $this->actingAs($admin)->post('/admin/machine-types', [
            'machineId' => $machine->public_id,
            'name' => 'Multi-head',
            'categoryIds' => [$saree->public_id, $neckline->public_id],
            'isActive' => true,
            'sortOrder' => 1,
        ])->assertRedirect(route('admin.machine-types.index'));

        $this->actingAs($admin)->post('/admin/machine-types', [
            'machineId' => $machine->public_id,
            'name' => 'Single-head',
            'categoryIds' => [$neckline->public_id, $saree->public_id],
            'isActive' => true,
            'sortOrder' => 2,
        ])->assertRedirect(route('admin.machine-types.index'));

        $multiHead = MachineType::query()->where('name', 'Multi-head')->firstOrFail();
        $singleHead = MachineType::query()->where('name', 'Single-head')->firstOrFail();

        $this->assertSame($machine->id, $multiHead->machine_id);
        $this->assertSame([$saree->id, $neckline->id], $multiHead->categories()->pluck('design_categories.id')->all());
        $this->assertSame([$neckline->id, $saree->id], $singleHead->categories()->pluck('design_categories.id')->all());

        $this->assertDatabaseHas('category_machine_type', [
            'machine_type_id' => $multiHead->id,
            'design_category_id' => $saree->id,
            'sort_order' => 1,
        ]);

        Sanctum::actingAs($admin);
        $this->getJson('/api/v1/catalog/machine-types/'.$multiHead->public_id.'/categories')
            ->assertOk()
            ->assertJsonPath('data.0.id', $saree->public_id)
            ->assertJsonPath('data.1.id', $neckline->public_id);
        $this->getJson('/api/v1/catalog/machine-types/'.$singleHead->public_id.'/categories')
            ->assertOk()
            ->assertJsonPath('data.0.id', $neckline->public_id)
            ->assertJsonPath('data.1.id', $saree->public_id);
    }

    public function test_machine_with_types_cannot_be_deleted(): void
    {
        $admin = User::factory()->superAdmin()->create();
        $machine = Machine::query()->create(['name' => 'Embroidery']);
        MachineType::query()->create(['machine_id' => $machine->id, 'name' => 'Multi-head']);

        $this->actingAs($admin)
            ->delete('/admin/machines/'.$machine->public_id)
            ->assertSessionHasErrors('machine');

        $this->assertDatabaseHas('machines', ['id' => $machine->id]);
    }

    public function test_inactive_categories_are_hidden_from_machine_type_screens(): void
    {
        $admin = User::factory()->superAdmin()->create();
        $machine = Machine::query()->create(['name' => 'Embroidery']);
        $machineType = MachineType::query()->create([
            'machine_id' => $machine->id,
            'name' => 'Multi-head',
        ]);
        $activeCategory = DesignCategory::query()->create(['name' => 'Saree', 'is_active' => true]);
        $inactiveCategory = DesignCategory::query()->create(['name' => 'Hidden category', 'is_active' => false]);
        $machineType->categories()->attach([$activeCategory->id, $inactiveCategory->id]);

        $this->actingAs($admin)
            ->get('/admin/machine-types')
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->where('machineTypes.data.0.categoryNames', ['Saree']));

        $this->actingAs($admin)
            ->get('/admin/machine-types/'.$machineType->public_id.'/edit')
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->where('machineType.categoryIds', [$activeCategory->public_id])
                ->has('categories', 1)
                ->where('categories.0.id', $activeCategory->public_id));
    }
}
