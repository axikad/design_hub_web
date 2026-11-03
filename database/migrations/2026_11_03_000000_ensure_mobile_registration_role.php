<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        $now = now();
        $roleId = DB::table('roles')->where('slug', 'marketplace_user')->value('id');

        if ($roleId === null) {
            $roleId = DB::table('roles')->insertGetId([
                'slug' => 'marketplace_user',
                'name' => 'Marketplace User',
                'created_at' => $now,
                'updated_at' => $now,
            ]);
        }

        $dashboardPermissionId = DB::table('permissions')
            ->where('slug', 'dashboard.read')
            ->value('id');

        if ($dashboardPermissionId !== null) {
            DB::table('permission_role')
                ->where('role_id', $roleId)
                ->where('permission_id', $dashboardPermissionId)
                ->delete();
        }

        DB::table('permissions')
            ->whereIn('slug', [
                'designs.read',
                'designs.create',
                'designs.update',
                'designs.delete',
            ])
            ->pluck('id')
            ->each(function (int $permissionId) use ($roleId, $now): void {
                DB::table('permission_role')->insertOrIgnore([
                    'role_id' => $roleId,
                    'permission_id' => $permissionId,
                    'assigned_by' => null,
                    'created_at' => $now,
                    'updated_at' => $now,
                ]);
            });

        DB::table('site_settings')
            ->whereNull('default_mobile_registration_role_id')
            ->update(['default_mobile_registration_role_id' => $roleId]);

        Cache::forget('site_settings.current');
    }

    public function down(): void
    {
        // Keep the role and its permissions because users may already depend on it.
    }
};
