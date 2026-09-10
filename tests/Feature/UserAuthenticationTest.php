<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Support\Facades\Auth;
use Tests\TestCase;

class UserAuthenticationTest extends TestCase
{
    /**
     * Test logging in a User instance with Auth facade.
     */
    public function test_user_can_be_authenticated_with_auth_facade(): void
    {
        $user = new User([
            'identification' => 98765432,
            'name' => 'Jane',
            'last_Name' => 'Doe',
            'email' => 'jane@example.com',
            'phone' => '987654321',
            'direction' => 'Avenue 2',
            'user_Name' => 'janedoe',
            'password' => 'password123',
        ]);
        $user->id = 1;

        Auth::login($user);

        $this->assertTrue(Auth::check());
        $this->assertEquals($user->id, Auth::id());
        $this->assertEquals(98765432, $user->identification);
        $this->assertEquals('Jane', Auth::user()->name);

        Auth::logout();
        $this->assertFalse(Auth::check());
    }

    /**
     * Test default auth provider uses App\Models\User.
     */
    public function test_auth_configuration_uses_user_model(): void
    {
        $this->assertSame(User::class, config('auth.providers.users.model'));
    }
}
