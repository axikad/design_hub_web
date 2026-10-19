<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::dropIfExists('role_permissions');
        DB::table('permissions')->where('action', 'legacy')->delete();
    }

    public function down(): void
    {
        Schema::create('role_permissions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('role_id')->constrained()->cascadeOnDelete();
            $table->foreignId('permission_id')->constrained()->cascadeOnDelete();
            $table->boolean('read')->default(false);
            $table->boolean('create')->default(false);
            $table->boolean('update')->default(false);
            $table->boolean('delete')->default(false);
            $table->timestamps();

            $table->unique(['role_id', 'permission_id']);
        });

        $legacyPermissions = [
            'dashboard' => ['slug' => 'admin_access', 'name' => 'Admin panel'],
            'users' => ['slug' => 'user_access', 'name' => 'Users'],
            'roles' => ['slug' => 'role_access', 'name' => 'Roles & permissions'],
            'designs' => ['slug' => 'design_access', 'name' => 'Designs'],
            'categories' => ['slug' => 'category_access', 'name' => 'Categories'],
            'design_types' => ['slug' => 'type_access', 'name' => 'Types'],
            'purchases' => ['slug' => 'purchase_access', 'name' => 'Purchases'],
            'branding' => ['slug' => 'site_setting_access', 'name' => 'Site settings'],
        ];
        $now = now();

        foreach ($legacyPermissions as $resource => $legacy) {
            $moduleId = DB::table('modules')->where('slug', $resource)->value('id');

            if ($moduleId === null) {
                continue;
            }

            $legacyPermissionId = DB::table('permissions')->insertGetId([
                'module_id' => $moduleId,
                'name' => $legacy['name'],
                'slug' => $legacy['slug'],
                'action' => 'legacy',
                'is_active' => true,
                'created_at' => $now,
                'updated_at' => $now,
            ]);

            DB::table('roles')->pluck('id')->each(function (int $roleId) use ($resource, $legacyPermissionId, $now): void {
                $flags = [];

                foreach (['read', 'create', 'update', 'delete'] as $action) {
                    $flags[$action] = DB::table('permission_role')
                        ->join('permissions', 'permissions.id', '=', 'permission_role.permission_id')
                        ->where('permission_role.role_id', $roleId)
                        ->where('permissions.slug', "{$resource}.{$action}")
                        ->exists();
                }

                DB::table('role_permissions')->insert([
                    'role_id' => $roleId,
                    'permission_id' => $legacyPermissionId,
                    ...$flags,
                    'created_at' => $now,
                    'updated_at' => $now,
                ]);
            });
        }
    }
};
