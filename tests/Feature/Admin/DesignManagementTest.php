<?php

namespace Tests\Feature\Admin;

use App\Models\DesignCategory;
use App\Models\DesignFileType;
use App\Models\DesignType;
use App\Models\Machine;
use App\Models\MachineType;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class DesignManagementTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_can_create_a_design_with_the_complete_catalog_selection(): void
    {
        Storage::fake('public');
        Storage::fake('local');
        $admin = User::factory()->create(['is_admin' => true]);
        $owner = User::factory()->create();
        [$machine, $machineType, $category, $type, $fileType] = $this->catalog();

        $this->actingAs($admin)->get('/admin/designs/create')->assertOk();

        $this->actingAs($admin)->post('/admin/designs', [
            'ownerId' => $owner->public_id,
            'machineId' => $machine->public_id,
            'machineTypeId' => $machineType->public_id,
            'categoryId' => $category->public_id,
            'typeId' => $type->public_id,
            'designFileTypeId' => $fileType->public_id,
            'title' => 'Admin floral design',
            'priceRupees' => '299.00',
            'size' => '6 x 6',
            'color' => 'Gold',
            'previewImage' => UploadedFile::fake()->image('preview.jpg'),
            'stitchFile' => UploadedFile::fake()->create('original.emb', 100, 'application/octet-stream'),
        ])->assertRedirect(route('admin.designs.index'));

        $this->assertDatabaseHas('designs', [
            'user_id' => $owner->id,
            'design_type_id' => $type->id,
            'design_file_type_id' => $fileType->id,
            'machine_type_id' => $machineType->public_id,
            'category_id' => $category->public_id,
            'title' => 'Admin floral design',
            'file_name' => 'original.emb',
        ]);
    }

    public function test_admin_design_rejects_a_machine_type_from_another_machine(): void
    {
        Storage::fake('public');
        Storage::fake('local');
        $admin = User::factory()->create(['is_admin' => true]);
        $owner = User::factory()->create();
        [, $machineType, $category, $type, $fileType] = $this->catalog();
        $differentMachine = Machine::query()->create(['name' => 'Different Machine']);

        $this->actingAs($admin)->post('/admin/designs', [
            'ownerId' => $owner->public_id,
            'machineId' => $differentMachine->public_id,
            'machineTypeId' => $machineType->public_id,
            'categoryId' => $category->public_id,
            'typeId' => $type->public_id,
            'designFileTypeId' => $fileType->public_id,
            'title' => 'Invalid selection',
            'priceRupees' => '299.00',
            'size' => '6 x 6',
            'color' => 'Gold',
            'previewImage' => UploadedFile::fake()->image('preview.jpg'),
            'stitchFile' => UploadedFile::fake()->create('original.emb', 100, 'application/octet-stream'),
        ])->assertSessionHasErrors('machineTypeId');

        $this->assertDatabaseMissing('designs', ['title' => 'Invalid selection']);
    }

    /** @return array{Machine, MachineType, DesignCategory, DesignType, DesignFileType} */
    private function catalog(): array
    {
        $machine = Machine::query()->create(['name' => 'Embroidery']);
        $machineType = MachineType::query()->create([
            'machine_id' => $machine->id,
            'name' => 'Multi Head',
        ]);
        $category = DesignCategory::query()->create(['name' => 'Saree']);
        $type = DesignType::query()->create([
            'design_category_id' => $category->id,
            'name' => 'Pallu',
        ]);
        $machineType->categories()->attach($category);
        $fileType = DesignFileType::query()->create([
            'name' => 'DST',
            'extension' => 'dst',
        ]);

        return [$machine, $machineType, $category, $type, $fileType];
    }
}
