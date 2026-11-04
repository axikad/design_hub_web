<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('withdrawals')) {
            Schema::create('withdrawals', function (Blueprint $table) {
                $table->id();
                $table->string('public_id')->unique();
                $table->foreignId('seller_id')->constrained('users')->restrictOnDelete();
                $table->unsignedBigInteger('amount_paise');
                $table->unsignedBigInteger('fee_paise')->default(0);
                $table->unsignedBigInteger('net_transfer_paise');
                $table->string('currency', 8)->default('INR');
                $table->string('payment_environment', 8)->default('test');
                $table->string('payout_mode', 16)->default('simulated');
                $table->string('status', 24)->default('requested');
                $table->string('idempotency_key', 100);
                $table->timestamp('requested_at');
                $table->timestamp('cancelled_at')->nullable();
                $table->timestamp('approved_at')->nullable();
                $table->timestamp('processed_at')->nullable();
                $table->foreignId('approved_by')->nullable()->constrained('users')->nullOnDelete();
                $table->string('provider', 32)->nullable();
                $table->string('provider_transfer_id')->nullable();
                $table->string('provider_status', 40)->nullable();
                $table->string('failure_code')->nullable();
                $table->text('failure_message')->nullable();
                $table->text('rejection_reason')->nullable();
                $table->json('payout_snapshot')->nullable();
                $table->timestamps();

                $table->unique(
                    ['seller_id', 'payment_environment', 'idempotency_key'],
                    'withdrawal_seller_env_idem_unique',
                );
                $table->index(
                    ['seller_id', 'payment_environment', 'status'],
                    'withdrawal_seller_env_status_idx',
                );
            });
        }

        if (! Schema::hasTable('withdrawal_status_histories')) {
            Schema::create('withdrawal_status_histories', function (Blueprint $table) {
                $table->id();
                $table->foreignId('withdrawal_id')->constrained('withdrawals')->cascadeOnDelete();
                $table->string('from_status', 24)->nullable();
                $table->string('to_status', 24);
                $table->foreignId('actor_id')->nullable()->constrained('users')->nullOnDelete();
                $table->text('note')->nullable();
                $table->json('metadata')->nullable();
                $table->timestamps();

                $table->index(['withdrawal_id', 'id'], 'withdrawal_history_idx');
            });
        }

        if (! Schema::hasColumn('seller_ledger_entries', 'withdrawal_id')) {
            Schema::table('seller_ledger_entries', function (Blueprint $table) {
                $table->foreignId('withdrawal_id')
                    ->nullable()
                    ->after('sale_earning_id')
                    ->constrained('withdrawals')
                    ->restrictOnDelete();
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasColumn('seller_ledger_entries', 'withdrawal_id')) {
            Schema::table('seller_ledger_entries', function (Blueprint $table) {
                $table->dropConstrainedForeignId('withdrawal_id');
            });
        }

        Schema::dropIfExists('withdrawal_status_histories');
        Schema::dropIfExists('withdrawals');
    }
};
