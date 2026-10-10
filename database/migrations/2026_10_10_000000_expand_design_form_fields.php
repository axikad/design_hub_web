<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('designs', function (Blueprint $table) {
            $table->string('machine_type_id')->nullable()->after('design_type_id');
            $table->string('category_id')->nullable()->after('machine_type_id');
            $table->boolean('show_price')->default(true)->after('category_id');
            $table->json('details')->nullable()->after('show_price');
            $table->json('images')->nullable()->after('details');
            $table->json('files')->nullable()->after('images');
        });
    }

    public function down(): void
    {
        Schema::table('designs', function (Blueprint $table) {
            $table->dropColumn([
                'machine_type_id',
                'category_id',
                'show_price',
                'details',
                'images',
                'files',
            ]);
        });
    }
};
