<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('design_image_fingerprints', function (Blueprint $table) {
            $table->dropUnique(['vector_id']);
            $table->dropIndex(['vector_status']);
            $table->dropColumn(['vector_id', 'vector_status', 'vector_error']);

            $table->json('embedding')->nullable();
            $table->string('embedding_model')->nullable();
            $table->string('embedding_status', 20)->default('disabled')->index();
            $table->text('embedding_error')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('design_image_fingerprints', function (Blueprint $table) {
            $table->dropIndex(['embedding_status']);
            $table->dropColumn(['embedding', 'embedding_model', 'embedding_status', 'embedding_error']);

            $table->uuid('vector_id')->nullable()->unique();
            $table->string('vector_status', 20)->default('pending')->index();
            $table->text('vector_error')->nullable();
        });
    }
};
