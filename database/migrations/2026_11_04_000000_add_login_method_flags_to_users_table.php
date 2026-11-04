<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->boolean('allow_otp_login')->default(false)->after('password');
            $table->boolean('allow_password_login')->default(true)->after('allow_otp_login');
        });

        DB::table('users')
            ->whereNotNull('phone_key')
            ->update(['allow_otp_login' => true]);

        DB::table('users')
            ->whereNull('password')
            ->whereNotNull('phone_key')
            ->update(['allow_password_login' => false]);
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn(['allow_otp_login', 'allow_password_login']);
        });
    }
};
