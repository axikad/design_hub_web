<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('design_variants', function (Blueprint $table) {
            $table->id();
            $table->foreignId('design_id')->constrained()->cascadeOnDelete();
            $table->foreignId('design_type_id')->constrained('design_types')->restrictOnDelete();
            $table->string('name_or_number');
            $table->unsignedBigInteger('stitch_count')->default(0);
            $table->unsignedInteger('needle')->default(0);
            $table->decimal('area', 12, 2)->default(0);
            $table->decimal('height', 12, 2)->default(0);
            $table->decimal('width', 12, 2)->default(0);
            $table->unsignedInteger('sort_order')->default(0);
            $table->timestamps();

            $table->unique(['design_id', 'design_type_id']);
            $table->index(['design_id', 'sort_order']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('design_variants');
    }
};
