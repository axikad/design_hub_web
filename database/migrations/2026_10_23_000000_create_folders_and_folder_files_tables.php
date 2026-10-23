<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('folders', function (Blueprint $table) {
            $table->id();
            $table->string('public_id')->unique();
            $table->string('name');
            $table->unsignedBigInteger('price_paise');
            $table->string('zip_name');
            $table->string('zip_path');
            $table->unsignedInteger('file_count')->default(0);
            $table->unsignedBigInteger('total_size')->default(0);
            $table->timestamps();

            $table->index('name');
        });

        Schema::create('folder_files', function (Blueprint $table) {
            $table->id();
            $table->foreignId('folder_id')->constrained()->cascadeOnDelete();
            $table->string('name');
            $table->string('relative_path', 1024);
            $table->string('storage_path', 1024);
            $table->string('extension', 50)->nullable();
            $table->string('mime_type')->default('application/octet-stream');
            $table->unsignedBigInteger('size')->default(0);
            $table->timestamps();

            $table->unique(['folder_id', 'relative_path']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('folder_files');
        Schema::dropIfExists('folders');
    }
};
