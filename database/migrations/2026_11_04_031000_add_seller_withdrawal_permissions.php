<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        $now = now();

        DB::table('modules')->updateOrInsert(
            ['slug' => 'withdrawals'],
            [
                'name' => 'Seller Withdrawals',
                'description' => 'Request and manage seller withdrawals.',
                'is_active' => true,
                'sort_order' => 119,
                'updated_at' => $now,
                'created_at' => $now,
            ],
        );

        $moduleId = DB::table('modules')->where('slug', 'withdrawals')->value('id');
        $permissions = [
            'withdrawals.view_own' => ['View Own Withdrawals', 'view_own'],
            'withdrawals.create_own' => ['Create Own Withdrawals', 'create_own'],
            'withdrawals.cancel_own' => ['Cancel Own Withdrawals', 'cancel_own'],
            'withdrawals.view_any' => ['View Any Withdrawal', 'view_any'],
            'withdrawals.manage' => ['Manage Withdrawals', 'manage'],
        ];

        foreach ($permissions as $slug => [$name, $action]) {
            DB::table('permissions')->updateOrInsert(
                ['slug' => $slug],
                [
                    'module_id' => $moduleId,
                    'name' => $name,
                    'action' => $action,
                    'description' => $name.'.',
                    'is_active' => true,
                    'updated_at' => $now,
                    'created_at' => $now,
                ],
            );
        }

        $sellerPermissionIds = DB::table('permissions')
            ->whereIn('slug', [
                'withdrawals.view_own',
                'withdrawals.create_own',
                'withdrawals.cancel_own',
            ])
            ->pluck('id');
        $sellerRoleIds = DB::table('roles')
            ->whereIn('slug', ['super_admin', 'marketplace_user'])
            ->pluck('id');

        foreach ($sellerRoleIds as $roleId) {
            foreach ($sellerPermissionIds as $permissionId) {
                DB::table('permission_role')->insertOrIgnore([
                    'role_id' => $roleId,
                    'permission_id' => $permissionId,
                    'assigned_by' => null,
                    'created_at' => $now,
                    'updated_at' => $now,
                ]);
            }
        }

        $adminPermissionIds = DB::table('permissions')
            ->whereIn('slug', ['withdrawals.view_any', 'withdrawals.manage'])
            ->pluck('id');
        $superAdminIds = DB::table('roles')->where('slug', 'super_admin')->pluck('id');

        foreach ($superAdminIds as $roleId) {
            foreach ($adminPermissionIds as $permissionId) {
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
        $permissionIds = DB::table('permissions')
            ->where('module_id', DB::table('modules')->where('slug', 'withdrawals')->value('id'))
            ->pluck('id');

        DB::table('permission_role')->whereIn('permission_id', $permissionIds)->delete();
        DB::table('permissions')->whereIn('id', $permissionIds)->delete();
        DB::table('modules')->where('slug', 'withdrawals')->delete();
    }
};
