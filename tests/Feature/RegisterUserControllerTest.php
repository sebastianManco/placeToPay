<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class RegisterUserControllerTest extends TestCase
{
    use RefreshDatabase;

    /**
     * Test user registration form can be rendered.
     */
    public function test_user_registration_form_can_be_rendered(): void
    {
        $response = $this->get('/home/register');

        $response->assertStatus(200);
        $response->assertViewIs('auth.register');
        $response->assertSee('name="password_confirmation"', false);
        $response->assertDontSee('value="{{old(\'password\')}}"', false);
    }

    /**
     * Test standard /register route can be rendered.
     */
    public function test_standard_register_route_can_be_rendered(): void
    {
        $response = $this->get('/register');

        $response->assertStatus(200);
        $response->assertViewIs('auth.register');
    }

    /**
     * Test successful registration with matching password and confirmation.
     */
    public function test_user_can_register_with_valid_password_and_confirmation(): void
    {
        $payload = [
            'identification' => 10203040,
            'name' => 'John',
            'lastName' => 'Doe',
            'email' => 'john.doe@example.com',
            'phone' => '3001234567',
            'direction' => 'Calle 100 #20-30',
            'userName' => 'johndoe',
            'password' => 'secret123',
            'password_confirmation' => 'secret123',
        ];

        $response = $this->post('/home/registered', $payload);

        $response->assertStatus(302);
        $response->assertSessionHas('success');

        $user = User::where('identification', 10203040)->first();
        $this->assertNotNull($user);
        $this->assertSame('John', $user->name);
        $this->assertSame('Doe', $user->last_Name);
        $this->assertSame('john.doe@example.com', $user->email);
        $this->assertNotEquals('secret123', $user->password);
        $this->assertTrue(Hash::check('secret123', $user->password));

        // Confirm password is never a model attribute
        $this->assertNull($user->getAttribute('confirm_Password'));
        $this->assertNull($user->getAttribute('confirm_password'));
    }

    /**
     * Test registration fails when password confirmation does not match.
     */
    public function test_registration_fails_when_password_confirmation_mismatches(): void
    {
        $payload = [
            'identification' => 10203041,
            'name' => 'John',
            'lastName' => 'Doe',
            'email' => 'john.mismatch@example.com',
            'phone' => '3001234567',
            'direction' => 'Calle 100 #20-30',
            'userName' => 'johnmismatch',
            'password' => 'secret123',
            'password_confirmation' => 'different456',
        ];

        $response = $this->from('/home/register')->post('/home/registered', $payload);

        $response->assertStatus(302);
        $response->assertRedirect('/home/register');
        $response->assertSessionHasErrors(['password']);

        $this->assertDatabaseMissing('users', [
            'identification' => 10203041,
        ]);
    }

    /**
     * Test registration fails when password is shorter than 8 characters.
     */
    public function test_registration_fails_when_password_is_shorter_than_8_chars(): void
    {
        $payload = [
            'identification' => 10203042,
            'name' => 'John',
            'lastName' => 'Doe',
            'email' => 'john.short@example.com',
            'phone' => '3001234567',
            'direction' => 'Calle 100 #20-30',
            'userName' => 'johnshort',
            'password' => 'short',
            'password_confirmation' => 'short',
        ];

        $response = $this->from('/home/register')->post('/home/registered', $payload);

        $response->assertStatus(302);
        $response->assertRedirect('/home/register');
        $response->assertSessionHasErrors(['password']);

        $this->assertDatabaseMissing('users', [
            'identification' => 10203042,
        ]);
    }

    /**
     * Test backward compatibility with legacy field names (Identification and confirmPassword).
     */
    public function test_registration_supports_legacy_field_names(): void
    {
        $payload = [
            'Identification' => 10203043,
            'name' => 'Legacy',
            'lastName' => 'User',
            'email' => 'legacy@example.com',
            'phone' => '3001234567',
            'direction' => 'Calle 200 #10-20',
            'userName' => 'legacyuser',
            'password' => 'secret123',
            'confirmPassword' => 'secret123',
        ];

        $response = $this->post('/home/registered', $payload);

        $response->assertStatus(302);
        $response->assertSessionHas('success');

        $user = User::where('identification', 10203043)->first();
        $this->assertNotNull($user);
        $this->assertTrue(Hash::check('secret123', $user->password));
    }
}
