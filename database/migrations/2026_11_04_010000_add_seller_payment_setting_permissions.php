<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        $now = now();

        DB::table('modules')->updateOrInsert(
            ['slug' => 'seller_payments'],
            [
                'name' => 'Seller Payments',
                'description' => 'Configure seller commission, earning holds, withdrawals and tax placeholders.',
                'is_active' => true,
                'sort_order' => 115,
                'updated_at' => $now,
                'created_at' => $now,
            ],
        );

        $moduleId = DB::table('modules')->where('slug', 'seller_payments')->value('id');

        foreach (['read', 'update'] as $action) {
            $slug = "seller_payments.{$action}";

            DB::table('permissions')->updateOrInsert(
                ['slug' => $slug],
                [
                    'module_id' => $moduleId,
                    'name' => str($action)->title()->append(' Seller Payments')->toString(),
                    'action' => $action,
                    'description' => $action === 'read'
                        ? 'View seller payment and marketplace financial settings.'
                        : 'Change seller payment and marketplace financial settings.',
                    'is_active' => true,
                    'updated_at' => $now,
                    'created_at' => $now,
                ],
            );

            $permissionId = DB::table('permissions')->where('slug', $slug)->value('id');
            $superAdminId = DB::table('roles')->where('slug', 'super_admin')->value('id');

            if ($permissionId !== null && $superAdminId !== null) {
                DB::table('permission_role')->insertOrIgnore([
                    'role_id' => $superAdminId,
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
            ->whereIn('slug', ['seller_payments.read', 'seller_payments.update'])
            ->pluck('id');

        DB::table('permission_role')->whereIn('permission_id', $permissionIds)->delete();
        DB::table('permissions')->whereIn('id', $permissionIds)->delete();
        DB::table('modules')->where('slug', 'seller_payments')->delete();
    }
};
