<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('folder_payment_orders', function (Blueprint $table) {
            $table->id();
            $table->string('public_id')->unique();
            $table->foreignId('buyer_id')->constrained('users')->cascadeOnDelete();
            $table->foreignId('folder_id')->constrained()->cascadeOnDelete();
            $table->string('razorpay_order_id')->unique();
            $table->string('razorpay_payment_id')->nullable()->unique();
            $table->unsignedBigInteger('amount_paise');
            $table->string('currency', 8)->default('INR');
            $table->string('status', 32)->default('created');
            $table->timestamp('last_reconciled_at')->nullable();
            $table->timestamps();

            $table->index(['buyer_id', 'folder_id', 'status']);
        });

        Schema::create('folder_purchases', function (Blueprint $table) {
            $table->id();
            $table->string('public_id')->unique();
            $table->foreignId('buyer_id')->constrained('users')->cascadeOnDelete();
            $table->foreignId('folder_id')->constrained()->cascadeOnDelete();
            $table->foreignId('folder_payment_order_id')->nullable()->constrained()->nullOnDelete();
            $table->string('razorpay_payment_id')->nullable()->unique();
            $table->unsignedBigInteger('amount_paise');
            $table->string('currency', 8)->default('INR');
            $table->string('status', 32)->default('paid');
            $table->timestamp('purchased_at')->nullable();
            $table->timestamps();

            $table->unique(['buyer_id', 'folder_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('folder_purchases');
        Schema::dropIfExists('folder_payment_orders');
    }
};
