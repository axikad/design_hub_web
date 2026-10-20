<?php

namespace Tests\Feature;

use App\Models\Design;
use App\Models\DesignCategory;
use App\Models\DesignType;
use App\Models\DesignVariant;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class DesignVariantStructureTest extends TestCase
{
    use RefreshDatabase;

    public function test_design_variants_store_a_type_and_its_specifications(): void
    {
        $this->assertTrue(Schema::hasColumns('design_variants', [
            'design_id',
            'design_type_id',
            'name_or_number',
            'stitch_count',
            'needle',
            'area',
            'height',
            'width',
            'sort_order',
        ]));

        $user = User::factory()->create();
        $type = DesignType::query()->create([
            'design_category_id' => DesignCategory::query()->create([
                'name' => 'Saree',
            ])->id,
            'name' => 'Pallu',
        ]);
        $design = Design::query()->create([
            'user_id' => $user->id,
            'title' => 'Test design',
            'price_paise' => 1000,
            'size' => '6 x 6',
            'color' => 'Gold',
        ]);

        $variant = $design->variants()->create([
            'design_type_id' => $type->id,
            'name_or_number' => 'Pallu 101',
            'stitch_count' => 12000,
            'needle' => 12,
            'area' => 36,
            'height' => 6,
            'width' => 6,
        ]);

        $this->assertInstanceOf(DesignVariant::class, $variant);
        $this->assertTrue($variant->type->is($type));
        $this->assertTrue($variant->design->is($design));
        $this->assertSame(12, $variant->needle);
    }
}
