<?php

namespace Tests\Feature\Admin;

use App\Models\DesignCategory;
use App\Models\Machine;
use App\Models\MachineType;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CategoryManagementTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_can_create_and_update_category_machine_type_compatibility(): void
    {
        $admin = User::factory()->superAdmin()->create();
        $machine = Machine::query()->create(['name' => 'Embroidery']);
        $firstType = MachineType::query()->create(['machine_id' => $machine->id, 'name' => 'Multi Head']);
        $secondType = MachineType::query()->create(['machine_id' => $machine->id, 'name' => 'Sequin']);

        $this->actingAs($admin)->post('/admin/categories', [
            'name' => 'Saree',
            'description' => 'Saree designs',
            'isActive' => true,
            'sortOrder' => 1,
            'machineTypeIds' => [$firstType->public_id, $secondType->public_id],
        ])->assertRedirect(route('admin.categories.index'));

        $category = DesignCategory::query()->where('name', 'Saree')->firstOrFail();
        $this->assertEqualsCanonicalizing(
            [$firstType->id, $secondType->id],
            $category->machineTypes()->pluck('machine_types.id')->all(),
        );

        $this->actingAs($admin)->put('/admin/categories/'.$category->public_id, [
            'name' => 'Saree',
            'description' => 'Updated',
            'isActive' => true,
            'sortOrder' => 1,
            'machineTypeIds' => [$secondType->public_id],
        ])->assertRedirect(route('admin.categories.index'));

        $this->assertSame([$secondType->id], $category->machineTypes()->pluck('machine_types.id')->all());
    }

    public function test_admin_can_delete_category_connected_to_multiple_machine_types(): void
    {
        $admin = User::factory()->superAdmin()->create();
        $machine = Machine::query()->create(['name' => 'Embroidery']);
        $firstType = MachineType::query()->create(['machine_id' => $machine->id, 'name' => 'Multi Head']);
        $secondType = MachineType::query()->create(['machine_id' => $machine->id, 'name' => 'Sequin']);
        $category = DesignCategory::query()->create(['name' => 'Temporary category']);
        $category->machineTypes()->attach([$firstType->id, $secondType->id]);

        $this->actingAs($admin)
            ->delete('/admin/categories/'.$category->public_id)
            ->assertRedirect(route('admin.categories.index'));

        $this->assertDatabaseMissing('design_categories', ['id' => $category->id]);
        $this->assertDatabaseMissing('category_machine_type', ['design_category_id' => $category->id]);
    }
}
