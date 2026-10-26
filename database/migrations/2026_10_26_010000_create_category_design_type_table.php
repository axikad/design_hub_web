<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('category_design_type', function (Blueprint $table) {
            $table->foreignId('design_category_id')->constrained()->cascadeOnDelete();
            $table->foreignId('design_type_id')->constrained()->cascadeOnDelete();
            $table->primary(['design_category_id', 'design_type_id']);
        });

        $rows = DB::table('design_types')
            ->whereNotNull('design_category_id')
            ->get(['id', 'design_category_id'])
            ->map(fn ($type) => [
                'design_category_id' => $type->design_category_id,
                'design_type_id' => $type->id,
            ])
            ->all();

        if ($rows !== []) {
            DB::table('category_design_type')->insert($rows);
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('category_design_type');
    }
};
