<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        $catalog = [
            'dashboard' => ['legacy' => 'administration', 'name' => 'Dashboard', 'order' => 10, 'actions' => ['read']],
            'users' => ['legacy' => 'user_management', 'name' => 'User Management', 'order' => 20, 'actions' => ['read', 'create', 'update', 'delete']],
            'roles' => ['legacy' => 'access_control', 'name' => 'Roles & Permissions', 'order' => 30, 'actions' => ['read', 'create', 'update', 'delete', 'assign_permissions']],
            'designs' => ['legacy' => 'design_management', 'name' => 'Design Management', 'order' => 40, 'actions' => ['read', 'create', 'update', 'delete']],
            'machines' => ['legacy' => null, 'name' => 'Machines', 'order' => 50, 'actions' => ['read', 'create', 'update', 'delete']],
            'machine_types' => ['legacy' => null, 'name' => 'Machine Types', 'order' => 60, 'actions' => ['read', 'create', 'update', 'delete']],
            'categories' => ['legacy' => 'catalog', 'name' => 'Categories', 'order' => 70, 'actions' => ['read', 'create', 'update', 'delete']],
            'design_types' => ['legacy' => 'catalog_types', 'name' => 'Design Types', 'order' => 80, 'actions' => ['read', 'create', 'update', 'delete']],
            'design_file_types' => ['legacy' => null, 'name' => 'Design File Types', 'order' => 90, 'actions' => ['read', 'create', 'update', 'delete']],
            'purchases' => ['legacy' => 'commerce', 'name' => 'Purchases', 'order' => 100, 'actions' => ['read', 'update', 'delete']],
            'branding' => ['legacy' => 'configuration', 'name' => 'Site Branding', 'order' => 110, 'actions' => ['read', 'update']],
        ];

        $permissionIds = [];
        $now = now();

        foreach ($catalog as $moduleSlug => $moduleData) {
            $moduleId = DB::table('modules')->where('slug', $moduleSlug)->value('id');

            if ($moduleId === null && $moduleData['legacy']) {
                $moduleId = DB::table('modules')->where('slug', $moduleData['legacy'])->value('id');
            }

            if ($moduleId === null) {
                $moduleId = DB::table('modules')->insertGetId([
                    'name' => $moduleData['name'],
                    'slug' => $moduleSlug,
                    'is_active' => true,
                    'sort_order' => $moduleData['order'],
                    'created_at' => $now,
                    'updated_at' => $now,
                ]);
            } else {
                DB::table('modules')->where('id', $moduleId)->update([
                    'name' => $moduleData['name'],
                    'slug' => $moduleSlug,
                    'is_active' => true,
                    'sort_order' => $moduleData['order'],
                    'updated_at' => $now,
                ]);
            }

            foreach ($moduleData['actions'] as $action) {
                $slug = "{$moduleSlug}.{$action}";
                $permissionId = DB::table('permissions')->where('slug', $slug)->value('id');

                if ($permissionId === null) {
                    $permissionId = DB::table('permissions')->insertGetId([
                        'module_id' => $moduleId,
                        'name' => str($action)->replace('_', ' ')->title()->append(' '.$moduleData['name'])->toString(),
                        'slug' => $slug,
                        'action' => $action,
                        'is_active' => true,
                        'created_at' => $now,
                        'updated_at' => $now,
                    ]);
                } else {
                    DB::table('permissions')->where('id', $permissionId)->update([
                        'module_id' => $moduleId,
                        'action' => $action,
                        'is_active' => true,
                        'updated_at' => $now,
                    ]);
                }

                $permissionIds[$slug] = $permissionId;
            }
        }

        DB::table('permissions')
            ->where('description', 'like', 'Migrated from %.')
            ->whereNotIn('slug', array_keys($permissionIds))
            ->delete();

        $rolePolicies = [
            'super_admin' => array_keys($permissionIds),
            'admin' => [
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
            ],
            'staff' => [
                'dashboard.read', 'users.read', 'designs.read', 'machines.read',
                'machine_types.read', 'categories.read', 'design_types.read',
                'design_file_types.read', 'purchases.read',
            ],
        ];

        foreach ($rolePolicies as $roleSlug => $slugs) {
            $roleId = DB::table('roles')->where('slug', $roleSlug)->value('id');

            if ($roleId === null) {
                continue;
            }

            foreach ($slugs as $slug) {
                if (! isset($permissionIds[$slug])) {
                    continue;
                }

                DB::table('permission_role')->insertOrIgnore([
                    'role_id' => $roleId,
                    'permission_id' => $permissionIds[$slug],
                    'assigned_by' => null,
                    'created_at' => $now,
                    'updated_at' => $now,
                ]);
            }
        }

        $superAdminRoleId = DB::table('roles')->where('slug', 'super_admin')->value('id');

        if ($superAdminRoleId !== null) {
            DB::table('users')
                ->where('is_admin', true)
                ->whereNull('role_id')
                ->update(['role_id' => $superAdminRoleId]);
        }
    }

    public function down(): void
    {
        // Permission assignments are intentionally preserved on rollback to
        // avoid deleting access rules that administrators may have changed.
    }
};
