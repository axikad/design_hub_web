<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        $roleId = DB::table('roles')->where('slug', 'marketplace_user')->value('id');

        if ($roleId === null) {
            return;
        }

        $now = now();
        $permissionIds = DB::table('permissions')
            ->whereIn('slug', [
                'designs.read',
                'designs.create',
                'designs.update',
                'designs.delete',
            ])
            ->pluck('id');

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

    public function down(): void
    {
        // Preserve role permissions because an administrator may have changed
        // them after this migration was applied.
    }
};
