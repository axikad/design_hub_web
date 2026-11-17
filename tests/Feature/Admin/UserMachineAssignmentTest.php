<?php

namespace Tests\Feature\Admin;

use App\Models\Machine;
use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class UserMachineAssignmentTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_can_assign_machines_when_creating_and_editing_a_marketplace_user(): void
    {
        $admin = User::factory()->superAdmin()->create();
        $role = Role::query()->where('slug', 'marketplace_user')->firstOrFail();
        $embroidery = Machine::query()->create(['name' => 'Embroidery', 'is_active' => true, 'sort_order' => 1]);
        $sketch = Machine::query()->create(['name' => 'Sketch Images', 'is_active' => true, 'sort_order' => 2]);

        $this->actingAs($admin)->post(route('admin.users.store'), [
            'name' => 'Marketplace Seller',
            'roleId' => $role->id,
            'email' => null,
            'password' => null,
            'password_confirmation' => null,
            'countryCode' => '+91',
            'mobileNumber' => '9876543210',
            'status' => 'active',
            'machineIds' => [$embroidery->public_id],
        ])->assertRedirect(route('admin.users.index'));

        $user = User::query()->where('phone_key', '+919876543210')->firstOrFail();
        $this->assertDatabaseHas('machine_user', ['user_id' => $user->id, 'machine_id' => $embroidery->id]);

        $this->actingAs($admin)->get(route('admin.users.edit', $user->public_id))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('admin/users/form')
                ->where('user.machineIds', [$embroidery->public_id])
                ->has('machines', 2));

        $this->actingAs($admin)->put(route('admin.users.update', $user->public_id), [
            'name' => 'Marketplace Seller',
            'roleId' => $role->id,
            'email' => null,
            'password' => null,
            'password_confirmation' => null,
            'countryCode' => '+91',
            'mobileNumber' => '9876543210',
            'status' => 'active',
            'machineIds' => [$sketch->public_id],
        ])->assertRedirect(route('admin.users.index'));

        $this->assertDatabaseMissing('machine_user', ['user_id' => $user->id, 'machine_id' => $embroidery->id]);
        $this->assertDatabaseHas('machine_user', ['user_id' => $user->id, 'machine_id' => $sketch->id]);
    }

    public function test_marketplace_user_requires_at_least_one_active_machine(): void
    {
        $admin = User::factory()->superAdmin()->create();
        $role = Role::query()->where('slug', 'marketplace_user')->firstOrFail();

        $this->actingAs($admin)->post(route('admin.users.store'), [
            'name' => 'Marketplace Seller',
            'roleId' => $role->id,
            'countryCode' => '+91',
            'mobileNumber' => '9876543210',
            'status' => 'active',
            'machineIds' => [],
        ])->assertSessionHasErrors('machineIds');
    }

    public function test_admin_must_enable_at_least_one_user_login_method(): void
    {
        $admin = User::factory()->superAdmin()->create();
        $role = Role::query()->where('slug', 'marketplace_user')->firstOrFail();
        $machine = Machine::query()->create(['name' => 'Embroidery', 'is_active' => true]);

        $this->actingAs($admin)->post(route('admin.users.store'), [
            'name' => 'Marketplace Seller',
            'roleId' => $role->id,
            'countryCode' => '+91',
            'mobileNumber' => '9876543210',
            'allowOtpLogin' => false,
            'allowPasswordLogin' => false,
            'status' => 'active',
            'machineIds' => [$machine->public_id],
        ])->assertSessionHasErrors('allowOtpLogin');
    }

    public function test_admin_receives_validation_error_for_a_duplicate_mobile_number(): void
    {
        $admin = User::factory()->superAdmin()->create();
        $role = Role::query()->where('slug', 'marketplace_user')->firstOrFail();
        $machine = Machine::query()->create(['name' => 'Embroidery', 'is_active' => true]);
        User::factory()->create([
            'country_code' => '+91',
            'mobile_number' => '9876543210',
            'phone_key' => '+919876543210',
        ]);

        $this->actingAs($admin)->post(route('admin.users.store'), [
            'name' => 'Duplicate Mobile Seller',
            'roleId' => $role->id,
            'countryCode' => '+91',
            'mobileNumber' => '98765 43210',
            'status' => 'active',
            'machineIds' => [$machine->public_id],
        ])->assertSessionHasErrors([
            'mobileNumber' => 'This mobile number is already registered.',
        ]);

        $this->assertDatabaseMissing('users', ['name' => 'Duplicate Mobile Seller']);
    }
}
