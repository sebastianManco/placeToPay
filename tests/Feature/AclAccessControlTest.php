<?php

namespace Tests\Feature;

use App\Models\Category;
use App\Models\Permission;
use App\Models\Product;
use App\Models\Role;
use App\Models\User;
use Database\Seeders\RoleAndPermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AclAccessControlTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        // Seed default roles and permissions
        $this->seed(RoleAndPermissionSeeder::class);
    }

    protected function createClientUser(array $attributes = []): User
    {
        static $counter = 1;
        $id = 71000000 + ($counter++);

        return User::create(array_merge([
            'identification' => $id,
            'name' => "Client{$counter}",
            'last_Name' => 'User',
            'email' => "client{$counter}@example.com",
            'phone' => '3001234567',
            'direction' => 'Calle 1 # 2-3',
            'user_name' => "clientuser{$counter}",
            'password' => 'secret123',
            'email_verified_at' => now(),
            'is_active' => true,
            'role' => 'client',
        ], $attributes));
    }

    protected function createAdminUser(array $attributes = []): User
    {
        static $counter = 1;
        $id = 72000000 + ($counter++);

        return User::create(array_merge([
            'identification' => $id,
            'name' => "Admin{$counter}",
            'last_Name' => 'Boss',
            'email' => "admin{$counter}@example.com",
            'phone' => '3007654321',
            'direction' => 'Avenida Principal # 10',
            'user_name' => "adminuser{$counter}",
            'password' => 'secret123',
            'email_verified_at' => now(),
            'is_active' => true,
            'role' => 'admin',
        ], $attributes));
    }

    public function test_guest_is_redirected_from_admin_panel(): void
    {
        $response = $this->get('/admin/products');
        $response->assertRedirect('/home/login');
    }

    public function test_regular_client_receives_403_accessing_admin_panel(): void
    {
        $client = $this->createClientUser();

        $routes = [
            '/admin/clients',
            '/admin/products',
            '/admin/categories',
            '/admin/orders',
            '/admin/reports',
            '/admin/roles',
        ];

        foreach ($routes as $route) {
            $response = $this->actingAs($client)->get($route);
            $response->assertStatus(403);
        }
    }

    public function test_user_with_products_view_can_access_catalog_but_not_create_product(): void
    {
        $user = $this->createClientUser();
        $user->givePermission('products.view');

        // Can view catalog
        $response = $this->actingAs($user)->get('/admin/products');
        $response->assertStatus(200);

        // Cannot view create form
        $responseCreate = $this->actingAs($user)->get('/admin/products/create');
        $responseCreate->assertStatus(403);

        // Cannot post create product
        $responseStore = $this->actingAs($user)->post('/admin/products', [
            'name' => 'Unauthorized Product',
            'price' => 100,
            'stock' => 10,
        ]);
        $responseStore->assertStatus(403);

        // Cannot view other modules
        $this->actingAs($user)->get('/admin/clients')->assertStatus(403);
        $this->actingAs($user)->get('/admin/roles')->assertStatus(403);
    }

    public function test_user_with_products_create_permission_can_access_create_form(): void
    {
        $user = $this->createClientUser();
        $user->givePermission('products.view');
        $user->givePermission('products.create');

        $response = $this->actingAs($user)->get('/admin/products/create');
        $response->assertStatus(200);
    }

    public function test_user_with_catalog_manager_role_can_manage_products_and_categories_only(): void
    {
        $user = $this->createClientUser();
        $catalogManagerRole = Role::where('slug', 'catalog_manager')->firstOrFail();
        $user->assignRole($catalogManagerRole);

        // Can access products and categories
        $this->actingAs($user)->get('/admin/products')->assertStatus(200);
        $this->actingAs($user)->get('/admin/products/create')->assertStatus(200);
        $this->actingAs($user)->get('/admin/categories')->assertStatus(200);
        $this->actingAs($user)->get('/admin/categories/create')->assertStatus(200);

        // Cannot access clients, orders, reports or roles
        $this->actingAs($user)->get('/admin/clients')->assertStatus(403);
        $this->actingAs($user)->get('/admin/orders')->assertStatus(403);
        $this->actingAs($user)->get('/admin/reports')->assertStatus(403);
        $this->actingAs($user)->get('/admin/roles')->assertStatus(403);
    }

    public function test_admin_user_has_unrestricted_access_to_all_admin_endpoints(): void
    {
        $admin = $this->createAdminUser();

        $this->actingAs($admin)->get('/admin/clients')->assertStatus(200);
        $this->actingAs($admin)->get('/admin/products')->assertStatus(200);
        $this->actingAs($admin)->get('/admin/categories')->assertStatus(200);
        $this->actingAs($admin)->get('/admin/orders')->assertStatus(200);
        $this->actingAs($admin)->get('/admin/reports')->assertStatus(200);
        $this->actingAs($admin)->get('/admin/roles')->assertStatus(200);
    }
}
