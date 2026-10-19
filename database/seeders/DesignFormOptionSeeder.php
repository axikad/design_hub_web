<?php

namespace Database\Seeders;

use App\Models\DesignCategory;
use App\Models\Machine;
use App\Models\MachineType;
use Illuminate\Database\Seeder;

class DesignFormOptionSeeder extends Seeder
{
    public function run(): void
    {
        $machine = Machine::query()->updateOrCreate(
            ['public_id' => 'embroidery'],
            ['name' => 'Embroidery', 'is_active' => true, 'sort_order' => 1],
        );

        $machineTypes = collect([
            ['public_id' => 'machine-embroidery', 'name' => 'Embroidery machine'],
            ['public_id' => 'multi-head', 'name' => 'Multi-head machine'],
        ])->map(fn (array $option, int $index) => MachineType::query()->updateOrCreate(
            ['public_id' => $option['public_id']],
            [
                'machine_id' => $machine->id,
                'name' => $option['name'],
                'is_active' => true,
                'sort_order' => $index + 1,
            ],
        ));

        collect([
            ['public_id' => 'neckline', 'name' => 'Neckline'],
            ['public_id' => 'motif', 'name' => 'Motif'],
            ['public_id' => 'border', 'name' => 'Border'],
        ])->each(function (array $option, int $index) use ($machineTypes) {
            $category = DesignCategory::query()->updateOrCreate(
                ['public_id' => $option['public_id']],
                ['name' => $option['name'], 'is_active' => true, 'sort_order' => $index + 1],
            );
            $category->machineTypes()->sync($machineTypes->pluck('id'));
        });
    }
}
