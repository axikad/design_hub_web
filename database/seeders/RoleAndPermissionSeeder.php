<?php

namespace Database\Seeders;

use App\Models\Module;
use App\Models\Permission;
use App\Models\Role;
use App\Models\RolePermission;
use App\Models\User;
use Illuminate\Database\Seeder;

class RoleAndPermissionSeeder extends Seeder
{
    public function run(): void
    {
        $superAdmin = Role::query()->firstOrCreate(
            ['slug' => 'super_admin'],
            ['name' => 'Super Admin'],
        );

        $admin = Role::query()->firstOrCreate(
            ['slug' => 'admin'],
            ['name' => 'Admin'],
        );

        $staff = Role::query()->firstOrCreate(
            ['slug' => 'staff'],
            ['name' => 'Staff'],
        );

        Role::query()->firstOrCreate(
            ['slug' => 'marketplace_user'],
            ['name' => 'Marketplace User'],
        );

        $catalog = [
            'Administration' => ['name' => 'Admin panel', 'slug' => 'admin_access'],
            'User Management' => ['name' => 'Users', 'slug' => 'user_access'],
            'Access Control' => ['name' => 'Roles & permissions', 'slug' => 'role_access'],
            'Design Management' => ['name' => 'Designs', 'slug' => 'design_access'],
            'Catalog' => ['name' => 'Categories', 'slug' => 'category_access'],
            'Catalog Types' => ['name' => 'Types', 'slug' => 'type_access'],
            'Commerce' => ['name' => 'Purchases', 'slug' => 'purchase_access'],
            'Configuration' => ['name' => 'Site settings', 'slug' => 'site_setting_access'],
        ];

        $permissions = [];

        foreach ($catalog as $moduleName => $permission) {
            $module = Module::query()->firstOrCreate(['name' => $moduleName]);

            $permissions[$permission['slug']] = Permission::query()->updateOrCreate(
                ['slug' => $permission['slug']],
                [
                    'module_id' => $module->id,
                    'name' => $permission['name'],
                ],
            );
        }

        $this->grantAll($superAdmin, $permissions);

        $this->grant($admin, $permissions, [
            'admin_access' => ['read' => true, 'create' => true, 'update' => true, 'delete' => true],
            'user_access' => ['read' => true, 'create' => true, 'update' => true, 'delete' => true],
            'design_access' => ['read' => true, 'create' => true, 'update' => true, 'delete' => true],
            'purchase_access' => ['read' => true, 'create' => false, 'update' => true, 'delete' => false],
            'role_access' => ['read' => true, 'create' => false, 'update' => false, 'delete' => false],
            'site_setting_access' => ['read' => true, 'create' => false, 'update' => true, 'delete' => false],
            'category_access' => ['read' => true, 'create' => true, 'update' => true, 'delete' => true],
            'type_access' => ['read' => true, 'create' => true, 'update' => true, 'delete' => true],
        ]);

        $this->grant($staff, $permissions, [
            'admin_access' => ['read' => true, 'create' => false, 'update' => false, 'delete' => false],
            'user_access' => ['read' => true, 'create' => false, 'update' => false, 'delete' => false],
            'design_access' => ['read' => true, 'create' => false, 'update' => false, 'delete' => false],
            'purchase_access' => ['read' => true, 'create' => false, 'update' => false, 'delete' => false],
            'category_access' => ['read' => true, 'create' => false, 'update' => false, 'delete' => false],
            'type_access' => ['read' => true, 'create' => false, 'update' => false, 'delete' => false],
        ]);

        User::query()
            ->where('email', 'admin@designhub.local')
            ->update([
                'role_id' => $superAdmin->id,
                'is_admin' => true,
            ]);
    }

    /** @param array<string, Permission> $permissions */
    private function grantAll(Role $role, array $permissions): void
    {
        foreach ($permissions as $permission) {
            RolePermission::query()->updateOrCreate(
                [
                    'role_id' => $role->id,
                    'permission_id' => $permission->id,
                ],
                [
                    'read' => true,
                    'create' => true,
                    'update' => true,
                    'delete' => true,
                ],
            );
        }
    }

    /**
     * @param array<string, Permission> $permissions
     * @param array<string, array{read?: bool, create?: bool, update?: bool, delete?: bool}> $matrix
     */
    private function grant(Role $role, array $permissions, array $matrix): void
    {
        foreach ($matrix as $slug => $flags) {
            if (! isset($permissions[$slug])) {
                continue;
            }

            RolePermission::query()->updateOrCreate(
                [
                    'role_id' => $role->id,
                    'permission_id' => $permissions[$slug]->id,
                ],
                [
                    'read' => $flags['read'] ?? false,
                    'create' => $flags['create'] ?? false,
                    'update' => $flags['update'] ?? false,
                    'delete' => $flags['delete'] ?? false,
                ],
            );
        }
    }
}
