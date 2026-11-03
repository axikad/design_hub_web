<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        $this->addColumnIfMissing('folders', 'user_id', function (Blueprint $table) {
            $table->foreignId('user_id')
                ->nullable()
                ->after('public_id')
                ->constrained('users')
                ->nullOnDelete();
        });

        $this->addColumnIfMissing('site_settings', 'seller_payouts_enabled', function (Blueprint $table) {
            $table->boolean('seller_payouts_enabled')->default(false);
        });
        $this->addColumnIfMissing('site_settings', 'platform_commission_basis_points', function (Blueprint $table) {
            $table->unsignedSmallInteger('platform_commission_basis_points')->default(2500);
        });
        $this->addColumnIfMissing('site_settings', 'earning_hold_days', function (Blueprint $table) {
            $table->unsignedSmallInteger('earning_hold_days')->default(7);
        });
        $this->addColumnIfMissing('site_settings', 'minimum_withdrawal_paise', function (Blueprint $table) {
            $table->unsignedBigInteger('minimum_withdrawal_paise')->default(50000);
        });
        $this->addColumnIfMissing('site_settings', 'buyer_gst_enabled', function (Blueprint $table) {
            $table->boolean('buyer_gst_enabled')->default(false);
        });
        $this->addColumnIfMissing('site_settings', 'buyer_gst_basis_points', function (Blueprint $table) {
            $table->unsignedSmallInteger('buyer_gst_basis_points')->default(0);
        });
        $this->addColumnIfMissing('site_settings', 'commission_gst_enabled', function (Blueprint $table) {
            $table->boolean('commission_gst_enabled')->default(false);
        });
        $this->addColumnIfMissing('site_settings', 'commission_gst_basis_points', function (Blueprint $table) {
            $table->unsignedSmallInteger('commission_gst_basis_points')->default(0);
        });
        $this->addColumnIfMissing('site_settings', 'tds_enabled', function (Blueprint $table) {
            $table->boolean('tds_enabled')->default(false);
        });
        $this->addColumnIfMissing('site_settings', 'tds_basis_points', function (Blueprint $table) {
            $table->unsignedSmallInteger('tds_basis_points')->default(0);
        });
        $this->addColumnIfMissing('site_settings', 'gst_tcs_enabled', function (Blueprint $table) {
            $table->boolean('gst_tcs_enabled')->default(false);
        });
        $this->addColumnIfMissing('site_settings', 'gst_tcs_basis_points', function (Blueprint $table) {
            $table->unsignedSmallInteger('gst_tcs_basis_points')->default(0);
        });

        $this->addColumnIfMissing('payment_orders', 'payment_environment', function (Blueprint $table) {
            $table->string('payment_environment', 8)->default('test')->after('currency');
        });
        $this->addColumnIfMissing('payment_orders', 'commission_basis_points', function (Blueprint $table) {
            $table->unsignedSmallInteger('commission_basis_points')->default(2500)->after('payment_environment');
        });

        $this->addColumnIfMissing('folder_payment_orders', 'payment_environment', function (Blueprint $table) {
            $table->string('payment_environment', 8)->default('test')->after('currency');
        });
        $this->addColumnIfMissing('folder_payment_orders', 'commission_basis_points', function (Blueprint $table) {
            $table->unsignedSmallInteger('commission_basis_points')->default(2500)->after('payment_environment');
        });

        $this->addColumnIfMissing('purchases', 'payment_environment', function (Blueprint $table) {
            $table->string('payment_environment', 8)->default('test')->after('currency');
        });

        $this->addColumnIfMissing('folder_purchases', 'payment_environment', function (Blueprint $table) {
            $table->string('payment_environment', 8)->default('test')->after('currency');
        });

        if (! Schema::hasTable('sale_earnings')) {
            Schema::create('sale_earnings', function (Blueprint $table) {
                $table->id();
                $table->string('public_id')->unique();
                $table->foreignId('seller_id')->constrained('users')->restrictOnDelete();
                $table->foreignId('buyer_id')->constrained('users')->restrictOnDelete();
                $table->foreignId('design_purchase_id')->nullable()->constrained('purchases')->nullOnDelete();
                $table->foreignId('folder_purchase_id')->nullable()->constrained('folder_purchases')->nullOnDelete();
                $table->string('item_type', 20);
                $table->unsignedBigInteger('item_id');
                $table->string('item_public_id');
                $table->string('item_name');
                $table->string('currency', 8)->default('INR');
                $table->string('payment_environment', 8)->default('test');
                $table->unsignedBigInteger('taxable_amount_paise');
                $table->unsignedBigInteger('buyer_gst_paise')->default(0);
                $table->unsignedBigInteger('buyer_paid_paise');
                $table->unsignedSmallInteger('commission_basis_points');
                $table->unsignedBigInteger('commission_paise');
                $table->unsignedBigInteger('seller_gross_paise');
                $table->unsignedBigInteger('commission_gst_paise')->default(0);
                $table->unsignedBigInteger('tds_paise')->default(0);
                $table->unsignedBigInteger('gst_tcs_paise')->default(0);
                $table->unsignedBigInteger('provider_fee_paise')->default(0);
                $table->unsignedBigInteger('refund_paise')->default(0);
                $table->unsignedBigInteger('seller_net_paise');
                $table->string('status', 24)->default('pending');
                $table->timestamp('available_at')->nullable();
                $table->json('calculation_snapshot');
                $table->timestamps();

                $table->unique('design_purchase_id');
                $table->unique('folder_purchase_id');
                $table->index(['seller_id', 'payment_environment', 'status'], 'sale_earnings_seller_status_idx');
                $table->index(['seller_id', 'available_at'], 'sale_earnings_seller_available_idx');
            });
        }

        if (! Schema::hasTable('seller_ledger_entries')) {
            Schema::create('seller_ledger_entries', function (Blueprint $table) {
                $table->id();
                $table->string('public_id')->unique();
                $table->foreignId('seller_id')->constrained('users')->restrictOnDelete();
                $table->foreignId('sale_earning_id')->nullable()->constrained('sale_earnings')->restrictOnDelete();
                $table->string('entry_type', 40);
                $table->string('balance_bucket', 24);
                $table->bigInteger('amount_paise');
                $table->string('currency', 8)->default('INR');
                $table->string('payment_environment', 8)->default('test');
                $table->string('idempotency_key')->unique();
                $table->json('metadata')->nullable();
                $table->timestamps();
            });
        }

        if (! Schema::hasIndex('seller_ledger_entries', 'seller_ledger_seller_balance_idx')) {
            Schema::table('seller_ledger_entries', function (Blueprint $table) {
                $table->index(
                    ['seller_id', 'payment_environment', 'balance_bucket'],
                    'seller_ledger_seller_balance_idx',
                );
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('seller_ledger_entries');
        Schema::dropIfExists('sale_earnings');

        Schema::table('folder_purchases', function (Blueprint $table) {
            $table->dropColumn('payment_environment');
        });

        Schema::table('purchases', function (Blueprint $table) {
            $table->dropColumn('payment_environment');
        });

        Schema::table('folder_payment_orders', function (Blueprint $table) {
            $table->dropColumn(['payment_environment', 'commission_basis_points']);
        });

        Schema::table('payment_orders', function (Blueprint $table) {
            $table->dropColumn(['payment_environment', 'commission_basis_points']);
        });

        Schema::table('site_settings', function (Blueprint $table) {
            $table->dropColumn([
                'seller_payouts_enabled',
                'platform_commission_basis_points',
                'earning_hold_days',
                'minimum_withdrawal_paise',
                'buyer_gst_enabled',
                'buyer_gst_basis_points',
                'commission_gst_enabled',
                'commission_gst_basis_points',
                'tds_enabled',
                'tds_basis_points',
                'gst_tcs_enabled',
                'gst_tcs_basis_points',
            ]);
        });

        Schema::table('folders', function (Blueprint $table) {
            $table->dropConstrainedForeignId('user_id');
        });
    }

    private function addColumnIfMissing(string $table, string $column, callable $definition): void
    {
        if (Schema::hasColumn($table, $column)) {
            return;
        }

        Schema::table($table, $definition);
    }
};
