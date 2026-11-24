<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->string('tax_state_code', 2)->nullable()->after('phone_key');
            $table->boolean('gst_registered')->default(false)->after('tax_state_code');
            $table->string('gstin', 15)->nullable()->after('gst_registered');
        });

        foreach (['payment_orders', 'folder_payment_orders'] as $tableName) {
            Schema::table($tableName, function (Blueprint $table) {
                $table->unsignedBigInteger('taxable_amount_paise')->default(0)->after('amount_paise');
                $table->unsignedSmallInteger('buyer_gst_basis_points')->default(0)->after('taxable_amount_paise');
                $table->unsignedBigInteger('buyer_gst_paise')->default(0)->after('buyer_gst_basis_points');
                $table->unsignedBigInteger('cgst_paise')->default(0)->after('buyer_gst_paise');
                $table->unsignedBigInteger('sgst_paise')->default(0)->after('cgst_paise');
                $table->unsignedBigInteger('igst_paise')->default(0)->after('sgst_paise');
                $table->string('gst_supply_type', 20)->default('none')->after('igst_paise');
                $table->string('seller_state_code', 2)->nullable()->after('gst_supply_type');
                $table->string('buyer_state_code', 2)->nullable()->after('seller_state_code');
                $table->unsignedSmallInteger('commission_gst_basis_points')->default(0)->after('commission_basis_points');
                $table->unsignedBigInteger('commission_gst_paise')->default(0)->after('commission_gst_basis_points');
                $table->json('tax_snapshot')->nullable()->after('commission_gst_paise');
            });
        }

        Schema::table('sale_earnings', function (Blueprint $table) {
            $table->unsignedSmallInteger('buyer_gst_basis_points')->default(0)->after('buyer_gst_paise');
            $table->unsignedBigInteger('cgst_paise')->default(0)->after('buyer_gst_basis_points');
            $table->unsignedBigInteger('sgst_paise')->default(0)->after('cgst_paise');
            $table->unsignedBigInteger('igst_paise')->default(0)->after('sgst_paise');
            $table->string('gst_supply_type', 20)->default('none')->after('igst_paise');
            $table->string('seller_state_code', 2)->nullable()->after('gst_supply_type');
            $table->string('buyer_state_code', 2)->nullable()->after('seller_state_code');
            $table->unsignedSmallInteger('commission_gst_basis_points')->default(0)->after('commission_gst_paise');
        });
    }

    public function down(): void
    {
        Schema::table('sale_earnings', function (Blueprint $table) {
            $table->dropColumn([
                'buyer_gst_basis_points', 'cgst_paise', 'sgst_paise', 'igst_paise',
                'gst_supply_type', 'seller_state_code', 'buyer_state_code', 'commission_gst_basis_points',
            ]);
        });

        foreach (['payment_orders', 'folder_payment_orders'] as $tableName) {
            Schema::table($tableName, function (Blueprint $table) {
                $table->dropColumn([
                    'taxable_amount_paise', 'buyer_gst_basis_points', 'buyer_gst_paise',
                    'cgst_paise', 'sgst_paise', 'igst_paise', 'gst_supply_type',
                    'seller_state_code', 'buyer_state_code', 'commission_gst_basis_points',
                    'commission_gst_paise', 'tax_snapshot',
                ]);
            });
        }

        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn(['tax_state_code', 'gst_registered', 'gstin']);
        });
    }
};
