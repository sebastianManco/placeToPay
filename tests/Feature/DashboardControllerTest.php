<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class DashboardControllerTest extends TestCase
{
    use RefreshDatabase;

    /**
     * Test unauthenticated users are redirected to login.
     */
    public function test_unauthenticated_users_are_redirected_to_login(): void
    {
        $response = $this->get('/dashboard');

        $response->assertStatus(302);
        $response->assertRedirect('/home/login');
    }

    /**
     * Test unverified users are redirected to verification notice.
     */
    public function test_unverified_users_are_redirected_to_verification_notice(): void
    {
        $user = User::create([
            'identification' => 11223344,
            'name' => 'Alice',
            'last_Name' => 'Smith',
            'email' => 'alice@example.com',
            'phone' => '123456789',
            'direction' => 'Street 1',
            'user_Name' => 'alicesmith',
            'password' => 'secret123',
            'email_verified_at' => null,
            'is_active' => true,
            'role' => 'client',
        ]);

        $response = $this->actingAs($user)->get('/dashboard');

        $response->assertStatus(302);
        $response->assertRedirect('/email/verify');
    }

    /**
     * Test verified user can view dashboard with default title.
     */
    public function test_dashboard_can_be_rendered_with_default_title(): void
    {
        $user = User::create([
            'identification' => 11223345,
            'name' => 'Bob',
            'last_Name' => 'Smith',
            'email' => 'bob@example.com',
            'phone' => '123456789',
            'direction' => 'Street 2',
            'user_Name' => 'bobsmith',
            'password' => 'secret123',
            'email_verified_at' => now(),
            'is_active' => true,
            'role' => 'client',
        ]);

        $response = $this->actingAs($user)->get('/dashboard');

        $response->assertStatus(200);
        $response->assertSee('valor default');
    }

    /**
     * Test verified user can view dashboard with custom title.
     */
    public function test_dashboard_can_be_rendered_with_custom_title(): void
    {
        $user = User::create([
            'identification' => 11223346,
            'name' => 'Charlie',
            'last_Name' => 'Brown',
            'email' => 'charlie@example.com',
            'phone' => '123456789',
            'direction' => 'Street 3',
            'user_Name' => 'charliebrown',
            'password' => 'secret123',
            'email_verified_at' => now(),
            'is_active' => true,
            'role' => 'client',
        ]);

        $response = $this->actingAs($user)->get('/dashboard?title=AdminPanel');

        $response->assertStatus(200);
        $response->assertSee('AdminPanel');
    }
}
