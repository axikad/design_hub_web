<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('otp_challenges', function (Blueprint $table) {
            $table->id();
            $table->string('phone_key')->index();
            $table->string('flow', 20);
            $table->string('name')->nullable();
            $table->string('code_hash');
            $table->unsignedTinyInteger('attempts')->default(0);
            $table->dateTime('expires_at');
            $table->dateTime('consumed_at')->nullable();
            $table->dateTime('last_sent_at');
            $table->timestamps();
        });

        Schema::create('uploads', function (Blueprint $table) {
            $table->id();
            $table->string('public_id')->unique();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('disk', 32);
            $table->string('path');
            $table->string('kind', 20);
            $table->string('original_name')->nullable();
            $table->string('mime', 120)->nullable();
            $table->unsignedBigInteger('size_bytes')->default(0);
            $table->boolean('attached')->default(false);
            $table->timestamps();
        });

        Schema::create('designs', function (Blueprint $table) {
            $table->id();
            $table->string('public_id')->unique();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('title');
            $table->unsignedBigInteger('price_paise')->nullable();
            $table->string('size');
            $table->string('color');
            $table->string('preview_path')->nullable();
            $table->string('stitch_path')->nullable();
            $table->string('file_name')->nullable();
            $table->timestamp('deleted_at')->nullable();
            $table->timestamps();

            $table->index(['deleted_at', 'created_at']);
        });

        Schema::create('saved_designs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('design_id')->constrained()->cascadeOnDelete();
            $table->timestamps();

            $table->unique(['user_id', 'design_id']);
        });

        Schema::create('payment_orders', function (Blueprint $table) {
            $table->id();
            $table->string('public_id')->unique();
            $table->foreignId('buyer_id')->constrained('users')->cascadeOnDelete();
            $table->foreignId('design_id')->constrained()->cascadeOnDelete();
            $table->string('razorpay_order_id')->unique();
            $table->unsignedBigInteger('amount_paise');
            $table->string('currency', 8)->default('INR');
            $table->string('status', 32)->default('created');
            $table->timestamps();

            $table->index(['buyer_id', 'design_id', 'status']);
        });

        Schema::create('purchases', function (Blueprint $table) {
            $table->id();
            $table->string('public_id')->unique();
            $table->foreignId('buyer_id')->constrained('users')->cascadeOnDelete();
            $table->foreignId('design_id')->constrained()->cascadeOnDelete();
            $table->foreignId('payment_order_id')->nullable()->constrained()->nullOnDelete();
            $table->string('razorpay_payment_id')->nullable()->unique();
            $table->unsignedBigInteger('amount_paise');
            $table->string('currency', 8)->default('INR');
            $table->string('status', 32)->default('paid');
            $table->timestamp('purchased_at')->nullable();
            $table->timestamps();

            $table->unique(['buyer_id', 'design_id']);
        });

        Schema::create('webhook_events', function (Blueprint $table) {
            $table->id();
            $table->string('provider_event_id')->unique();
            $table->string('event_type')->nullable();
            $table->json('payload');
            $table->string('status', 32)->default('received');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('webhook_events');
        Schema::dropIfExists('purchases');
        Schema::dropIfExists('payment_orders');
        Schema::dropIfExists('saved_designs');
        Schema::dropIfExists('designs');
        Schema::dropIfExists('uploads');
        Schema::dropIfExists('otp_challenges');
    }
};
