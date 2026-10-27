<?php

namespace Tests\Feature\Api;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class ProfileApiTest extends TestCase
{
    use RefreshDatabase;

    public function test_authenticated_user_can_update_name_without_changing_contact_details(): void
    {
        $user = User::factory()->create([
            'name' => 'Old Name',
            'email' => 'old@example.com',
            'email_verified_at' => now(),
        ]);
        Sanctum::actingAs($user);

        $this->patchJson('/api/v1/users/me', [
            'name' => 'New Name',
        ])
            ->assertOk()
            ->assertJsonPath('message', 'Profile updated successfully.')
            ->assertJsonPath('data.id', $user->public_id)
            ->assertJsonPath('data.name', 'New Name')
            ->assertJsonPath('data.email', 'old@example.com')
            ->assertJsonMissingPath('data.password');

        $this->assertDatabaseHas('users', [
            'id' => $user->id,
            'name' => 'New Name',
            'email' => 'old@example.com',
        ]);
    }

    public function test_authenticated_user_can_optionally_upload_an_avatar_with_profile_update(): void
    {
        Storage::fake('public');
        $user = User::factory()->create();
        Sanctum::actingAs($user);

        $response = $this->post('/api/v1/users/me', [
            'name' => $user->name,
            'image' => UploadedFile::fake()->image('avatar.jpg', 300, 300),
        ], ['Accept' => 'application/json'])
            ->assertOk()
            ->assertJsonPath('message', 'Profile updated successfully.');

        $path = $user->fresh()->avatar_path;
        $this->assertNotNull($path);
        Storage::disk('public')->assertExists($path);
        $this->assertSame(Storage::disk('public')->url($path), $response->json('data.avatarUri'));
    }

    public function test_profile_update_requires_otp_flow_for_contact_changes(): void
    {
        $user = User::factory()->create();
        Sanctum::actingAs($user);

        $this->patchJson('/api/v1/users/me', [
            'name' => 'New Name',
            'email' => 'new@example.com',
        ])
            ->assertUnprocessable()
            ->assertJsonPath('code', 'validation_error')
            ->assertJsonStructure(['errors' => ['contact']]);

        $this->assertNotSame('new@example.com', $user->fresh()->email);
    }

    public function test_authenticated_user_can_verify_and_update_mobile_number(): void
    {
        config([
            'designhub.otp_static_enabled' => true,
            'designhub.otp_static_code' => '1234',
        ]);

        $user = User::factory()->create([
            'country_code' => '+91',
            'mobile_number' => '9000000001',
            'phone_key' => '+919000000001',
        ]);
        Sanctum::actingAs($user);

        $payload = [
            'type' => 'mobile',
            'countryCode' => '+91',
            'mobileNumber' => '9000000002',
        ];

        $this->postJson('/api/v1/users/me/contact/request-otp', $payload)
            ->assertOk()
            ->assertJsonPath('data.type', 'mobile')
            ->assertJsonPath('data.destination', '*********0002');

        $this->postJson('/api/v1/users/me/contact/verify-otp', [
            ...$payload,
            'code' => '1234',
        ])
            ->assertOk()
            ->assertJsonPath('data.countryCode', '+91')
            ->assertJsonPath('data.mobileNumber', '9000000002');

        $this->assertDatabaseHas('users', [
            'id' => $user->id,
            'phone_key' => '+919000000002',
        ]);
    }

    public function test_authenticated_user_can_verify_and_update_email(): void
    {
        config([
            'designhub.otp_static_enabled' => true,
            'designhub.otp_static_code' => '1234',
            'mail.default' => 'array',
        ]);

        $user = User::factory()->create([
            'email' => 'old@example.com',
            'email_verified_at' => now(),
        ]);
        Sanctum::actingAs($user);

        $payload = [
            'type' => 'email',
            'email' => 'NEW@EXAMPLE.COM',
        ];

        $this->postJson('/api/v1/users/me/contact/request-otp', $payload)
            ->assertOk()
            ->assertJsonPath('data.type', 'email')
            ->assertJsonPath('data.destination', 'n**@example.com');

        $this->postJson('/api/v1/users/me/contact/verify-otp', [
            ...$payload,
            'code' => '1234',
        ])
            ->assertOk()
            ->assertJsonPath('data.email', 'new@example.com');

        $this->assertNotNull($user->fresh()->email_verified_at);
    }

    public function test_profile_update_can_remove_the_current_avatar(): void
    {
        Storage::fake('public');
        $user = User::factory()->create(['avatar_path' => 'previews/existing.jpg']);
        Sanctum::actingAs($user);

        $this->postJson('/api/v1/users/me', [
            'name' => $user->name,
            'removeImage' => true,
        ])
            ->assertOk()
            ->assertJsonPath('data.avatarUri', null);

        $this->assertNull($user->fresh()->avatar_path);
    }
}
