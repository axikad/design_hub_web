<?php

namespace Database\Seeders;

use App\Models\Module;
use App\Models\Permission;
use App\Models\Role;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Collection;

class RoleAndPermissionSeeder extends Seeder
{
    public function run(): void
    {
        $roles = [
            'super_admin' => Role::query()->firstOrCreate(['slug' => 'super_admin'], ['name' => 'Super Admin']),
            'admin' => Role::query()->firstOrCreate(['slug' => 'admin'], ['name' => 'Admin']),
            'staff' => Role::query()->firstOrCreate(['slug' => 'staff'], ['name' => 'Staff']),
            'marketplace_user' => Role::query()->firstOrCreate(['slug' => 'marketplace_user'], ['name' => 'Marketplace User']),
        ];

        $catalog = [
            'dashboard' => ['name' => 'Dashboard', 'sortOrder' => 10, 'actions' => ['read']],
            'users' => ['name' => 'User Management', 'sortOrder' => 20, 'actions' => ['read', 'create', 'update', 'delete']],
            'roles' => ['name' => 'Roles & Permissions', 'sortOrder' => 30, 'actions' => ['read', 'create', 'update', 'delete', 'assign_permissions']],
            'designs' => ['name' => 'Design Management', 'sortOrder' => 40, 'actions' => ['read', 'create', 'update', 'delete']],
            'machines' => ['name' => 'Machines', 'sortOrder' => 50, 'actions' => ['read', 'create', 'update', 'delete']],
            'machine_types' => ['name' => 'Machine Types', 'sortOrder' => 60, 'actions' => ['read', 'create', 'update', 'delete']],
            'categories' => ['name' => 'Categories', 'sortOrder' => 70, 'actions' => ['read', 'create', 'update', 'delete']],
            'design_types' => ['name' => 'Design Types', 'sortOrder' => 80, 'actions' => ['read', 'create', 'update', 'delete']],
            'design_file_types' => ['name' => 'Design File Types', 'sortOrder' => 90, 'actions' => ['read', 'create', 'update', 'delete']],
            'purchases' => ['name' => 'Purchases', 'sortOrder' => 100, 'actions' => ['read', 'update', 'delete']],
            'branding' => ['name' => 'Site Branding', 'sortOrder' => 110, 'actions' => ['read', 'update']],
        ];

        $permissions = collect();

        foreach ($catalog as $moduleSlug => $moduleData) {
            $module = Module::query()->updateOrCreate(
                ['slug' => $moduleSlug],
                [
                    'name' => $moduleData['name'],
                    'is_active' => true,
                    'sort_order' => $moduleData['sortOrder'],
                ],
            );

            foreach ($moduleData['actions'] as $action) {
                $permission = Permission::query()->updateOrCreate(
                    ['slug' => "{$moduleSlug}.{$action}"],
                    [
                        'module_id' => $module->id,
                        'name' => str($action)->replace('_', ' ')->title()->append(' '.$moduleData['name'])->toString(),
                        'action' => $action,
                        'is_active' => true,
                    ],
                );

                $permissions->put($permission->slug, $permission);
            }
        }

        $this->syncPermissions($roles['super_admin'], $permissions->keys()->all(), $permissions);
        $this->syncPermissions($roles['admin'], [
            'dashboard.read',
            'users.read', 'users.create', 'users.update', 'users.delete',
            'roles.read',
            'designs.read', 'designs.create', 'designs.update', 'designs.delete',
            'machines.read', 'machines.create', 'machines.update', 'machines.delete',
            'machine_types.read', 'machine_types.create', 'machine_types.update', 'machine_types.delete',
            'categories.read', 'categories.create', 'categories.update', 'categories.delete',
            'design_types.read', 'design_types.create', 'design_types.update', 'design_types.delete',
            'design_file_types.read', 'design_file_types.create', 'design_file_types.update', 'design_file_types.delete',
            'purchases.read', 'purchases.update',
            'branding.read', 'branding.update',
        ], $permissions);
        $this->syncPermissions($roles['staff'], [
            'dashboard.read',
            'users.read',
            'designs.read',
            'machines.read',
            'machine_types.read',
            'categories.read',
            'design_types.read',
            'design_file_types.read',
            'purchases.read',
        ], $permissions);
        $this->syncPermissions($roles['marketplace_user'], [
            'designs.read', 'designs.create', 'designs.update', 'designs.delete',
        ], $permissions);

        User::query()
            ->where('email', 'admin@designhub.local')
            ->update([
                'role_id' => $roles['super_admin']->id,
            ]);
    }

    /**
     * @param  list<string>  $slugs
     * @param  Collection<string, Permission>  $permissions
     */
    private function syncPermissions(Role $role, array $slugs, Collection $permissions): void
    {
        $role->permissions()->sync(
            collect($slugs)
                ->map(fn (string $slug) => $permissions->get($slug)?->id)
                ->filter()
                ->values()
                ->all(),
        );
    }
}
