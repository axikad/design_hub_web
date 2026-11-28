<?php

namespace Tests\Feature\Api;

use App\Models\Design;
use App\Models\DesignCategory;
use App\Models\DesignType;
use App\Models\Machine;
use App\Models\MachineType;
use App\Models\Purchase;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class DesignBrowseFiltersApiTest extends TestCase
{
    use RefreshDatabase;

    public function test_browse_filter_catalog_returns_complete_active_relationships_without_requiring_designs(): void
    {
        $catalog = $this->catalog();

        $this->getJson('/api/v1/catalog/browse-filters')
            ->assertOk()
            ->assertJsonPath('data.machines.0.id', $catalog['machine']->public_id)
            ->assertJsonPath('data.machines.0.name', $catalog['machine']->name)
            ->assertJsonPath('data.machines.0.machineTypeIds.0', $catalog['machineType']->public_id)
            ->assertJsonPath('data.machineTypes.0.id', $catalog['machineType']->public_id)
            ->assertJsonPath('data.machineTypes.0.categoryIds.0', $catalog['category']->public_id)
            ->assertJsonPath('data.categories.0.machineTypeIds.0', $catalog['machineType']->public_id)
            ->assertJsonPath('data.categories.0.designTypeIds.0', $catalog['firstType']->public_id)
            ->assertJsonFragment([
                'id' => $catalog['unusedType']->public_id,
                'name' => $catalog['unusedType']->name,
                'categoryIds' => [$catalog['category']->public_id],
            ])
            ->assertJsonMissingPath('meta')
            ->assertJsonMissingPath('links');
    }

    public function test_design_list_filters_by_top_level_machine_for_current_and_legacy_records(): void
    {
        $catalog = $this->catalog();
        $current = $this->design($catalog, ['title' => 'Current machine design']);
        $legacy = $this->design($catalog, ['title' => 'Legacy machine design']);
        $legacy->update(['machine_id' => null]);

        $otherMachine = Machine::query()->create(['public_id' => 'knitting', 'name' => 'Knitting']);
        $otherMachineType = MachineType::query()->create([
            'machine_id' => $otherMachine->id,
            'public_id' => 'flat-knitting',
            'name' => 'Flat Knitting',
        ]);
        $otherCategory = DesignCategory::query()->create(['public_id' => 'knitwear', 'name' => 'Knitwear']);
        $otherCategory->machineTypes()->attach($otherMachineType);
        $otherType = DesignType::query()->create([
            'public_id' => 'knit-pattern',
            'design_category_id' => $otherCategory->id,
            'name' => 'Knit Pattern',
        ]);
        $otherCatalog = [
            'machine' => $otherMachine,
            'machineType' => $otherMachineType,
            'category' => $otherCategory,
            'firstType' => $otherType,
        ];
        $this->design($otherCatalog, ['title' => 'Other machine design']);

        $response = $this->getJson('/api/v1/designs?'.http_build_query([
            'machine_id' => $catalog['machine']->public_id,
        ]))->assertOk();

        $this->assertEqualsCanonicalizing(
            [$current->public_id, $legacy->public_id],
            collect($response->json('data'))->pluck('id')->all(),
        );
    }

    public function test_design_list_filters_machine_type_category_and_design_type(): void
    {
        $catalog = $this->catalog();
        $matching = $this->design($catalog, ['title' => 'Matching']);
        $otherCategory = DesignCategory::query()->create(['public_id' => 'other-category', 'name' => 'Other']);
        $otherCategory->machineTypes()->attach($catalog['machineType']);
        $otherType = DesignType::query()->create([
            'public_id' => 'other-type',
            'design_category_id' => $otherCategory->id,
            'name' => 'Other type',
        ]);
        $this->design($catalog, [
            'title' => 'Other',
            'category_id' => $otherCategory->public_id,
            'variant_type' => $otherType,
        ]);

        $query = http_build_query([
            'machine_type_id' => $catalog['machineType']->public_id,
            'category_ids' => [$catalog['category']->public_id],
            'design_type_ids' => [$catalog['firstType']->public_id],
        ]);

        $this->getJson('/api/v1/designs?'.$query)
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.id', $matching->public_id)
            ->assertJsonPath('data.0.machineTypeId', $catalog['machineType']->public_id)
            ->assertJsonPath('data.0.categoryId', $catalog['category']->public_id)
            ->assertJsonPath('data.0.designTypes.0.typeId', $catalog['firstType']->public_id)
            ->assertJsonPath('data.0.designTypes.0.details.stitchCount', 10000);
    }

    public function test_specification_filters_match_values_on_the_same_design_type_assignment(): void
    {
        $catalog = $this->catalog();
        $design = $this->design($catalog, ['title' => 'Two variants']);
        $design->variants()->create([
            'design_type_id' => $catalog['secondType']->id,
            'name_or_number' => 'Second',
            'stitch_count' => 60000,
            'needle' => 8,
            'area' => 250,
            'height' => 10,
            'width' => 25,
            'sort_order' => 2,
        ]);

        $mismatchedAssignment = http_build_query([
            'design_type_ids' => [$catalog['firstType']->public_id],
            'stitch_ranges' => ['50000:75000'],
            'needle_values' => [8],
            'area_ranges' => ['200:300'],
        ]);
        $matchingAssignment = http_build_query([
            'design_type_ids' => [$catalog['secondType']->public_id],
            'stitch_ranges' => ['50000:75000'],
            'needle_values' => [8],
            'area_ranges' => ['200:300'],
        ]);

        $this->getJson('/api/v1/designs?'.$mismatchedAssignment)
            ->assertOk()
            ->assertJsonCount(0, 'data');

        $this->getJson('/api/v1/designs?'.$matchingAssignment)
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.id', $design->public_id);
    }

    public function test_stitch_area_and_needle_boundaries_follow_filter_sheet_rules(): void
    {
        $catalog = $this->catalog();
        $lower = $this->design($catalog, [
            'title' => 'Lower',
            'stitch_count' => 24999,
            'needle' => 0,
            'area' => 99.99,
        ]);
        $next = $this->design($catalog, [
            'title' => 'Next',
            'stitch_count' => 25000,
            'needle' => 12,
            'area' => 100,
        ]);
        $final = $this->design($catalog, [
            'title' => 'Final',
            'stitch_count' => 250000,
            'needle' => 12,
            'area' => 500,
        ]);
        $this->design($catalog, [
            'title' => 'Above final',
            'stitch_count' => 250001,
            'needle' => 6,
            'area' => 499.99,
        ]);

        $this->assertFilteredIds(['stitch_ranges' => ['0:25000']], [$lower->public_id]);
        $this->assertFilteredIds(['stitch_ranges' => ['25000:50000']], [$next->public_id]);
        $this->assertFilteredIds(['stitch_ranges' => ['225000:250000']], [$final->public_id]);
        $this->assertFilteredIds(['area_ranges' => ['0:100']], [$lower->public_id]);
        $this->assertFilteredIds(['area_ranges' => ['100:200']], [$next->public_id]);
        $this->assertFilteredIds(['area_ranges' => ['500:*']], [$final->public_id]);
        $this->assertFilteredIds(['needle_values' => [0]], [$lower->public_id]);
        $this->assertFilteredIds(['needle_values' => [12]], [$next->public_id, $final->public_id]);
    }

    public function test_multi_select_values_use_or_and_filters_are_applied_before_pagination(): void
    {
        $catalog = $this->catalog();
        $first = $this->design($catalog, ['title' => 'First', 'stitch_count' => 10000]);
        $second = $this->design($catalog, ['title' => 'Second', 'stitch_count' => 30000]);
        $third = $this->design($catalog, ['title' => 'Third', 'stitch_count' => 55000]);
        $this->design($catalog, ['title' => 'Excluded', 'stitch_count' => 90000]);

        $query = http_build_query([
            'stitch_ranges' => ['0:25000', '25000:50000', '50000:75000'],
            'page' => 1,
            'per_page' => 2,
        ]);

        $response = $this->getJson('/api/v1/designs?'.$query)
            ->assertOk()
            ->assertJsonCount(2, 'data')
            ->assertJsonPath('meta.total', 3)
            ->assertJsonPath('meta.per_page', 2)
            ->assertJsonPath('meta.last_page', 2)
            ->assertJsonStructure(['links' => ['first', 'last', 'prev', 'next']]);

        $this->assertEqualsCanonicalizing(
            [$first->public_id, $second->public_id, $third->public_id],
            collect($response->json('data'))->pluck('id')
                ->merge(collect($this->getJson('/api/v1/designs?'.$query.'&page=2')->json('data'))->pluck('id'))
                ->all(),
        );
    }

    public function test_invalid_filter_values_return_validation_errors(): void
    {
        $this->getJson('/api/v1/designs?'.http_build_query([
            'machine_id' => 'missing-machine',
            'stitch_ranges' => ['10:20'],
            'needle_values' => [13],
            'area_ranges' => ['500:600'],
        ]))
            ->assertUnprocessable()
            ->assertJsonValidationErrors([
                'machine_id',
                'stitch_ranges.0',
                'needle_values.0',
                'area_ranges.0',
            ]);
    }

    public function test_design_list_sorts_before_pagination(): void
    {
        $catalog = $this->catalog();
        $low = $this->design($catalog, ['title' => 'Low price', 'price_paise' => 1000]);
        $middle = $this->design($catalog, ['title' => 'Middle price', 'price_paise' => 2000]);
        $high = $this->design($catalog, ['title' => 'High price', 'price_paise' => 3000]);

        $ascending = $this->getJson('/api/v1/designs?'.http_build_query([
            'sort' => 'price-asc',
            'per_page' => 2,
        ]))->assertOk();

        $this->assertSame(
            [$low->public_id, $middle->public_id],
            collect($ascending->json('data'))->pluck('id')->all(),
        );
        $ascending->assertJsonPath('meta.total', 3)->assertJsonPath('meta.last_page', 2);

        $descending = $this->getJson('/api/v1/designs?'.http_build_query([
            'sort' => 'price-desc',
            'per_page' => 3,
        ]))->assertOk();

        $this->assertSame(
            [$high->public_id, $middle->public_id, $low->public_id],
            collect($descending->json('data'))->pluck('id')->all(),
        );
    }

    public function test_popular_sort_uses_paid_purchase_count_only(): void
    {
        $catalog = $this->catalog();
        $popular = $this->design($catalog, ['title' => 'Popular']);
        $unpaid = $this->design($catalog, ['title' => 'Unpaid']);

        Purchase::query()->create([
            'buyer_id' => User::factory()->create()->id,
            'design_id' => $popular->id,
            'amount_paise' => $popular->price_paise,
            'status' => 'paid',
            'purchased_at' => now(),
        ]);
        Purchase::query()->create([
            'buyer_id' => User::factory()->create()->id,
            'design_id' => $unpaid->id,
            'amount_paise' => $unpaid->price_paise,
            'status' => 'pending',
        ]);

        $response = $this->getJson('/api/v1/designs?sort=popular')->assertOk();

        $this->assertSame($popular->public_id, $response->json('data.0.id'));
    }

    public function test_invalid_sort_returns_validation_error(): void
    {
        $this->getJson('/api/v1/designs?sort=random')
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['sort']);
    }

    /** @return array<string, Machine|MachineType|DesignCategory|DesignType> */
    private function catalog(): array
    {
        $machine = Machine::query()->create(['public_id' => 'embroidery', 'name' => 'Embroidery']);
        $machineType = MachineType::query()->create([
            'machine_id' => $machine->id,
            'public_id' => 'multi-head',
            'name' => 'Multi Head',
        ]);
        $category = DesignCategory::query()->create(['public_id' => 'saree', 'name' => 'Saree']);
        $category->machineTypes()->attach($machineType, ['sort_order' => 1]);
        $firstType = DesignType::query()->create([
            'public_id' => 'pallu',
            'design_category_id' => $category->id,
            'name' => 'Pallu',
            'sort_order' => 1,
        ]);
        $secondType = DesignType::query()->create([
            'public_id' => 'scut',
            'design_category_id' => $category->id,
            'name' => 'Scut',
            'sort_order' => 2,
        ]);
        $unusedType = DesignType::query()->create([
            'public_id' => 'lace',
            'design_category_id' => $category->id,
            'name' => 'Lace',
            'sort_order' => 3,
        ]);

        return compact('machine', 'machineType', 'category', 'firstType', 'secondType', 'unusedType');
    }

    private function design(array $catalog, array $attributes = []): Design
    {
        $variantType = $attributes['variant_type'] ?? $catalog['firstType'];
        unset($attributes['variant_type']);

        $variant = [
            'design_type_id' => $variantType->id,
            'name_or_number' => $attributes['title'] ?? 'Filtered design',
            'stitch_count' => $attributes['stitch_count'] ?? 10000,
            'needle' => $attributes['needle'] ?? 4,
            'area' => $attributes['area'] ?? 50,
            'height' => 5,
            'width' => 10,
            'sort_order' => 1,
        ];
        unset($attributes['stitch_count'], $attributes['needle'], $attributes['area']);

        $design = Design::query()->create(array_merge([
            'user_id' => User::factory()->create()->id,
            'machine_id' => $catalog['machine']->id,
            'machine_type_id' => $catalog['machineType']->public_id,
            'category_id' => $catalog['category']->public_id,
            'title' => 'Filtered design',
            'price_paise' => 10000,
            'size' => '10 x 5',
            'color' => 'Blue',
        ], $attributes));
        $design->variants()->create($variant);

        return $design;
    }

    /** @param list<string> $expectedIds */
    private function assertFilteredIds(array $filters, array $expectedIds): void
    {
        $response = $this->getJson('/api/v1/designs?'.http_build_query($filters))->assertOk();

        $this->assertEqualsCanonicalizing($expectedIds, collect($response->json('data'))->pluck('id')->all());
    }
}
