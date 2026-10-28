<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::transaction(function (): void {
            foreach (['machines', 'design_categories', 'design_types', 'design_file_types'] as $table) {
                $this->normalize($table);
            }

            DB::table('machine_types')
                ->distinct()
                ->orderBy('machine_id')
                ->pluck('machine_id')
                ->each(fn ($machineId) => $this->normalize('machine_types', ['machine_id' => $machineId]));
        });
    }

    public function down(): void
    {
        // Position normalization cannot be reversed without restoring a database backup.
    }

    private function normalize(string $table, array $scope = []): void
    {
        $query = DB::table($table);

        foreach ($scope as $column => $value) {
            $query->where($column, $value);
        }

        $query
            ->orderBy('sort_order')
            ->orderBy('name')
            ->orderBy('id')
            ->pluck('id')
            ->each(fn ($id, int $index) => DB::table($table)->where('id', $id)->update(['sort_order' => $index + 1]));
    }
};
