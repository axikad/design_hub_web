<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        $now = now();
        $modules = [
            'wallet' => ['name' => 'Seller Wallet', 'sort_order' => 116],
            'earnings' => ['name' => 'Seller Earnings', 'sort_order' => 117],
            'ledger' => ['name' => 'Seller Transactions', 'sort_order' => 118],
        ];

        foreach ($modules as $moduleSlug => $module) {
            DB::table('modules')->updateOrInsert(
                ['slug' => $moduleSlug],
                [
                    'name' => $module['name'],
                    'description' => 'View the authenticated seller\'s own financial data.',
                    'is_active' => true,
                    'sort_order' => $module['sort_order'],
                    'updated_at' => $now,
                    'created_at' => $now,
                ],
            );

            $moduleId = DB::table('modules')->where('slug', $moduleSlug)->value('id');
            $permissionSlug = "{$moduleSlug}.view_own";

            DB::table('permissions')->updateOrInsert(
                ['slug' => $permissionSlug],
                [
                    'module_id' => $moduleId,
                    'name' => 'View Own '.$module['name'],
                    'action' => 'view_own',
                    'description' => 'View only financial records owned by the authenticated seller.',
                    'is_active' => true,
                    'updated_at' => $now,
                    'created_at' => $now,
                ],
            );

            $permissionId = DB::table('permissions')->where('slug', $permissionSlug)->value('id');
            $roleIds = DB::table('roles')
                ->whereIn('slug', ['super_admin', 'marketplace_user'])
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
        $slugs = ['wallet.view_own', 'earnings.view_own', 'ledger.view_own'];
        $permissionIds = DB::table('permissions')->whereIn('slug', $slugs)->pluck('id');

        DB::table('permission_role')->whereIn('permission_id', $permissionIds)->delete();
        DB::table('permissions')->whereIn('id', $permissionIds)->delete();
        DB::table('modules')->whereIn('slug', ['wallet', 'earnings', 'ledger'])->delete();
    }
};
