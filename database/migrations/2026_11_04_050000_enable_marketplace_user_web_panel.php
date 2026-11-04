<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        $now = now();

        DB::table('modules')->updateOrInsert(
            ['slug' => 'marketplace'],
            [
                'name' => 'Marketplace Panel',
                'description' => 'Access marketplace content through the web panel with owner-only data scoping.',
                'is_active' => true,
                'sort_order' => 39,
                'created_at' => $now,
                'updated_at' => $now,
            ],
        );

        $moduleId = DB::table('modules')->where('slug', 'marketplace')->value('id');
        DB::table('permissions')->updateOrInsert(
            ['slug' => 'marketplace.manage_own'],
            [
                'module_id' => $moduleId,
                'name' => 'Manage Own Marketplace Content',
                'action' => 'manage_own',
                'description' => 'Restricts dashboard, design and folder data to the authenticated owner.',
                'is_active' => true,
                'created_at' => $now,
                'updated_at' => $now,
            ],
        );

        $roleIds = DB::table('roles')
            ->where('slug', 'marketplace_user')
            ->pluck('id')
            ->merge(DB::table('site_settings')->whereNotNull('default_mobile_registration_role_id')->pluck('default_mobile_registration_role_id'))
            ->unique();
        $permissionIds = DB::table('permissions')
            ->whereIn('slug', [
                'marketplace.manage_own',
                'dashboard.read',
                'designs.read',
                'designs.create',
                'designs.update',
                'designs.delete',
                'folders.read',
                'folders.create',
                'folders.update',
                'folders.delete',
            ])
            ->pluck('id');

        foreach ($roleIds as $roleId) {
            foreach ($permissionIds as $permissionId) {
                DB::table('permission_role')->insertOrIgnore([
                    'role_id' => $roleId,
                    'permission_id' => $permissionId,
                    'assigned_by' => null,
                    'created_at' => $now,
                    'updated_at' => $now,
                ]);
            }
        }
    }

    public function down(): void
    {
        $roleIds = DB::table('roles')
            ->where('slug', 'marketplace_user')
            ->pluck('id')
            ->merge(DB::table('site_settings')->whereNotNull('default_mobile_registration_role_id')->pluck('default_mobile_registration_role_id'))
            ->unique();
        $addedPermissionIds = DB::table('permissions')
            ->whereIn('slug', [
                'dashboard.read',
                'folders.read',
                'folders.create',
                'folders.update',
                'folders.delete',
            ])
            ->pluck('id');

        DB::table('permission_role')
            ->whereIn('role_id', $roleIds)
            ->whereIn('permission_id', $addedPermissionIds)
            ->delete();

        $permissionId = DB::table('permissions')->where('slug', 'marketplace.manage_own')->value('id');

        if ($permissionId !== null) {
            DB::table('permission_role')->where('permission_id', $permissionId)->delete();
            DB::table('permissions')->where('id', $permissionId)->delete();
        }

        DB::table('modules')->where('slug', 'marketplace')->delete();
    }
};
