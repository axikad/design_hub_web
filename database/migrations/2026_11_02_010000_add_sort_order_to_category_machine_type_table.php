<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('category_machine_type', function (Blueprint $table) {
            $table->unsignedInteger('sort_order')->default(0);
            $table->index(['machine_type_id', 'sort_order'], 'category_machine_type_order_index');
        });

        DB::table('machine_types')->orderBy('id')->pluck('id')->each(function (int $machineTypeId): void {
            DB::table('category_machine_type')
                ->join('design_categories', 'design_categories.id', '=', 'category_machine_type.design_category_id')
                ->where('category_machine_type.machine_type_id', $machineTypeId)
                ->orderBy('design_categories.sort_order')
                ->orderBy('design_categories.name')
                ->orderBy('design_categories.id')
                ->pluck('category_machine_type.design_category_id')
                ->each(fn (int $categoryId, int $index) => DB::table('category_machine_type')
                    ->where('machine_type_id', $machineTypeId)
                    ->where('design_category_id', $categoryId)
                    ->update(['sort_order' => $index + 1]));
        });
    }

    public function down(): void
    {
        Schema::table('category_machine_type', function (Blueprint $table) {
            $table->dropIndex('category_machine_type_order_index');
            $table->dropColumn('sort_order');
        });
    }
};
