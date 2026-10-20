<?php

namespace Tests\Feature\Auth;

use App\Models\Role;
use App\Models\SiteSetting;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class RegistrationTest extends TestCase
{
    use RefreshDatabase;

    public function test_registration_screen_can_be_rendered()
    {
        $response = $this->get('/register');

        $response->assertStatus(200);
    }

    public function test_new_users_can_register()
    {
        $role = Role::query()->create([
            'name' => 'Staff',
            'slug' => 'staff',
        ]);
        SiteSetting::current()->update(['default_registration_role_id' => $role->id]);
        SiteSetting::forgetCache();

        $response = $this->post('/register', [
            'name' => 'Test User',
            'email' => 'test@example.com',
            'password' => 'password',
            'password_confirmation' => 'password',
        ]);

        $this->assertAuthenticated();
        $response->assertRedirect(route('dashboard', absolute: false));
        $this->assertDatabaseHas('users', [
            'email' => 'test@example.com',
            'role_id' => $role->id,
        ]);
    }
}
