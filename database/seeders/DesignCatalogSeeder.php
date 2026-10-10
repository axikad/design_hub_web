<?php

namespace Database\Seeders;

use App\Models\DesignCategory;
use App\Models\DesignType;
use Illuminate\Database\Seeder;

class DesignCatalogSeeder extends Seeder
{
    public function run(): void
    {
        $hoop = DesignCategory::query()->firstOrCreate(
            ['name' => 'Hoop size'],
            ['description' => 'Physical embroidery hoop dimensions', 'sort_order' => 1],
        );

        $colorCat = DesignCategory::query()->firstOrCreate(
            ['name' => 'Color family'],
            ['description' => 'Primary thread palette', 'sort_order' => 2],
        );

        $style = DesignCategory::query()->firstOrCreate(
            ['name' => 'Design style'],
            ['description' => 'Motif classification', 'sort_order' => 3],
        );

        foreach (['4″ hoop', '6″ hoop', '8″ hoop', 'Saree border', 'Motif'] as $index => $name) {
            DesignType::query()->firstOrCreate(
                ['design_category_id' => $hoop->id, 'name' => $name],
                ['sort_order' => $index + 1],
            );
        }

        foreach (['Maroon', 'Gold', 'Ivory', 'Forest', 'Navy', 'Blush', 'Black', 'Copper'] as $index => $name) {
            DesignType::query()->firstOrCreate(
                ['design_category_id' => $colorCat->id, 'name' => $name],
                ['sort_order' => $index + 1],
            );
        }

        foreach (['Floral', 'Geometric', 'Traditional', 'Minimal'] as $index => $name) {
            DesignType::query()->firstOrCreate(
                ['design_category_id' => $style->id, 'name' => $name],
                ['sort_order' => $index + 1],
            );
        }
    }
}
