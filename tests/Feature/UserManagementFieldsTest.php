<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class UserManagementFieldsTest extends TestCase
{
    use RefreshDatabase;

    public function test_new_users_receive_active_status_by_default(): void
    {
        $user = User::factory()->create();

        $this->assertSame('active', $user->status);
    }

    public function test_user_management_fields_can_be_stored(): void
    {
        $user = User::factory()->create([
            'avatar_path' => 'avatars/user.png',
            'status' => 'inactive',
            'last_login_at' => '2026-10-19 10:30:00',
        ]);

        $this->assertSame('avatars/user.png', $user->avatar_path);
        $this->assertSame('inactive', $user->status);
        $this->assertSame('2026-10-19 10:30:00', $user->last_login_at?->format('Y-m-d H:i:s'));
    }
}
