<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('machines', function (Blueprint $table) {
            $table->json('design_form_config')->nullable()->after('image_path');
        });

        Schema::table('designs', function (Blueprint $table) {
            $table->foreignId('machine_id')
                ->nullable()
                ->after('design_file_type_id')
                ->constrained('machines')
                ->nullOnDelete();
        });

        DB::table('designs')
            ->whereNotNull('machine_type_id')
            ->orderBy('id')
            ->chunkById(500, function ($designs) {
                $machineTypeIds = $designs->pluck('machine_type_id')->filter()->unique()->values();
                $machineIds = DB::table('machine_types')
                    ->whereIn('public_id', $machineTypeIds)
                    ->pluck('machine_id', 'public_id');

                foreach ($designs as $design) {
                    $machineId = $machineIds->get($design->machine_type_id);

                    if ($machineId !== null) {
                        DB::table('designs')->where('id', $design->id)->update(['machine_id' => $machineId]);
                    }
                }
            });
    }

    public function down(): void
    {
        Schema::table('designs', function (Blueprint $table) {
            $table->dropConstrainedForeignId('machine_id');
        });

        Schema::table('machines', function (Blueprint $table) {
            $table->dropColumn('design_form_config');
        });
    }
};
