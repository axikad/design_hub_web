<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('design_machine_types', function (Blueprint $table) {
            $table->id();
            $table->string('public_id')->unique();
            $table->string('name');
            $table->boolean('is_active')->default(true);
            $table->unsignedInteger('sort_order')->default(0);
            $table->timestamps();
        });

        Schema::create('design_option_categories', function (Blueprint $table) {
            $table->id();
            $table->string('public_id')->unique();
            $table->string('name');
            $table->boolean('is_active')->default(true);
            $table->unsignedInteger('sort_order')->default(0);
            $table->timestamps();
        });

        Schema::create('design_category_machine_type', function (Blueprint $table) {
            $table->foreignId('design_option_category_id')->constrained()->cascadeOnDelete();
            $table->foreignId('design_machine_type_id')->constrained()->cascadeOnDelete();
            $table->primary(['design_option_category_id', 'design_machine_type_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('design_category_machine_type');
        Schema::dropIfExists('design_option_categories');
        Schema::dropIfExists('design_machine_types');
    }
};
