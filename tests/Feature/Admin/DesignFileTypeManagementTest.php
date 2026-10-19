<?php

namespace Tests\Feature\Admin;

use App\Models\Design;
use App\Models\DesignFileType;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class DesignFileTypeManagementTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_can_create_and_update_a_design_file_type(): void
    {
        $admin = User::factory()->create(['is_admin' => true]);

        $this->actingAs($admin)->post('/admin/design-file-types', [
            'name' => 'PES',
            'extension' => '.PES',
            'isActive' => true,
            'sortOrder' => 4,
        ])->assertRedirect(route('admin.design-file-types.index'));

        $fileType = DesignFileType::query()->where('extension', 'pes')->firstOrFail();

        $this->actingAs($admin)->put('/admin/design-file-types/'.$fileType->public_id, [
            'name' => 'PES File',
            'extension' => 'pes',
            'isActive' => false,
            'sortOrder' => 5,
        ])->assertRedirect(route('admin.design-file-types.index'));

        $this->assertDatabaseHas('design_file_types', [
            'id' => $fileType->id,
            'name' => 'PES File',
            'extension' => 'pes',
            'is_active' => false,
            'sort_order' => 5,
        ]);
    }

    public function test_file_type_used_by_a_design_cannot_be_deleted(): void
    {
        $admin = User::factory()->create(['is_admin' => true]);
        $owner = User::factory()->create();
        $fileType = DesignFileType::query()->create([
            'name' => 'DST',
            'extension' => 'dst',
        ]);
        Design::query()->create([
            'user_id' => $owner->id,
            'design_file_type_id' => $fileType->id,
            'title' => 'Protected design',
            'price_paise' => 1000,
            'size' => '6 x 6',
            'color' => 'Gold',
        ]);

        $this->actingAs($admin)
            ->delete('/admin/design-file-types/'.$fileType->public_id)
            ->assertSessionHasErrors('designFileType');

        $this->assertDatabaseHas('design_file_types', ['id' => $fileType->id]);
    }
}
