<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('machine_design_file_type', function (Blueprint $table) {
            $table->foreignId('machine_id')->constrained()->cascadeOnDelete();
            $table->foreignId('design_file_type_id')->constrained()->cascadeOnDelete();
            $table->primary(['machine_id', 'design_file_type_id']);
        });

        $machineIds = DB::table('machines')->pluck('id');
        $fileTypeIds = DB::table('design_file_types')->pluck('id');
        $rows = [];

        foreach ($machineIds as $machineId) {
            foreach ($fileTypeIds as $fileTypeId) {
                $rows[] = [
                    'machine_id' => $machineId,
                    'design_file_type_id' => $fileTypeId,
                ];
            }
        }

        if ($rows !== []) {
            DB::table('machine_design_file_type')->insert($rows);
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('machine_design_file_type');
    }
};
