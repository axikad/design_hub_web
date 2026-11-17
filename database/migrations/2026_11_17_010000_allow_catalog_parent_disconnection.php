<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('machine_types', function (Blueprint $table) {
            $table->dropForeign(['machine_id']);
        });
        Schema::table('machine_types', function (Blueprint $table) {
            $table->foreignId('machine_id')->nullable()->change();
            $table->foreign('machine_id')->references('id')->on('machines')->nullOnDelete();
        });

        Schema::table('design_types', function (Blueprint $table) {
            $table->dropForeign(['design_category_id']);
        });
        Schema::table('design_types', function (Blueprint $table) {
            $table->foreignId('design_category_id')->nullable()->change();
            $table->foreign('design_category_id')->references('id')->on('design_categories')->nullOnDelete();
        });
    }

    public function down(): void
    {
        if (DB::table('machine_types')->whereNull('machine_id')->exists()
            || DB::table('design_types')->whereNull('design_category_id')->exists()) {
            throw new RuntimeException('Reassign disconnected catalog records before rolling back this migration.');
        }

        Schema::table('machine_types', function (Blueprint $table) {
            $table->dropForeign(['machine_id']);
            $table->foreignId('machine_id')->nullable(false)->change();
            $table->foreign('machine_id')->references('id')->on('machines')->cascadeOnDelete();
        });

        Schema::table('design_types', function (Blueprint $table) {
            $table->dropForeign(['design_category_id']);
            $table->foreignId('design_category_id')->nullable(false)->change();
            $table->foreign('design_category_id')->references('id')->on('design_categories')->cascadeOnDelete();
        });
    }
};
