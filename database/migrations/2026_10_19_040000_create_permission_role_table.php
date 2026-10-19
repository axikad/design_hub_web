<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('permission_role', function (Blueprint $table) {
            $table->id();
            $table->foreignId('role_id')->constrained()->cascadeOnDelete();
            $table->foreignId('permission_id')->constrained()->cascadeOnDelete();
            $table->foreignId('assigned_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->unique(['role_id', 'permission_id']);
        });

        $resourceByLegacySlug = [
            'admin_access' => 'dashboard',
            'user_access' => 'users',
            'role_access' => 'roles',
            'design_access' => 'designs',
            'category_access' => 'categories',
            'type_access' => 'design_types',
            'purchase_access' => 'purchases',
            'site_setting_access' => 'branding',
        ];

        $actions = ['read', 'create', 'update', 'delete'];
        $now = now();

        DB::table('permissions')
            ->where('action', 'legacy')
            ->orderBy('id')
            ->get()
            ->each(function (object $legacyPermission) use ($resourceByLegacySlug, $actions, $now): void {
                $resource = $resourceByLegacySlug[$legacyPermission->slug]
                    ?? str((string) $legacyPermission->slug)->beforeLast('_access')->plural()->toString();

                foreach ($actions as $action) {
                    $slug = "{$resource}.{$action}";
                    $permissionId = DB::table('permissions')->where('slug', $slug)->value('id');

                    if ($permissionId === null) {
                        $permissionId = DB::table('permissions')->insertGetId([
                            'module_id' => $legacyPermission->module_id,
                            'name' => str($action)->title()->append(' '.$legacyPermission->name)->toString(),
                            'slug' => $slug,
                            'action' => $action,
                            'description' => "Migrated from {$legacyPermission->slug}.",
                            'is_active' => true,
                            'created_at' => $now,
                            'updated_at' => $now,
                        ]);
                    }

                    DB::table('role_permissions')
                        ->where('permission_id', $legacyPermission->id)
                        ->where($action, true)
                        ->pluck('role_id')
                        ->each(function (int $roleId) use ($permissionId, $now): void {
                            DB::table('permission_role')->insertOrIgnore([
                                'role_id' => $roleId,
                                'permission_id' => $permissionId,
                                'assigned_by' => null,
                                'created_at' => $now,
                                'updated_at' => $now,
                            ]);
                        });
                }
            });
    }

    public function down(): void
    {
        Schema::dropIfExists('permission_role');

        DB::table('permissions')
            ->where('description', 'like', 'Migrated from %.')
            ->delete();
    }
};
