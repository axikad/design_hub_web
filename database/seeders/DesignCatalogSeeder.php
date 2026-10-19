<?php

namespace Database\Seeders;

use App\Models\DesignCategory;
use App\Models\DesignType;
use Illuminate\Database\Seeder;

class DesignCatalogSeeder extends Seeder
{
    public function run(): void
    {
        $catalog = [
            [
                'public_id' => 'saree',
                'name' => 'Saree',
                'description' => 'Design placements for sarees',
                'types' => [
                    ['public_id' => 'pallu', 'name' => 'Pallu'],
                    ['public_id' => 'border', 'name' => 'Border'],
                    ['public_id' => 'all-over', 'name' => 'All Over'],
                ],
            ],
            [
                'public_id' => 'blouse-top',
                'name' => 'Blouse / Top',
                'description' => 'Design placements for blouses and tops',
                'types' => [
                    ['public_id' => 'neckline', 'name' => 'Neckline'],
                    ['public_id' => 'sleeve', 'name' => 'Sleeve'],
                    ['public_id' => 'back', 'name' => 'Back'],
                ],
            ],
            [
                'public_id' => 'general',
                'name' => 'General',
                'description' => 'Reusable standalone design placements',
                'types' => [
                    ['public_id' => 'motif', 'name' => 'Motif'],
                    ['public_id' => 'logo', 'name' => 'Logo'],
                    ['public_id' => 'patch', 'name' => 'Patch'],
                ],
            ],
        ];

        foreach ($catalog as $categoryIndex => $categoryData) {
            $category = DesignCategory::query()->updateOrCreate(
                ['public_id' => $categoryData['public_id']],
                [
                    'name' => $categoryData['name'],
                    'description' => $categoryData['description'],
                    'is_active' => true,
                    'sort_order' => $categoryIndex + 1,
                ],
            );

            foreach ($categoryData['types'] as $typeIndex => $typeData) {
                DesignType::query()->updateOrCreate(
                    ['public_id' => $typeData['public_id']],
                    [
                        'design_category_id' => $category->id,
                        'name' => $typeData['name'],
                        'is_active' => true,
                        'sort_order' => $typeIndex + 1,
                    ],
                );
            }
        }

        DesignCategory::query()
            ->whereIn('public_id', ['neckline', 'motif', 'border'])
            ->orWhereIn('name', ['Hoop size', 'Color family', 'Design style'])
            ->update(['is_active' => false]);
    }
}
