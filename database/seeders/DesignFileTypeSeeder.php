<?php

namespace Database\Seeders;

use App\Models\DesignFileType;
use App\Models\Machine;
use Illuminate\Database\Seeder;

class DesignFileTypeSeeder extends Seeder
{
    public function run(): void
    {
        $fileTypes = [
            ['public_id' => 'emb', 'name' => 'EMB', 'extension' => 'emb'],
            ['public_id' => 'dst', 'name' => 'DST', 'extension' => 'dst'],
            ['public_id' => 'dhb', 'name' => 'DHB', 'extension' => 'dhb'],
        ];

        $models = collect($fileTypes)->map(function (array $fileType, int $index) {
            return DesignFileType::query()->updateOrCreate(
                ['public_id' => $fileType['public_id']],
                [
                    'name' => $fileType['name'],
                    'extension' => $fileType['extension'],
                    'is_active' => true,
                    'sort_order' => $index + 1,
                ],
            );
        });

        $fileTypeIds = $models->pluck('id')->all();
        Machine::query()->each(fn (Machine $machine) => $machine->designFileTypes()->syncWithoutDetaching($fileTypeIds));
    }
}
