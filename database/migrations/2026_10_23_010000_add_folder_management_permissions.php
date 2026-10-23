<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        $now = now();

        DB::table('modules')->updateOrInsert(
            ['slug' => 'folders'],
            [
                'name' => 'Folder Management',
                'description' => 'Manage ZIP-based downloadable folders.',
                'is_active' => true,
                'sort_order' => 45,
                'updated_at' => $now,
                'created_at' => $now,
            ],
        );

        $moduleId = DB::table('modules')->where('slug', 'folders')->value('id');

        foreach (['read', 'create', 'update', 'delete'] as $action) {
            $slug = "folders.{$action}";

            DB::table('permissions')->updateOrInsert(
                ['slug' => $slug],
                [
                    'module_id' => $moduleId,
                    'name' => str($action)->title()->append(' Folder Management')->toString(),
                    'action' => $action,
                    'is_active' => true,
                    'updated_at' => $now,
                    'created_at' => $now,
                ],
            );

            $permissionId = DB::table('permissions')->where('slug', $slug)->value('id');
            $roleIds = DB::table('roles')
                ->whereIn('slug', $action === 'read' ? ['super_admin', 'admin', 'staff'] : ['super_admin', 'admin'])
                ->pluck('id');

            foreach ($roleIds as $roleId) {
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
        $permissionIds = DB::table('permissions')->where('slug', 'like', 'folders.%')->pluck('id');
        DB::table('permission_role')->whereIn('permission_id', $permissionIds)->delete();
        DB::table('permissions')->whereIn('id', $permissionIds)->delete();
        DB::table('modules')->where('slug', 'folders')->delete();
    }
};
