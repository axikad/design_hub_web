<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('design_image_fingerprints', function (Blueprint $table) {
            $table->id();
            $table->foreignId('design_id')->constrained()->cascadeOnDelete();
            $table->unsignedSmallInteger('image_index');
            $table->string('image_path', 2048);
            $table->char('sha256', 64)->index();
            $table->char('perceptual_hash', 16)->nullable();
            $table->uuid('vector_id')->nullable()->unique();
            $table->string('vector_status', 20)->default('pending')->index();
            $table->text('vector_error')->nullable();
            $table->timestamps();

            $table->unique(['design_id', 'image_index']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('design_image_fingerprints');
    }
};
