<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('design_variants', function (Blueprint $table) {
            $table->string('image_path')->nullable()->after('custom_type_name');
            $table->string('image_name')->nullable()->after('image_path');
        });
    }

    public function down(): void
    {
        Schema::table('design_variants', function (Blueprint $table) {
            $table->dropColumn(['image_path', 'image_name']);
        });
    }
};
