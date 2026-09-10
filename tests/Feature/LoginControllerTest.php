<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\RateLimiter;
use Tests\TestCase;

class LoginControllerTest extends TestCase
{
    use RefreshDatabase;

    /**
     * Test the login page can be rendered successfully.
     */
    public function test_login_page_can_be_rendered(): void
    {
        $response = $this->get('/home/login');

        $response->assertStatus(200);
        $response->assertViewIs('auth.login');
        $response->assertSee('Login', false);
        $response->assertSee('name="email"', false);
        $response->assertSee('name="password"', false);
        $response->assertSee('name="remember"', false);
        $response->assertSee('method="POST"', false);
    }

    /**
     * Test the standard /login route renders the auth.login view.
     */
    public function test_standard_login_route_can_be_rendered(): void
    {
        $response = $this->get('/login');

        $response->assertStatus(200);
        $response->assertViewIs('auth.login');
    }

    /**
     * Test authenticated users visiting login page are redirected.
     */
    public function test_authenticated_user_cannot_visit_login_page(): void
    {
        $user = User::create([
            'identification' => 20000001,
            'name' => 'Active',
            'last_Name' => 'User',
            'email' => 'active@example.com',
            'phone' => '123456789',
            'direction' => 'Street 10',
            'user_Name' => 'activeuser',
            'password' => Hash::make('secret123'),
            'email_verified_at' => now(),
            'is_active' => true,
            'role' => 'client',
        ]);

        $response = $this->actingAs($user)->get('/home/login');

        $response->assertStatus(302);
        $response->assertRedirect('/dashboard');
    }

    /**
     * Test successful login with valid credentials.
     */
    public function test_user_can_login_with_valid_credentials(): void
    {
        $user = User::create([
            'identification' => 20000002,
            'name' => 'Valid',
            'last_Name' => 'Login',
            'email' => 'valid@example.com',
            'phone' => '123456789',
            'direction' => 'Street 11',
            'user_Name' => 'validlogin',
            'password' => 'secret123',
            'email_verified_at' => now(),
            'is_active' => true,
            'role' => 'client',
        ]);

        $response = $this->post('/home/login', [
            'email' => 'valid@example.com',
            'password' => 'secret123',
            'remember' => '1',
        ]);

        $response->assertStatus(302);
        $response->assertRedirect('/dashboard');
        $this->assertAuthenticatedAs($user);
    }

    /**
     * Test login fails with invalid password.
     */
    public function test_user_cannot_login_with_invalid_password(): void
    {
        User::create([
            'identification' => 20000003,
            'name' => 'Wrong',
            'last_Name' => 'Password',
            'email' => 'wrongpass@example.com',
            'phone' => '123456789',
            'direction' => 'Street 12',
            'user_Name' => 'wrongpass',
            'password' => 'correctpassword',
            'email_verified_at' => now(),
            'is_active' => true,
            'role' => 'client',
        ]);

        $response = $this->from('/home/login')->post('/home/login', [
            'email' => 'wrongpass@example.com',
            'password' => 'incorrectpassword',
        ]);

        $response->assertStatus(302);
        $response->assertRedirect('/home/login');
        $response->assertSessionHasErrors('email');
        $this->assertGuest();
    }

    /**
     * Test login is blocked if user is inactive.
     */
    public function test_user_cannot_login_if_account_is_inactive(): void
    {
        User::create([
            'identification' => 20000004,
            'name' => 'Inactive',
            'last_Name' => 'Account',
            'email' => 'inactive@example.com',
            'phone' => '123456789',
            'direction' => 'Street 13',
            'user_Name' => 'inactiveuser',
            'password' => 'secret123',
            'email_verified_at' => now(),
            'is_active' => false,
            'role' => 'client',
        ]);

        $response = $this->from('/home/login')->post('/home/login', [
            'email' => 'inactive@example.com',
            'password' => 'secret123',
        ]);

        $response->assertStatus(302);
        $response->assertRedirect('/home/login');
        $response->assertSessionHasErrors(['email' => 'Tu cuenta se encuentra inactiva. Por favor, contacta al administrador.']);
        $this->assertGuest();
    }

    /**
     * Test rate limiting blocks after 5 failed login attempts.
     */
    public function test_login_attempts_are_rate_limited(): void
    {
        User::create([
            'identification' => 20000005,
            'name' => 'Throttle',
            'last_Name' => 'User',
            'email' => 'throttle@example.com',
            'phone' => '123456789',
            'direction' => 'Street 14',
            'user_Name' => 'throttleuser',
            'password' => 'secret123',
            'email_verified_at' => now(),
            'is_active' => true,
            'role' => 'client',
        ]);

        for ($i = 0; $i < 5; $i++) {
            $this->post('/home/login', [
                'email' => 'throttle@example.com',
                'password' => 'wrongpassword',
            ]);
        }

        // 6th attempt should trigger throttle message
        $response = $this->from('/home/login')->post('/home/login', [
            'email' => 'throttle@example.com',
            'password' => 'wrongpassword',
        ]);

        $response->assertStatus(302);
        $response->assertSessionHasErrors('email');
    }

    /**
     * Test authenticated user can log out.
     */
    public function test_authenticated_user_can_logout(): void
    {
        $user = User::create([
            'identification' => 20000006,
            'name' => 'Logout',
            'last_Name' => 'Tester',
            'email' => 'logout@example.com',
            'phone' => '123456789',
            'direction' => 'Street 15',
            'user_Name' => 'logoutuser',
            'password' => 'secret123',
            'email_verified_at' => now(),
            'is_active' => true,
            'role' => 'client',
        ]);

        $response = $this->actingAs($user)->post('/logout');

        $response->assertStatus(302);
        $response->assertRedirect('/home/login');
        $this->assertFalse(Auth::check());
    }
}
