<?php

namespace Tests\Feature\Admin;

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
}
