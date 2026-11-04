<?php

namespace Tests\Feature\Auth;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AuthenticationTest extends TestCase
{
    use RefreshDatabase;

    public function test_login_screen_can_be_rendered()
    {
        $response = $this->get('/login');

        $response->assertStatus(200);
    }

    public function test_users_can_authenticate_using_the_login_screen()
    {
        $user = User::factory()->create();

        $response = $this->post('/login', [
            'email' => $user->email,
            'password' => 'password',
        ]);

        $this->assertAuthenticated();
        $response->assertRedirect(route('dashboard', absolute: false));
    }

    public function test_users_can_not_authenticate_with_invalid_password()
    {
        $user = User::factory()->create();

        $this->post('/login', [
            'email' => $user->email,
            'password' => 'wrong-password',
        ]);

        $this->assertGuest();
    }

    public function test_users_can_authenticate_with_mobile_number_and_password(): void
    {
        User::factory()->create([
            'country_code' => '+91',
            'mobile_number' => '9876543210',
            'phone_key' => '+919876543210',
            'allow_password_login' => true,
        ]);

        $response = $this->post('/login', [
            'loginType' => 'mobile',
            'countryCode' => '+91',
            'mobileNumber' => '9876543210',
            'password' => 'password',
        ]);

        $this->assertAuthenticated();
        $response->assertRedirect(route('dashboard', absolute: false));
    }

    public function test_password_login_is_rejected_when_disabled_for_the_user(): void
    {
        $user = User::factory()->create(['allow_password_login' => false]);
        $this->assertFalse($user->allow_password_login);

        $this->post('/login', [
            'email' => $user->email,
            'password' => 'password',
        ])->assertSessionHasErrors('email');

        $this->assertGuest();
    }

    public function test_users_can_authenticate_on_the_web_with_mobile_otp(): void
    {
        config([
            'designhub.otp_static_enabled' => true,
            'designhub.otp_static_code' => '1234',
        ]);

        User::factory()->create([
            'country_code' => '+91',
            'mobile_number' => '9876543210',
            'phone_key' => '+919876543210',
            'allow_otp_login' => true,
        ]);

        $payload = [
            'countryCode' => '+91',
            'mobileNumber' => '9876543210',
        ];

        $this->from('/login')->post(route('login.otp.request'), $payload)
            ->assertRedirect('/login')
            ->assertSessionHasNoErrors();

        $this->post(route('login.otp.verify'), $payload + ['code' => '1234'])
            ->assertRedirect(route('dashboard', absolute: false));

        $this->assertAuthenticated();
    }

    public function test_otp_login_is_rejected_when_disabled_for_the_user(): void
    {
        User::factory()->create([
            'country_code' => '+91',
            'mobile_number' => '9876543210',
            'phone_key' => '+919876543210',
            'allow_otp_login' => false,
        ]);

        $this->from('/login')->post(route('login.otp.request'), [
            'countryCode' => '+91',
            'mobileNumber' => '9876543210',
        ])->assertSessionHasErrors('mobileNumber');

        $this->assertGuest();
    }

    public function test_users_can_logout()
    {
        $user = User::factory()->create();

        $response = $this->actingAs($user)->post('/logout');

        $this->assertGuest();
        $response->assertRedirect('/');
    }
}
