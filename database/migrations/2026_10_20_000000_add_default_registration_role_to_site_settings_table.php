<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('site_settings', function (Blueprint $table) {
            $table->foreignId('default_registration_role_id')
                ->nullable()
                ->after('favicon_path')
                ->constrained('roles')
                ->nullOnDelete();
        });

        $staffRoleId = DB::table('roles')->where('slug', 'staff')->value('id');

        if ($staffRoleId !== null) {
            DB::table('site_settings')->update([
                'default_registration_role_id' => $staffRoleId,
            ]);
        }
    }

    public function down(): void
    {
        Schema::table('site_settings', function (Blueprint $table) {
            $table->dropConstrainedForeignId('default_registration_role_id');
        });
    }
};
