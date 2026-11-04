<?php

namespace Tests\Feature\Admin;

use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class SiteSettingManagementTest extends TestCase
{
    use RefreshDatabase;

    public function test_mobile_registration_role_dropdown_always_has_a_safe_marketplace_role(): void
    {
        $admin = User::factory()->superAdmin()->create();

        $this->actingAs($admin)
            ->get(route('admin.settings.site.edit'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('admin/settings/site')
                ->has('mobileRegistrationRoles', 1)
                ->where('mobileRegistrationRoles.0.slug', 'marketplace_user'));

        $marketplaceRole = Role::query()->where('slug', 'marketplace_user')->firstOrFail();
        $this->assertTrue($marketplaceRole->hasPermission('dashboard.read'));
        $this->assertTrue($marketplaceRole->hasPermission('marketplace.manage_own'));
    }
}
