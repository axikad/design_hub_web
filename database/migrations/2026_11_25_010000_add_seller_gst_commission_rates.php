<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('site_settings', function (Blueprint $table): void {
            $table->unsignedSmallInteger('gst_seller_commission_basis_points')
                ->default(2000)
                ->after('platform_commission_basis_points');
            $table->unsignedSmallInteger('non_gst_seller_commission_basis_points')
                ->default(1500)
                ->after('gst_seller_commission_basis_points');
        });

        DB::table('site_settings')->update([
            'platform_commission_basis_points' => 2000,
            'gst_seller_commission_basis_points' => 2000,
            'non_gst_seller_commission_basis_points' => 1500,
        ]);
    }

    public function down(): void
    {
        Schema::table('site_settings', function (Blueprint $table): void {
            $table->dropColumn([
                'gst_seller_commission_basis_points',
                'non_gst_seller_commission_basis_points',
            ]);
        });
    }
};
