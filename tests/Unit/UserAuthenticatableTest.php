<?php

namespace Tests\Unit;

use App\Models\User;
use App\User as LegacyUser;
use Illuminate\Contracts\Auth\Authenticatable as AuthenticatableContract;
use Illuminate\Foundation\Auth\User as BaseAuthenticatable;
use Tests\TestCase;

class UserAuthenticatableTest extends TestCase
{
    /**
     * Test User model inherits from Authenticatable and implements contract.
     */
    public function test_user_inherits_from_authenticatable(): void
    {
        $user = new User();

        $this->assertInstanceOf(BaseAuthenticatable::class, $user);
        $this->assertInstanceOf(AuthenticatableContract::class, $user);
    }

    /**
     * Test legacy App\User also inherits from Authenticatable and implements contract.
     */
    public function test_legacy_user_inherits_from_authenticatable(): void
    {
        $legacyUser = new LegacyUser();

        $this->assertInstanceOf(User::class, $legacyUser);
        $this->assertInstanceOf(BaseAuthenticatable::class, $legacyUser);
        $this->assertInstanceOf(AuthenticatableContract::class, $legacyUser);
    }

    /**
     * Test Authenticatable identifier methods and table configurations.
     */
    public function test_user_authenticatable_identifiers_and_table_configuration(): void
    {
        $user = new User([
            'identification' => 12345678,
            'password' => 'secret123',
        ]);

        $this->assertSame('users', $user->getTable());
        $this->assertSame('identification', $user->getKeyName());
        $this->assertFalse($user->getIncrementing());
        $this->assertSame('int', $user->getKeyType());

        // Authenticatable interface methods
        $this->assertSame('identification', $user->getAuthIdentifierName());
        $this->assertEquals(12345678, $user->getAuthIdentifier());
        $this->assertSame('password', $user->getAuthPasswordName());
        $this->assertSame('remember_token', $user->getRememberTokenName());
    }

    /**
     * Test sensitive attributes are hidden from serialization.
     */
    public function test_sensitive_attributes_are_hidden_from_serialization(): void
    {
        $user = new User([
            'identification' => 12345678,
            'name' => 'John',
            'last_Name' => 'Doe',
            'email' => 'john@example.com',
            'phone' => '1234567',
            'direction' => 'Street 1',
            'user_Name' => 'johndoe',
            'password' => 'secret123',
            'remember_token' => 'token123',
        ]);

        $array = $user->toArray();

        $this->assertArrayNotHasKey('password', $array);
        $this->assertArrayNotHasKey('remember_token', $array);
        $this->assertArrayHasKey('name', $array);
        $this->assertArrayHasKey('identification', $array);
    }

    /**
     * Test password hashing cast.
     */
    public function test_password_is_hashed_via_cast(): void
    {
        $user = new User();
        $user->password = 'plain-password';

        $this->assertNotEquals('plain-password', $user->password);
        $this->assertTrue(\Illuminate\Support\Facades\Hash::check('plain-password', $user->password));
    }
}
