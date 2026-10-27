<?php

namespace Tests\Feature\Api;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class ProfileApiTest extends TestCase
{
    use RefreshDatabase;

    public function test_authenticated_user_can_update_name_and_email(): void
    {
        $user = User::factory()->create([
            'name' => 'Old Name',
            'email' => 'old@example.com',
            'email_verified_at' => now(),
        ]);
        Sanctum::actingAs($user);

        $this->patchJson('/api/v1/users/me', [
            'name' => 'New Name',
            'email' => 'NEW@EXAMPLE.COM',
        ])
            ->assertOk()
            ->assertJsonPath('message', 'Profile updated successfully.')
            ->assertJsonPath('data.id', $user->public_id)
            ->assertJsonPath('data.name', 'New Name')
            ->assertJsonPath('data.email', 'new@example.com')
            ->assertJsonMissingPath('data.password');

        $this->assertDatabaseHas('users', [
            'id' => $user->id,
            'name' => 'New Name',
            'email' => 'new@example.com',
            'email_verified_at' => null,
        ]);
    }

    public function test_profile_update_rejects_an_email_used_by_another_user(): void
    {
        $user = User::factory()->create();
        $other = User::factory()->create();
        Sanctum::actingAs($user);

        $this->patchJson('/api/v1/users/me', [
            'name' => 'New Name',
            'email' => $other->email,
        ])
            ->assertUnprocessable()
            ->assertJsonPath('code', 'validation_error')
            ->assertJsonStructure(['errors' => ['email']]);
    }
}
