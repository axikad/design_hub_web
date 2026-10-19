<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('machines', function (Blueprint $table) {
            $table->id();
            $table->string('public_id')->unique();
            $table->string('name');
            $table->boolean('is_active')->default(true);
            $table->unsignedInteger('sort_order')->default(0);
            $table->timestamps();
        });

        Schema::create('machine_types', function (Blueprint $table) {
            $table->id();
            $table->foreignId('machine_id')->constrained()->cascadeOnDelete();
            $table->string('public_id')->unique();
            $table->string('name');
            $table->boolean('is_active')->default(true);
            $table->unsignedInteger('sort_order')->default(0);
            $table->timestamps();
        });

        Schema::create('category_machine_type', function (Blueprint $table) {
            $table->foreignId('design_category_id')->constrained()->cascadeOnDelete();
            $table->foreignId('machine_type_id')->constrained()->cascadeOnDelete();
            $table->primary(['design_category_id', 'machine_type_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('category_machine_type');
        Schema::dropIfExists('machine_types');
        Schema::dropIfExists('machines');
    }
};
