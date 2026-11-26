<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('catalogs', function (Blueprint $table): void {
            $table->id();
            $table->string('public_id')->unique();
            $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('name');
            $table->unsignedBigInteger('price_paise');
            $table->string('zip_name');
            $table->string('zip_path', 1024);
            $table->unsignedInteger('file_count')->default(0);
            $table->unsignedBigInteger('total_size')->default(0);
            $table->timestamps();
            $table->index('name');
        });

        Schema::create('catalog_files', function (Blueprint $table): void {
            $table->id();
            $table->string('public_id')->unique();
            $table->foreignId('catalog_id')->constrained()->cascadeOnDelete();
            $table->string('name');
            $table->string('relative_path', 1024);
            $table->string('storage_path', 1024);
            $table->string('extension', 50)->nullable();
            $table->string('mime_type')->default('application/octet-stream');
            $table->unsignedBigInteger('size')->default(0);
            $table->timestamps();
            $table->unique(['catalog_id', 'relative_path']);
        });

        Schema::create('catalog_payment_orders', function (Blueprint $table): void {
            $table->id();
            $table->string('public_id')->unique();
            $table->foreignId('buyer_id')->constrained('users')->cascadeOnDelete();
            $table->foreignId('catalog_id')->constrained()->cascadeOnDelete();
            $table->string('razorpay_order_id')->unique();
            $table->string('razorpay_payment_id')->nullable()->unique();
            $table->unsignedBigInteger('amount_paise');
            $table->unsignedBigInteger('taxable_amount_paise')->default(0);
            $table->unsignedSmallInteger('buyer_gst_basis_points')->default(0);
            $table->unsignedBigInteger('buyer_gst_paise')->default(0);
            $table->unsignedBigInteger('cgst_paise')->default(0);
            $table->unsignedBigInteger('sgst_paise')->default(0);
            $table->unsignedBigInteger('igst_paise')->default(0);
            $table->string('gst_supply_type', 20)->default('none');
            $table->string('seller_state_code', 2)->nullable();
            $table->string('buyer_state_code', 2)->nullable();
            $table->string('currency', 8)->default('INR');
            $table->string('payment_environment', 8)->default('test');
            $table->unsignedSmallInteger('commission_basis_points')->default(2000);
            $table->unsignedSmallInteger('commission_gst_basis_points')->default(0);
            $table->unsignedBigInteger('commission_gst_paise')->default(0);
            $table->json('tax_snapshot')->nullable();
            $table->string('status', 32)->default('created');
            $table->timestamp('last_reconciled_at')->nullable();
            $table->timestamps();
            $table->index(['buyer_id', 'catalog_id', 'status']);
        });

        Schema::create('catalog_purchases', function (Blueprint $table): void {
            $table->id();
            $table->string('public_id')->unique();
            $table->foreignId('buyer_id')->constrained('users')->cascadeOnDelete();
            $table->foreignId('catalog_id')->constrained()->cascadeOnDelete();
            $table->foreignId('catalog_payment_order_id')->nullable()->constrained()->nullOnDelete();
            $table->string('razorpay_payment_id')->nullable()->unique();
            $table->unsignedBigInteger('amount_paise');
            $table->string('currency', 8)->default('INR');
            $table->string('payment_environment', 8)->default('test');
            $table->string('status', 32)->default('paid');
            $table->timestamp('purchased_at')->nullable();
            $table->timestamps();
            $table->unique(['buyer_id', 'catalog_id']);
        });

        Schema::table('sale_earnings', function (Blueprint $table): void {
            $table->foreignId('catalog_purchase_id')
                ->nullable()
                ->after('folder_purchase_id')
                ->constrained('catalog_purchases')
                ->nullOnDelete();
            $table->unique('catalog_purchase_id');
        });

        $moduleId = DB::table('modules')->insertGetId([
            'name' => 'Catalog Management',
            'slug' => 'catalogs',
            'description' => 'Manage purchasable ZIP or RAR catalogs.',
            'is_active' => true,
            'sort_order' => 46,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $permissionIds = [];
        foreach (['read', 'create', 'update', 'delete'] as $action) {
            $permissionIds[$action] = DB::table('permissions')->insertGetId([
                'module_id' => $moduleId,
                'name' => ucfirst($action).' Catalog Management',
                'slug' => "catalogs.{$action}",
                'action' => $action,
                'is_active' => true,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }

        $roles = DB::table('roles')->whereIn('slug', ['super_admin', 'admin', 'staff', 'marketplace_user'])
            ->pluck('id', 'slug');
        foreach ($roles as $slug => $roleId) {
            $allowed = $slug === 'staff' ? [$permissionIds['read']] : array_values($permissionIds);
            foreach ($allowed as $permissionId) {
                DB::table('permission_role')->insertOrIgnore([
                    'role_id' => $roleId,
                    'permission_id' => $permissionId,
                ]);
            }
        }
    }

    public function down(): void
    {
        $permissionIds = DB::table('permissions')->where('slug', 'like', 'catalogs.%')->pluck('id');
        DB::table('permission_role')->whereIn('permission_id', $permissionIds)->delete();
        DB::table('permissions')->whereIn('id', $permissionIds)->delete();
        DB::table('modules')->where('slug', 'catalogs')->delete();

        Schema::table('sale_earnings', function (Blueprint $table): void {
            $table->dropUnique(['catalog_purchase_id']);
            $table->dropConstrainedForeignId('catalog_purchase_id');
        });

        Schema::dropIfExists('catalog_purchases');
        Schema::dropIfExists('catalog_payment_orders');
        Schema::dropIfExists('catalog_files');
        Schema::dropIfExists('catalogs');
    }
};
