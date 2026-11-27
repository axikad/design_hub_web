<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('design_variants', function (Blueprint $table) {
            $table->string('specification_mode', 32)
                ->default('technical')
                ->after('custom_type_name');
        });

        DB::table('design_variants')
            ->whereNotNull('image_path')
            ->where(function ($query) {
                $query->whereNull('stitch_count')->orWhere('stitch_count', 0);
            })
            ->where(function ($query) {
                $query->whereNull('needle')->orWhere('needle', 0);
            })
            ->where(function ($query) {
                $query->whereNull('area')->orWhere('area', 0);
            })
            ->where(function ($query) {
                $query->whereNull('height')->orWhere('height', 0);
            })
            ->where(function ($query) {
                $query->whereNull('width')->orWhere('width', 0);
            })
            ->update(['specification_mode' => 'design_property']);
    }

    public function down(): void
    {
        Schema::table('design_variants', function (Blueprint $table) {
            $table->dropColumn('specification_mode');
        });
    }
};
