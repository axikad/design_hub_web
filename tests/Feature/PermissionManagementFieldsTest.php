<?php

namespace Tests\Feature;

use App\Models\Module;
use App\Models\Permission;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PermissionManagementFieldsTest extends TestCase
{
    use RefreshDatabase;

    public function test_permission_management_fields_can_be_stored(): void
    {
        $actor = User::factory()->create();
        $module = Module::query()->create([
            'name' => 'Test Reports',
            'slug' => 'test_reports',
        ]);

        $permission = Permission::query()->create([
            'module_id' => $module->id,
            'name' => 'View test reports',
            'slug' => 'test_reports.read',
            'action' => 'read',
            'description' => 'View the user list and user details.',
            'created_by' => $actor->id,
            'updated_by' => $actor->id,
        ]);

        $this->assertSame('read', $permission->action);
        $this->assertTrue($permission->is_active);
        $this->assertTrue($permission->creator->is($actor));
        $this->assertTrue($permission->updater->is($actor));
    }
}
