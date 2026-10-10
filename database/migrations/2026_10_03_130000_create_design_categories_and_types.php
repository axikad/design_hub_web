<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('design_categories', function (Blueprint $table) {
            $table->id();
            $table->string('public_id')->unique();
            $table->string('name');
            $table->string('description')->nullable();
            $table->boolean('is_active')->default(true);
            $table->unsignedInteger('sort_order')->default(0);
            $table->timestamps();
        });

        Schema::create('design_types', function (Blueprint $table) {
            $table->id();
            $table->string('public_id')->unique();
            $table->foreignId('design_category_id')->constrained('design_categories')->cascadeOnDelete();
            $table->string('name');
            $table->string('description')->nullable();
            $table->boolean('is_active')->default(true);
            $table->unsignedInteger('sort_order')->default(0);
            $table->timestamps();
        });

        Schema::table('designs', function (Blueprint $table) {
            $table->foreignId('design_type_id')->nullable()->after('user_id')->constrained('design_types')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('designs', function (Blueprint $table) {
            $table->dropConstrainedForeignId('design_type_id');
        });
        Schema::dropIfExists('design_types');
        Schema::dropIfExists('design_categories');
    }
};
