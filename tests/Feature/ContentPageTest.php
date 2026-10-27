<?php

namespace Tests\Feature;

use App\Models\ContentPage;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ContentPageTest extends TestCase
{
    use RefreshDatabase;

    public function test_public_legal_page_renders_active_markdown_content(): void
    {
        ContentPage::query()->where('slug', 'terms-and-conditions')->update([
            'content' => "# Terms\n\nThese are the current marketplace terms.",
        ]);

        $this->get('/legal/terms')
            ->assertOk()
            ->assertSee('Terms and Conditions')
            ->assertSee('These are the current marketplace terms.');
    }

    public function test_inactive_content_page_is_not_public(): void
    {
        ContentPage::query()->where('slug', 'privacy-policy')->update(['is_active' => false]);

        $this->get('/legal/privacy')->assertNotFound();
    }

    public function test_super_admin_can_update_a_content_page(): void
    {
        $admin = User::factory()->superAdmin()->create();
        $page = ContentPage::query()->where('slug', 'refund-policy')->firstOrFail();

        $this->actingAs($admin)
            ->put('/admin/content-pages/'.$page->id, [
                'title' => 'Updated Refund Policy',
                'content' => 'Refund details updated by the administrator.',
                'isActive' => true,
            ])
            ->assertRedirect();

        $this->assertDatabaseHas('content_pages', [
            'id' => $page->id,
            'title' => 'Updated Refund Policy',
            'is_active' => true,
        ]);
    }
}
