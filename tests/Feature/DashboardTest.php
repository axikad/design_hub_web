<?php

namespace Tests\Feature;

use App\Models\Design;
use App\Models\Folder;
use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class DashboardTest extends TestCase
{
    use RefreshDatabase;

    public function test_guests_are_redirected_to_the_login_page()
    {
        $this->get('/dashboard')->assertRedirect('/login');
    }

    public function test_authenticated_users_without_admin_access_cannot_visit_the_dashboard()
    {
        $this->actingAs($user = User::factory()->create());

        $this->get('/dashboard')->assertForbidden();
    }

    public function test_admin_users_can_visit_the_dashboard()
    {
        $this->actingAs($user = User::factory()->superAdmin()->create());

        $this->get('/dashboard')->assertOk();
    }

    public function test_marketplace_dashboard_only_counts_the_authenticated_sellers_content(): void
    {
        $marketplaceRole = Role::query()->where('slug', 'marketplace_user')->firstOrFail();
        $seller = User::factory()->create(['role_id' => $marketplaceRole->id]);
        $otherSeller = User::factory()->create();

        foreach ([[$seller, 'Mine'], [$otherSeller, 'Other']] as [$owner, $title]) {
            Design::query()->create([
                'user_id' => $owner->id,
                'title' => $title,
                'size' => '',
                'color' => '',
                'details' => [],
                'images' => [],
                'files' => [],
            ]);
            Folder::query()->create([
                'user_id' => $owner->id,
                'name' => $title.' Pack',
                'price_paise' => 10000,
                'zip_name' => strtolower($title).'.zip',
                'zip_path' => 'folders/'.strtolower($title).'.zip',
            ]);
        }

        $this->actingAs($seller)
            ->get(route('dashboard'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->where('ownContentOnly', true)
                ->where('stats.designs', 1)
                ->where('stats.folders', 1)
                ->has('recentDesigns', 1)
                ->where('recentDesigns.0.title', 'Mine'));
    }
}
