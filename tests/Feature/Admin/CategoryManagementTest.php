<?php

namespace Tests\Feature\Admin;

use App\Models\DesignCategory;
use App\Models\DesignType;
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

    public function test_category_positions_shift_and_can_be_reordered(): void
    {
        $admin = User::factory()->superAdmin()->create();
        $first = DesignCategory::query()->create(['name' => 'First', 'sort_order' => 1]);
        $second = DesignCategory::query()->create(['name' => 'Second', 'sort_order' => 2]);
        $third = DesignCategory::query()->create(['name' => 'Third', 'sort_order' => 3]);

        $this->actingAs($admin)->put('/admin/categories/'.$third->public_id, [
            'name' => 'Third',
            'isActive' => true,
            'sortOrder' => 1,
            'machineTypeIds' => [],
        ])->assertRedirect(route('admin.categories.index'));

        $this->assertSame(1, $third->fresh()->sort_order);
        $this->assertSame(2, $first->fresh()->sort_order);
        $this->assertSame(3, $second->fresh()->sort_order);

        $this->actingAs($admin)->put('/admin/categories/reorder', [
            'ids' => [$second->public_id, $third->public_id, $first->public_id],
            'startPosition' => 1,
        ])->assertRedirect();

        $this->assertSame(1, $second->fresh()->sort_order);
        $this->assertSame(2, $third->fresh()->sort_order);
        $this->assertSame(3, $first->fresh()->sort_order);

        $this->actingAs($admin)->post('/admin/categories', [
            'name' => 'Fourth',
            'isActive' => true,
            'machineTypeIds' => [],
        ])->assertRedirect(route('admin.categories.index'));

        $this->assertDatabaseHas('design_categories', ['name' => 'Fourth', 'sort_order' => 4]);
    }

    public function test_admin_can_select_and_order_design_types_per_category(): void
    {
        $admin = User::factory()->superAdmin()->create();
        $sourceCategory = DesignCategory::query()->create(['name' => 'Source']);
        $border = DesignType::query()->create([
            'design_category_id' => $sourceCategory->id,
            'name' => 'Border',
            'sort_order' => 1,
        ]);
        $pallu = DesignType::query()->create([
            'design_category_id' => $sourceCategory->id,
            'name' => 'Pallu',
            'sort_order' => 2,
        ]);

        $this->actingAs($admin)->post('/admin/categories', [
            'name' => 'Saree',
            'isActive' => true,
            'sortOrder' => 2,
            'machineTypeIds' => [],
            'designTypeIds' => [$pallu->public_id, $border->public_id],
        ])->assertRedirect(route('admin.categories.index'));

        $category = DesignCategory::query()->where('name', 'Saree')->firstOrFail();
        $this->assertSame(
            [$pallu->id, $border->id],
            $category->types()->pluck('design_types.id')->all(),
        );
        $this->assertDatabaseHas('category_design_type', [
            'design_category_id' => $category->id,
            'design_type_id' => $pallu->id,
            'sort_order' => 1,
        ]);

        $this->actingAs($admin)->put('/admin/categories/'.$category->public_id, [
            'name' => 'Saree',
            'isActive' => true,
            'sortOrder' => 2,
            'machineTypeIds' => [],
            'designTypeIds' => [$border->public_id, $pallu->public_id],
        ])->assertRedirect(route('admin.categories.index'));

        $this->assertSame(
            [$border->id, $pallu->id],
            $category->types()->pluck('design_types.id')->all(),
        );
    }
}
