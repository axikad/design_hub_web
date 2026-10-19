<?php

namespace Tests\Feature\Admin;

use App\Models\Permission;
use App\Models\Role;
use App\Models\User;
use Database\Seeders\RoleAndPermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PermissionAuthorizationTest extends TestCase
{
    use RefreshDatabase;

    public function test_staff_role_cannot_bypass_action_permissions(): void
    {
        $this->seed(RoleAndPermissionSeeder::class);
        $staff = Role::query()->where('slug', 'staff')->firstOrFail();
        $user = User::factory()->create(['role_id' => $staff->id]);

        $this->actingAs($user)->get(route('admin.users.index'))->assertOk();
        $this->actingAs($user)->get(route('admin.users.create'))->assertForbidden();
    }

    public function test_super_admin_can_sync_permissions_through_the_new_pivot(): void
    {
        $this->seed(RoleAndPermissionSeeder::class);
        $superAdminRole = Role::query()->where('slug', 'super_admin')->firstOrFail();
        $adminRole = Role::query()->where('slug', 'admin')->firstOrFail();
        $superAdmin = User::factory()->create(['role_id' => $superAdminRole->id]);
        $permission = Permission::query()->where('slug', 'users.read')->firstOrFail();

        $this->actingAs($superAdmin)
            ->put(route('admin.roles.permissions.update', $adminRole), [
                'permissionIds' => [$permission->id],
            ])
            ->assertRedirect(route('admin.roles.index'));

        $this->assertDatabaseHas('permission_role', [
            'role_id' => $adminRole->id,
            'permission_id' => $permission->id,
            'assigned_by' => $superAdmin->id,
        ]);
        $this->assertSame(1, $adminRole->permissions()->count());
    }
}
