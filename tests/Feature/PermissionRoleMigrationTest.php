<?php

namespace Tests\Feature;

use App\Models\Module;
use App\Models\Permission;
use App\Models\Role;
use App\Models\User;
use Database\Seeders\RoleAndPermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PermissionRoleMigrationTest extends TestCase
{
    use RefreshDatabase;

    public function test_role_and_permission_use_the_template_style_pivot(): void
    {
        $actor = User::factory()->create();
        $role = Role::query()->create(['name' => 'Editor', 'slug' => 'editor']);
        $module = Module::query()->create(['name' => 'Test Designs', 'slug' => 'test_designs']);
        $permission = Permission::query()->create([
            'module_id' => $module->id,
            'name' => 'Update Designs',
            'slug' => 'test_designs.update',
            'action' => 'update',
        ]);

        $role->permissions()->attach($permission->id, ['assigned_by' => $actor->id]);

        $this->assertTrue($role->permissions()->whereKey($permission->id)->exists());
        $this->assertTrue($permission->roles()->whereKey($role->id)->exists());
        $this->assertDatabaseHas('permission_role', [
            'role_id' => $role->id,
            'permission_id' => $permission->id,
            'assigned_by' => $actor->id,
        ]);
    }

    public function test_seeder_assigns_action_permissions_through_the_new_pivot(): void
    {
        $this->seed(RoleAndPermissionSeeder::class);

        $admin = Role::query()->where('slug', 'admin')->firstOrFail();

        $this->assertTrue($admin->permissions()->where('slug', 'users.read')->exists());
        $this->assertTrue($admin->permissions()->where('slug', 'users.delete')->exists());
        $this->assertFalse($admin->permissions()->where('slug', 'roles.create')->exists());

        $marketplaceUser = Role::query()->where('slug', 'marketplace_user')->firstOrFail();

        $this->assertEqualsCanonicalizing(
            ['designs.read', 'designs.create', 'designs.update', 'designs.delete'],
            $marketplaceUser->permissions()->pluck('slug')->all(),
        );
    }
}
