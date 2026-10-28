<?php

namespace Tests\Feature\Admin;

use App\Models\User;
use App\Services\DesignImageMatchingService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Tests\TestCase;

class DesignImageSearchAdminTest extends TestCase
{
    use RefreshDatabase;

    public function test_image_search_redirects_to_a_refresh_safe_get_page(): void
    {
        $this->mock(DesignImageMatchingService::class)
            ->shouldReceive('search')
            ->once()
            ->andReturn([]);

        $admin = User::factory()->superAdmin()->create();

        $response = $this->actingAs($admin)->post('/admin/designs/search-by-image', [
            'image' => UploadedFile::fake()->image('query.jpg', 100, 100),
        ]);

        $response->assertRedirect('/admin/designs?imageSearch=1');

        $this->actingAs($admin)->get('/admin/designs?imageSearch=1')->assertOk();
        $this->actingAs($admin)->get('/admin/designs?imageSearch=1')->assertOk();
    }

    public function test_old_post_url_can_be_opened_with_get_without_a_405_error(): void
    {
        $admin = User::factory()->superAdmin()->create();

        $this->actingAs($admin)
            ->get('/admin/designs/search-by-image')
            ->assertRedirect('/admin/designs');
    }
}
