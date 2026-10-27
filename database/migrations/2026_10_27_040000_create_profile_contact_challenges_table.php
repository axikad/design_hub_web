<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('profile_contact_challenges', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('type', 20);
            $table->string('target');
            $table->string('country_code', 8)->nullable();
            $table->string('mobile_number', 20)->nullable();
            $table->string('email')->nullable();
            $table->string('code_hash');
            $table->unsignedTinyInteger('attempts')->default(0);
            $table->dateTime('expires_at');
            $table->dateTime('consumed_at')->nullable();
            $table->dateTime('last_sent_at');
            $table->timestamps();

            $table->index(['user_id', 'type', 'target'], 'profile_contact_lookup');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('profile_contact_challenges');
    }
};
