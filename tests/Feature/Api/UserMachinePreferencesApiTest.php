<?php

namespace Tests\Feature\Api;

use App\Models\Design;
use App\Models\Machine;
use App\Models\MachineType;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class UserMachinePreferencesApiTest extends TestCase
{
    use RefreshDatabase;

    public function test_user_can_list_and_update_selected_machines(): void
    {
        $embroidery = $this->machine('embroidery', 'Embroidery', 1);
        $airJet = $this->machine('air-jet-loom', 'Air Jet Loom', 2);
        $user = User::factory()->create();
        Sanctum::actingAs($user);

        $this->getJson('/api/v1/users/me/machines')
            ->assertOk()
            ->assertJsonPath('data.machineFilteringActive', false)
            ->assertJsonPath('data.selectedMachineIds', [])
            ->assertJsonCount(2, 'data.machines');

        $this->putJson('/api/v1/users/me/machines', [
            'machineIds' => [$airJet->public_id],
        ])
            ->assertOk()
            ->assertJsonPath('message', 'Machine preferences updated successfully.')
            ->assertJsonPath('data.machineFilteringActive', true)
            ->assertJsonPath('data.selectedMachineIds.0', 'air-jet-loom')
            ->assertJsonPath('data.machines.0.selected', false)
            ->assertJsonPath('data.machines.1.selected', true);

        $this->assertDatabaseHas('machine_user', [
            'user_id' => $user->id,
            'machine_id' => $airJet->id,
        ]);

        $this->getJson('/api/v1/users/me')
            ->assertOk()
            ->assertJsonPath('data.machineFilteringActive', true)
            ->assertJsonPath('data.selectedMachineIds.0', 'air-jet-loom')
            ->assertJsonPath('data.selectedMachines.0.id', 'air-jet-loom');

        $this->getJson('/api/v1/catalog/machines')
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.id', 'air-jet-loom');

        $this->getJson('/api/v1/catalog/design-options')
            ->assertOk()
            ->assertJsonCount(1, 'data.machines')
            ->assertJsonPath('data.machines.0.id', 'air-jet-loom');
    }

    public function test_selected_machines_filter_design_listing(): void
    {
        $embroidery = $this->machine('embroidery', 'Embroidery', 1);
        $airJet = $this->machine('air-jet-loom', 'Air Jet Loom', 2);
        $embroideryType = $this->machineType($embroidery, 'multi-head', 'Multi Head');
        $airJetType = $this->machineType($airJet, 'dobby-air-jet', 'Dobby Air Jet');
        $owner = User::factory()->create();
        $viewer = User::factory()->create();
        $viewer->machines()->attach($embroidery);
        Sanctum::actingAs($viewer);

        $visible = $this->design($owner, $embroideryType, 'Embroidery design');
        $this->design($owner, $airJetType, 'Air jet design');

        $this->getJson('/api/v1/designs')
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.id', $visible->public_id);

        $this->getJson('/api/v1/home')
            ->assertOk()
            ->assertJsonPath('data.latest.total', 1)
            ->assertJsonPath('data.latest.items.0.id', $visible->public_id);
    }

    public function test_inactive_or_unknown_machine_cannot_be_selected(): void
    {
        $inactive = $this->machine('inactive-machine', 'Inactive', 1, false);
        Sanctum::actingAs(User::factory()->create());

        $this->putJson('/api/v1/users/me/machines', [
            'machineIds' => [$inactive->public_id, 'missing-machine'],
        ])
            ->assertUnprocessable()
            ->assertJsonPath('code', 'validation_error')
            ->assertJsonStructure(['errors' => ['machineIds.0', 'machineIds.1']]);
    }

    private function machine(string $publicId, string $name, int $sortOrder, bool $active = true): Machine
    {
        return Machine::query()->create([
            'public_id' => $publicId,
            'name' => $name,
            'sort_order' => $sortOrder,
            'is_active' => $active,
        ]);
    }

    private function machineType(Machine $machine, string $publicId, string $name): MachineType
    {
        return MachineType::query()->create([
            'machine_id' => $machine->id,
            'public_id' => $publicId,
            'name' => $name,
            'sort_order' => 1,
            'is_active' => true,
        ]);
    }

    private function design(User $owner, MachineType $machineType, string $title): Design
    {
        return Design::query()->create([
            'user_id' => $owner->id,
            'machine_type_id' => $machineType->public_id,
            'title' => $title,
            'price_paise' => 1000,
            'size' => '6 x 6',
            'color' => '',
            'publish_date' => now(config('designhub.marketplace_timezone'))->toDateString(),
        ]);
    }
}
