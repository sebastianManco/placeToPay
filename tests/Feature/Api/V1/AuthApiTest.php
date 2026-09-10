<?php

namespace Tests\Feature\Api\V1;

use App\Models\Permission;
use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class AuthApiTest extends TestCase
{
    use RefreshDatabase;

    /**
     * Create an active test user.
     */
    protected function createTestUser(array $attributes = []): User
    {
        return User::create(array_merge([
            'identification' => 50000001,
            'name' => 'John',
            'last_Name' => 'Doe',
            'email' => 'johndoe@example.com',
            'phone' => '3001112233',
            'direction' => 'Calle 10 # 20-30',
            'user_name' => 'johndoe',
            'password' => Hash::make('Secret123*'),
            'email_verified_at' => now(),
            'is_active' => true,
            'role' => 'client',
        ], $attributes));
    }

    /**
     * Test successful login for admin user returns bearer token with wildcard abilities.
     */
    public function test_admin_can_login_and_receives_token_with_full_abilities(): void
    {
        $admin = $this->createTestUser([
            'identification' => 50000002,
            'email' => 'admin@example.com',
            'role' => 'admin',
        ]);

        $response = $this->postJson(route('api.v1.auth.login'), [
            'email' => 'admin@example.com',
            'password' => 'Secret123*',
            'device_name' => 'Postman-Test',
        ]);

        $response->assertOk()
            ->assertJsonStructure([
                'success',
                'message',
                'data' => [
                    'token',
                    'token_type',
                    'user' => [
                        'id',
                        'identification',
                        'name',
                        'last_name',
                        'email',
                        'role',
                        'roles',
                        'permissions',
                    ],
                ],
            ]);

        $this->assertEquals('Bearer', $response->json('data.token_type'));
        $this->assertEquals(['*'], $response->json('data.user.permissions'));
        $this->assertNotEmpty($response->json('data.token'));
        $this->assertDatabaseHas('personal_access_tokens', [
            'tokenable_id' => $admin->id,
            'name' => 'Postman-Test',
        ]);
    }

    /**
     * Test non-admin user receives bearer token with granular role/permission abilities.
     */
    public function test_standard_user_receives_token_with_assigned_permission_abilities(): void
    {
        $role = Role::create([
            'name' => 'Catalog Manager',
            'slug' => 'catalog_manager',
        ]);

        $perm1 = Permission::create([
            'name' => 'Crear productos',
            'slug' => 'products.create',
            'module' => 'products',
        ]);

        $perm2 = Permission::create([
            'name' => 'Editar productos',
            'slug' => 'products.edit',
            'module' => 'products',
        ]);

        $role->permissions()->attach([$perm1->id, $perm2->id]);

        $user = $this->createTestUser([
            'identification' => 50000003,
            'email' => 'catalog@example.com',
            'role' => 'catalog_manager',
        ]);
        $user->assignRole($role);

        $response = $this->postJson(route('api.v1.auth.login'), [
            'email' => 'catalog@example.com',
            'password' => 'Secret123*',
        ]);

        $response->assertOk();
        $permissions = $response->json('data.user.permissions');
        $this->assertContains('products.create', $permissions);
        $this->assertContains('products.edit', $permissions);
    }

    /**
     * Test login fails with invalid password credentials.
     */
    public function test_cannot_login_with_invalid_password(): void
    {
        $this->createTestUser(['email' => 'user@example.com']);

        $response = $this->postJson(route('api.v1.auth.login'), [
            'email' => 'user@example.com',
            'password' => 'WrongPassword!',
        ]);

        $response->assertUnauthorized()
            ->assertJson([
                'success' => false,
                'message' => 'Credenciales de acceso incorrectas.',
            ]);
    }

    /**
     * Test inactive user cannot authenticate and receives 403 Forbidden.
     */
    public function test_inactive_user_is_forbidden_from_logging_in(): void
    {
        $this->createTestUser([
            'email' => 'inactive@example.com',
            'is_active' => false,
        ]);

        $response = $this->postJson(route('api.v1.auth.login'), [
            'email' => 'inactive@example.com',
            'password' => 'Secret123*',
        ]);

        $response->assertForbidden()
            ->assertJson([
                'success' => false,
                'message' => 'Tu cuenta se encuentra inactiva. Por favor, contacta al administrador.',
            ]);
    }

    /**
     * Test validation rules for login payload.
     */
    public function test_login_requires_email_and_password(): void
    {
        $response = $this->postJson(route('api.v1.auth.login'), []);

        $response->assertUnprocessable()
            ->assertJsonStructure([
                'success',
                'message',
                'errors' => [
                    'email',
                    'password',
                ],
            ]);
    }

    /**
     * Test authenticated user can retrieve profile via GET /api/v1/auth/me.
     */
    public function test_authenticated_user_can_fetch_me_profile(): void
    {
        $user = $this->createTestUser([
            'identification' => 50000004,
            'email' => 'me@example.com',
            'role' => 'admin',
        ]);

        Sanctum::actingAs($user, ['*']);

        $response = $this->getJson(route('api.v1.auth.me'));

        $response->assertOk()
            ->assertJson([
                'success' => true,
                'data' => [
                    'id' => $user->id,
                    'identification' => 50000004,
                    'name' => 'John',
                    'email' => 'me@example.com',
                    'role' => 'admin',
                ],
            ]);
    }

    /**
     * Test unauthenticated request to /api/v1/auth/me returns 401.
     */
    public function test_unauthenticated_me_returns_401(): void
    {
        $response = $this->getJson(route('api.v1.auth.me'));

        $response->assertUnauthorized();
    }

    /**
     * Test user can logout and revoke their current bearer token.
     */
    public function test_user_can_logout_and_token_is_revoked(): void
    {
        $user = $this->createTestUser([
            'identification' => 50000005,
            'email' => 'logout@example.com',
        ]);

        $loginResponse = $this->postJson(route('api.v1.auth.login'), [
            'email' => 'logout@example.com',
            'password' => 'Secret123*',
        ]);

        $token = $loginResponse->json('data.token');

        // Logout using Bearer header
        $logoutResponse = $this->withHeader('Authorization', 'Bearer ' . $token)
            ->postJson(route('api.v1.auth.logout'));

        $logoutResponse->assertOk()
            ->assertJson([
                'success' => true,
                'message' => 'Sesión cerrada y token revocado exitosamente.',
            ]);

        $this->assertDatabaseMissing('personal_access_tokens', [
            'tokenable_id' => $user->id,
        ]);

        app('auth')->forgetGuards();

        // Trying to access protected route with the revoked token must return 401
        $meResponse = $this->withHeader('Authorization', 'Bearer ' . $token)
            ->getJson(route('api.v1.auth.me'));

        $meResponse->assertUnauthorized();
    }
}
