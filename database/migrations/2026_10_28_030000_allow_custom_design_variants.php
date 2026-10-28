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
            $table->foreignId('design_type_id')->nullable()->change();
            $table->string('custom_type_name')->nullable()->after('design_type_id');
        });
    }

    public function down(): void
    {
        DB::table('design_variants')->whereNull('design_type_id')->delete();

        Schema::table('design_variants', function (Blueprint $table) {
            $table->dropColumn('custom_type_name');
            $table->foreignId('design_type_id')->nullable(false)->change();
        });
    }
};
