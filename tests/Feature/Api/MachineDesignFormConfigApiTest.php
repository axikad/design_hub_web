<?php

namespace Tests\Feature\Api;

use App\Models\Design;
use App\Models\Machine;
use App\Models\User;
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
            ->assertJsonPath('data.0.designFormConfig.category', false)
            ->assertJsonPath('data.0.designFormConfig.designTypes', false)
            ->assertJsonPath('data.0.designFormConfig.designSpecifications', false)
            ->assertJsonPath('data.0.designFormConfig.stitchFiles', false)
            ->assertJsonPath('data.0.designFormConfig.designFileType', false)
            ->assertJsonPath('data.0.designFormConfig.previewImages', true);
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
            'title' => 'Minimal design',
        ])
            ->assertCreated()
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
}
