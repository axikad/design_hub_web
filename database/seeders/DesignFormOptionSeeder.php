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
        $embroidery = Machine::query()->updateOrCreate(
            ['public_id' => 'embroidery'],
            ['name' => 'Embroidery', 'is_active' => true, 'sort_order' => 1],
        );

        $airJet = Machine::query()->updateOrCreate(
            ['public_id' => 'air-jet-loom'],
            ['name' => 'Air Jet Loom', 'is_active' => true, 'sort_order' => 2],
        );

        $machineTypes = collect([
            ['machine_id' => $embroidery->id, 'public_id' => 'machine-embroidery', 'name' => 'Multi Head', 'sort_order' => 1],
            ['machine_id' => $embroidery->id, 'public_id' => 'sequin', 'name' => 'Sequin', 'sort_order' => 2],
            ['machine_id' => $embroidery->id, 'public_id' => 'multi-sequin', 'name' => 'Multi Sequin', 'sort_order' => 3],
            ['machine_id' => $airJet->id, 'public_id' => 'dobby-air-jet', 'name' => 'Dobby Air Jet', 'sort_order' => 4],
            ['machine_id' => $airJet->id, 'public_id' => 'jacquard-air-jet', 'name' => 'Jacquard Air Jet', 'sort_order' => 5],
        ])->map(fn (array $option) => MachineType::query()->updateOrCreate(
            ['public_id' => $option['public_id']],
            [
                'machine_id' => $option['machine_id'],
                'name' => $option['name'],
                'is_active' => true,
                'sort_order' => $option['sort_order'],
            ],
        ));

        // Retain the legacy row for existing designs, but hide the duplicate from selection APIs.
        MachineType::query()->where('public_id', 'multi-head')->update(['is_active' => false]);

        collect([
            ['public_id' => 'saree', 'name' => 'Saree'],
            ['public_id' => 'blouse-top', 'name' => 'Blouse / Top'],
            ['public_id' => 'general', 'name' => 'General'],
        ])->each(function (array $option, int $index) use ($machineTypes) {
            $category = DesignCategory::query()->updateOrCreate(
                ['public_id' => $option['public_id']],
                ['name' => $option['name'], 'is_active' => true, 'sort_order' => $index + 1],
            );
            $category->machineTypes()->sync($machineTypes->pluck('id'));
        });

        DesignCategory::query()
            ->whereIn('public_id', ['neckline', 'motif', 'border'])
            ->update(['is_active' => false]);
    }
}
