<?php

namespace Tests\Feature\Admin;

use App\Models\Design;
use App\Models\DesignCategory;
use App\Models\DesignType;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class DesignTypeManagementTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_can_connect_one_design_type_to_multiple_categories(): void
    {
        $admin = User::factory()->superAdmin()->create();
        $saree = DesignCategory::query()->create(['name' => 'Saree']);
        $blouse = DesignCategory::query()->create(['name' => 'Blouse']);

        $this->actingAs($admin)->post('/admin/types', [
            'categoryIds' => [$saree->public_id, $blouse->public_id],
            'name' => 'Border',
            'description' => 'Shared border',
            'isActive' => true,
            'sortOrder' => 1,
        ])->assertRedirect(route('admin.types.index'));

        $type = DesignType::query()->where('name', 'Border')->firstOrFail();
        $this->assertSame($saree->id, $type->design_category_id);
        $this->assertEqualsCanonicalizing(
            [$saree->id, $blouse->id],
            $type->categories()->pluck('design_categories.id')->all(),
        );

        $this->actingAs($admin)->put('/admin/types/'.$type->public_id, [
            'categoryIds' => [$blouse->public_id],
            'name' => 'Border',
            'description' => 'Blouse border',
            'isActive' => true,
            'sortOrder' => 1,
        ])->assertRedirect(route('admin.types.index'));

        $type->refresh();
        $this->assertSame($blouse->id, $type->design_category_id);
        $this->assertSame([$blouse->id], $type->categories()->pluck('design_categories.id')->all());
    }

    public function test_deleting_a_used_design_type_preserves_and_disconnects_design_records(): void
    {
        $admin = User::factory()->superAdmin()->create();
        $seller = User::factory()->create();
        $category = DesignCategory::query()->create(['name' => 'Saree']);
        $type = DesignType::query()->create([
            'design_category_id' => $category->id,
            'name' => 'Pallu',
        ]);
        $design = Design::query()->create([
            'user_id' => $seller->id,
            'design_type_id' => $type->id,
            'title' => 'Test design',
            'price_paise' => 10000,
            'size' => '',
            'color' => '',
            'details' => [],
            'images' => [],
            'files' => [],
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

        $this->actingAs($admin)
            ->delete('/admin/types/'.$type->public_id)
            ->assertRedirect(route('admin.types.index'))
            ->assertSessionHas('success');

        $this->assertDatabaseMissing('design_types', ['id' => $type->id]);
        $this->assertNull($design->fresh()->design_type_id);
        $this->assertNull($variant->fresh()->design_type_id);
        $this->assertSame('Pallu', $variant->fresh()->custom_type_name);
    }
}
