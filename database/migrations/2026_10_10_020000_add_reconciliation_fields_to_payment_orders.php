<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('payment_orders', function (Blueprint $table) {
            $table->string('razorpay_payment_id')->nullable()->unique()->after('razorpay_order_id');
            $table->timestamp('last_reconciled_at')->nullable()->after('status');
        });
    }

    public function down(): void
    {
        Schema::table('payment_orders', function (Blueprint $table) {
            $table->dropUnique(['razorpay_payment_id']);
            $table->dropColumn(['razorpay_payment_id', 'last_reconciled_at']);
        });
    }
};
