<?php

namespace Tests\Feature;

use App\Models\Module;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ModuleManagementFieldsTest extends TestCase
{
    use RefreshDatabase;

    public function test_module_management_fields_and_defaults_can_be_stored(): void
    {
        $actor = User::factory()->create();

        $module = Module::query()->create([
            'name' => 'Test Content Management',
            'slug' => 'test_content_management',
            'description' => 'Manage application users.',
            'sort_order' => 10,
            'created_by' => $actor->id,
            'updated_by' => $actor->id,
        ]);

        $this->assertTrue($module->is_active);
        $this->assertSame(10, $module->sort_order);
        $this->assertTrue($module->creator->is($actor));
        $this->assertTrue($module->updater->is($actor));
    }
}
