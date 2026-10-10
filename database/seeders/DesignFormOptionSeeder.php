<?php

namespace Database\Seeders;

use App\Models\DesignMachineType;
use App\Models\DesignOptionCategory;
use Illuminate\Database\Seeder;

class DesignFormOptionSeeder extends Seeder
{
    public function run(): void
    {
        $machines = collect([
            ['public_id' => 'machine-embroidery', 'name' => 'Embroidery machine'],
            ['public_id' => 'multi-head', 'name' => 'Multi-head machine'],
        ])->map(fn (array $option, int $index) => DesignMachineType::query()->updateOrCreate(
            ['public_id' => $option['public_id']],
            ['name' => $option['name'], 'is_active' => true, 'sort_order' => $index + 1],
        ));

        collect([
            ['public_id' => 'neckline', 'name' => 'Neckline'],
            ['public_id' => 'motif', 'name' => 'Motif'],
            ['public_id' => 'border', 'name' => 'Border'],
        ])->each(function (array $option, int $index) use ($machines) {
            $category = DesignOptionCategory::query()->updateOrCreate(
                ['public_id' => $option['public_id']],
                ['name' => $option['name'], 'is_active' => true, 'sort_order' => $index + 1],
            );
            $category->machineTypes()->sync($machines->pluck('id'));
        });
    }
}
