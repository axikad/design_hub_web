<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('category_design_type', function (Blueprint $table) {
            $table->unsignedInteger('sort_order')->default(0);
            $table->index(['design_category_id', 'sort_order']);
        });

        DB::table('design_categories')->orderBy('id')->pluck('id')->each(function (int $categoryId): void {
            DB::table('category_design_type')
                ->join('design_types', 'design_types.id', '=', 'category_design_type.design_type_id')
                ->where('category_design_type.design_category_id', $categoryId)
                ->orderBy('design_types.sort_order')
                ->orderBy('design_types.name')
                ->pluck('category_design_type.design_type_id')
                ->values()
                ->each(function (int $designTypeId, int $index) use ($categoryId): void {
                    DB::table('category_design_type')
                        ->where('design_category_id', $categoryId)
                        ->where('design_type_id', $designTypeId)
                        ->update(['sort_order' => $index + 1]);
                });
        });
    }

    public function down(): void
    {
        Schema::table('category_design_type', function (Blueprint $table) {
            $table->dropIndex(['design_category_id', 'sort_order']);
            $table->dropColumn('sort_order');
        });
    }
};
