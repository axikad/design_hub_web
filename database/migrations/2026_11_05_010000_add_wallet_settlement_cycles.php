<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('settlement_cycles')) {
            Schema::create('settlement_cycles', function (Blueprint $table) {
                $table->id();
                $table->unsignedTinyInteger('start_day');
                $table->unsignedTinyInteger('end_day');
                $table->unsignedTinyInteger('settlement_day');
                $table->unsignedSmallInteger('sort_order')->default(0);
                $table->timestamps();

                $table->index(['sort_order', 'start_day'], 'settlement_cycle_order_idx');
            });
        }

        if (! Schema::hasColumn('users', 'seller_settlement_mode')) {
            Schema::table('users', function (Blueprint $table) {
                $table->string('seller_settlement_mode', 16)->default('manual')->after('allow_password_login');
            });
        }

        if (! Schema::hasColumn('withdrawals', 'request_source')) {
            Schema::table('withdrawals', function (Blueprint $table) {
                $table->string('request_source', 16)->default('seller')->after('payout_mode');
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasColumn('withdrawals', 'request_source')) {
            Schema::table('withdrawals', fn (Blueprint $table) => $table->dropColumn('request_source'));
        }

        if (Schema::hasColumn('users', 'seller_settlement_mode')) {
            Schema::table('users', fn (Blueprint $table) => $table->dropColumn('seller_settlement_mode'));
        }

        Schema::dropIfExists('settlement_cycles');
    }
};
