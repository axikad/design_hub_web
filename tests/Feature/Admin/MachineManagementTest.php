<?php

namespace Tests\Feature\Admin;

use App\Models\DesignCategory;
use App\Models\Machine;
use App\Models\MachineType;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
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
        ])->assertRedirect(route('admin.machines.index'));

        $this->assertDatabaseHas('machines', [
            'name' => 'Embroidery',
            'is_active' => true,
            'sort_order' => 1,
        ]);
    }

    public function test_admin_can_create_a_machine_type_and_map_categories(): void
    {
        $admin = User::factory()->superAdmin()->create();
        $machine = Machine::query()->create(['name' => 'Embroidery']);
        $category = DesignCategory::query()->create(['name' => 'Neckline']);

        $this->actingAs($admin)->post('/admin/machine-types', [
            'machineId' => $machine->public_id,
            'name' => 'Multi-head',
            'categoryIds' => [$category->public_id],
            'isActive' => true,
            'sortOrder' => 1,
        ])->assertRedirect(route('admin.machine-types.index'));

        $machineType = MachineType::query()->where('name', 'Multi-head')->firstOrFail();

        $this->assertSame($machine->id, $machineType->machine_id);
        $this->assertTrue($machineType->categories()->whereKey($category->id)->exists());
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
