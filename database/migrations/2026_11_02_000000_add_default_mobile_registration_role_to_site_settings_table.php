<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('site_settings', function (Blueprint $table) {
            $table->foreignId('default_mobile_registration_role_id')
                ->nullable()
                ->after('default_registration_role_id')
                ->constrained('roles')
                ->nullOnDelete();
        });

        $marketplaceRoleId = DB::table('roles')->where('slug', 'marketplace_user')->value('id');

        if ($marketplaceRoleId !== null) {
            DB::table('site_settings')->update([
                'default_mobile_registration_role_id' => $marketplaceRoleId,
            ]);
        }

        Cache::forget('site_settings.current');
    }

    public function down(): void
    {
        Schema::table('site_settings', function (Blueprint $table) {
            $table->dropConstrainedForeignId('default_mobile_registration_role_id');
        });

        Cache::forget('site_settings.current');
    }
};
