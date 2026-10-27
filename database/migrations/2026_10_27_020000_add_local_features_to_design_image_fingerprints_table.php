<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('design_image_fingerprints', function (Blueprint $table) {
            $table->json('color_histogram')->nullable()->after('perceptual_hash');
            $table->json('grayscale_layout')->nullable()->after('color_histogram');
        });
    }

    public function down(): void
    {
        Schema::table('design_image_fingerprints', function (Blueprint $table) {
            $table->dropColumn(['color_histogram', 'grayscale_layout']);
        });
    }
};
