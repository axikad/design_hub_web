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
            ['description' => 'Physical embroidery hoop dimensions', 'sort_order' => 10],
        );

        $color = DesignCategory::query()->firstOrCreate(
            ['name' => 'Color family'],
            ['description' => 'Primary thread palette', 'sort_order' => 20],
        );

        $style = DesignCategory::query()->firstOrCreate(
            ['name' => 'Design style'],
            ['description' => 'Motif classification', 'sort_order' => 30],
        );

        $this->seedTypes($hoop, ['4-inch hoop', '6-inch hoop', '8-inch hoop', 'Saree border', 'Motif']);
        $this->seedTypes($color, ['Maroon', 'Gold', 'Ivory', 'Forest', 'Navy', 'Blush', 'Black', 'Copper']);
        $this->seedTypes($style, ['Floral', 'Geometric', 'Traditional', 'Minimal']);
    }

    private function seedTypes(DesignCategory $category, array $names): void
    {
        foreach ($names as $index => $name) {
            DesignType::query()->firstOrCreate(
                ['design_category_id' => $category->id, 'name' => $name],
                ['sort_order' => $index + 1],
            );
        }
    }
}
