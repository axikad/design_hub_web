<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('designs', function (Blueprint $table) {
            $table->decimal('sequence_type', 12, 2)->default(0)->after('color');
        });

        $sequenceTypes = [];

        foreach (DB::table('design_variants')->lazyById() as $variant) {
            if (! isset($sequenceTypes[$variant->design_id])) {
                $sequenceTypes[$variant->design_id] = $variant->sequence_type;
            } elseif ((float) $sequenceTypes[$variant->design_id] === 0.0 && (float) $variant->sequence_type !== 0.0) {
                $sequenceTypes[$variant->design_id] = $variant->sequence_type;
            }
        }

        foreach ($sequenceTypes as $designId => $sequenceType) {
            DB::table('designs')
                ->where('id', $designId)
                ->update(['sequence_type' => $sequenceType]);
        }

        Schema::table('design_variants', function (Blueprint $table) {
            $table->dropColumn('sequence_type');
        });
    }

    public function down(): void
    {
        Schema::table('design_variants', function (Blueprint $table) {
            $table->decimal('sequence_type', 12, 2)->default(0)->after('needle');
        });

        foreach (DB::table('design_variants')->lazyById() as $variant) {
            DB::table('design_variants')
                ->where('id', $variant->id)
                ->update([
                    'sequence_type' => DB::table('designs')
                        ->where('id', $variant->design_id)
                        ->value('sequence_type') ?? 0,
                ]);
        }

        Schema::table('designs', function (Blueprint $table) {
            $table->dropColumn('sequence_type');
        });
    }
};
