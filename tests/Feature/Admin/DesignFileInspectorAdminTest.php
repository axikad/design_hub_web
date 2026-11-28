<?php

namespace Tests\Feature\Admin;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class DesignFileInspectorAdminTest extends TestCase
{
    use RefreshDatabase;

    public function test_super_admin_can_open_and_inspect_a_dst_file(): void
    {
        $admin = User::factory()->superAdmin()->create();
        $header = str_pad("LA:DEMO\rST: 120\rCO: 1\r+X: 100\r-X: 20\r+Y: 50\r-Y: 10\r\x1a", 512, "\x20");

        $this->actingAs($admin)
            ->get(route('admin.design-file-inspector.index'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('admin/design-file-inspector/index')
                ->where('supportedExtensions', ['dst'])
                ->where('result', null));

        $this->actingAs($admin)
            ->post(route('admin.design-file-inspector.inspect'), [
                'designFile' => UploadedFile::fake()->createWithContent('demo.dst', $header."\x00\x00\xf3"),
            ])
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('admin/design-file-inspector/index')
                ->where('result.metadata.stitchCount', 120)
                ->where('result.metadata.widthMm', 12)
                ->where('result.metadata.heightMm', 6));
    }
}
