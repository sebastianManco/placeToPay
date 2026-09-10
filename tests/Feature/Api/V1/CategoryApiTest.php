<?php

namespace Tests\Feature\Api\V1;

use App\Models\Category;
use App\Models\Product;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class CategoryApiTest extends TestCase
{
    use RefreshDatabase;

    /**
     * Helper to create an admin user for testing.
     */
    protected function createAdminUser(): User
    {
        return User::create([
            'identification' => 70000001,
            'name' => 'Admin',
            'last_Name' => 'CategoryTester',
            'email' => 'admin.category@test.com',
            'phone' => '3001234567',
            'direction' => 'Category Avenue',
            'user_name' => 'admincat',
            'password' => 'secret123',
            'email_verified_at' => now(),
            'is_active' => true,
            'role' => 'admin',
        ]);
    }

    /**
     * Helper to create a regular client user without category management permissions.
     */
    protected function createClientUser(): User
    {
        return User::create([
            'identification' => 70000002,
            'name' => 'Client',
            'last_Name' => 'CategoryTester',
            'email' => 'client.category@test.com',
            'phone' => '3009876543',
            'direction' => 'Client Road',
            'user_name' => 'clientcat',
            'password' => 'secret123',
            'email_verified_at' => now(),
            'is_active' => true,
            'role' => 'client',
        ]);
    }

    /**
     * Test listing categories with pagination (Public Endpoint).
     */
    public function test_can_list_categories_with_pagination(): void
    {
        Category::factory()->count(18)->create();

        $response = $this->getJson(route('api.v1.categories.index', ['per_page' => 10]));

        $response->assertOk()
            ->assertJsonStructure([
                'success',
                'message',
                'data' => [
                    '*' => [
                        'id',
                        'name',
                        'description',
                        'is_active',
                        'products_count',
                        'created_at',
                        'updated_at',
                    ],
                ],
                'meta' => [
                    'current_page',
                    'per_page',
                    'total',
                    'last_page',
                    'from',
                    'to',
                ],
                'links' => [
                    'first',
                    'last',
                    'prev',
                    'next',
                ],
            ]);

        $this->assertCount(10, $response->json('data'));
        $this->assertEquals(18, $response->json('meta.total'));
    }

    /**
     * Test filtering categories by active status (Public Endpoint).
     */
    public function test_can_filter_categories_by_status(): void
    {
        Category::factory()->count(3)->create(['is_active' => true]);
        Category::factory()->count(2)->inactive()->create();

        $responseActive = $this->getJson(route('api.v1.categories.index', ['is_active' => 'true']));
        $responseActive->assertOk();
        $this->assertCount(3, $responseActive->json('data'));

        $responseInactive = $this->getJson(route('api.v1.categories.index', ['is_active' => 'false']));
        $responseInactive->assertOk();
        $this->assertCount(2, $responseInactive->json('data'));
    }

    /**
     * Test searching categories by name keyword (Public Endpoint).
     */
    public function test_can_search_categories_by_name(): void
    {
        Category::factory()->create(['name' => 'Tecnología y Gadgets']);
        Category::factory()->create(['name' => 'Alimentos y Bebidas']);
        Category::factory()->create(['name' => 'Hogar']);

        $response = $this->getJson(route('api.v1.categories.index', ['search' => 'Tecno']));

        $response->assertOk();
        $this->assertCount(1, $response->json('data'));
        $this->assertEquals('Tecnología y Gadgets', $response->json('data.0.name'));
    }

    /**
     * Test unauthenticated request to create category returns 401.
     */
    public function test_unauthenticated_user_cannot_create_category(): void
    {
        $response = $this->postJson(route('api.v1.categories.store'), [
            'name' => 'Categoría Sin Autenticar',
        ]);

        $response->assertUnauthorized();
    }

    /**
     * Test client without permissions gets 403 Forbidden.
     */
    public function test_client_without_permission_cannot_create_category(): void
    {
        $client = $this->createClientUser();
        Sanctum::actingAs($client, ['*']);

        $response = $this->postJson(route('api.v1.categories.store'), [
            'name' => 'Categoría Prohibida',
        ]);

        $response->assertForbidden();
    }

    /**
     * Test creating a new category returns 201 Created with Location header.
     */
    public function test_can_create_category(): void
    {
        $admin = $this->createAdminUser();
        Sanctum::actingAs($admin, ['*']);

        $payload = [
            'name' => 'Electrodomésticos',
            'description' => 'Aparatos electrónicos para el hogar',
            'is_active' => true,
        ];

        $response = $this->postJson(route('api.v1.categories.store'), $payload);

        $response->assertCreated()
            ->assertHeader('Location')
            ->assertJson([
                'success' => true,
                'message' => 'Categoría creada exitosamente.',
                'data' => [
                    'name' => 'Electrodomésticos',
                    'description' => 'Aparatos electrónicos para el hogar',
                    'is_active' => true,
                ],
            ]);

        $this->assertDatabaseHas('categories', [
            'name' => 'Electrodomésticos',
        ]);
    }

    /**
     * Test creation validation fails when name is missing or duplicated.
     */
    public function test_cannot_create_category_with_invalid_data(): void
    {
        $admin = $this->createAdminUser();
        Sanctum::actingAs($admin, ['*']);

        Category::factory()->create(['name' => 'Calzado']);

        // Missing name
        $responseMissing = $this->postJson(route('api.v1.categories.store'), []);
        $responseMissing->assertStatus(422)
            ->assertJson([
                'success' => false,
                'message' => 'Los datos enviados no son válidos.',
            ])
            ->assertJsonValidationErrors(['name']);

        // Duplicate name
        $responseDuplicate = $this->postJson(route('api.v1.categories.store'), [
            'name' => 'Calzado',
        ]);
        $responseDuplicate->assertStatus(422)
            ->assertJsonValidationErrors(['name']);
    }

    /**
     * Test retrieving a single category (Public Endpoint).
     */
    public function test_can_show_category(): void
    {
        $category = Category::factory()->create(['name' => 'Ropa Deportiva']);
        Product::factory()->count(3)->create(['category_id' => $category->id]);

        $response = $this->getJson(route('api.v1.categories.show', $category));

        $response->assertOk()
            ->assertJson([
                'success' => true,
                'data' => [
                    'id' => $category->id,
                    'name' => 'Ropa Deportiva',
                    'products_count' => 3,
                ],
            ]);
    }

    /**
     * Test 404 response when category does not exist.
     */
    public function test_returns_404_when_category_not_found(): void
    {
        $response = $this->getJson('/api/v1/categories/99999');

        $response->assertNotFound();
    }

    /**
     * Test full update via PUT.
     */
    public function test_can_update_category_via_put(): void
    {
        $admin = $this->createAdminUser();
        Sanctum::actingAs($admin, ['*']);

        $category = Category::factory()->create([
            'name' => 'Nombre Viejo',
            'description' => 'Desc Vieja',
            'is_active' => true,
        ]);

        $response = $this->putJson(route('api.v1.categories.update', $category), [
            'name' => 'Nombre Nuevo',
            'description' => 'Desc Nueva',
            'is_active' => false,
        ]);

        $response->assertOk()
            ->assertJson([
                'success' => true,
                'data' => [
                    'id' => $category->id,
                    'name' => 'Nombre Nuevo',
                    'description' => 'Desc Nueva',
                    'is_active' => false,
                ],
            ]);

        $this->assertDatabaseHas('categories', [
            'id' => $category->id,
            'name' => 'Nombre Nuevo',
            'is_active' => false,
        ]);
    }

    /**
     * Test partial update via PATCH.
     */
    public function test_can_update_category_via_patch(): void
    {
        $admin = $this->createAdminUser();
        Sanctum::actingAs($admin, ['*']);

        $category = Category::factory()->create([
            'name' => 'Computadores',
            'description' => 'Laptops y Desktop',
            'is_active' => true,
        ]);

        $response = $this->patchJson(route('api.v1.categories.patch', $category), [
            'is_active' => false,
        ]);

        $response->assertOk()
            ->assertJson([
                'success' => true,
                'data' => [
                    'id' => $category->id,
                    'name' => 'Computadores',
                    'is_active' => false,
                ],
            ]);

        $this->assertDatabaseHas('categories', [
            'id' => $category->id,
            'name' => 'Computadores',
            'is_active' => false,
        ]);
    }

    /**
     * Test deleting category without products returns 204 No Content.
     */
    public function test_can_delete_empty_category(): void
    {
        $admin = $this->createAdminUser();
        Sanctum::actingAs($admin, ['*']);

        $category = Category::factory()->create();

        $response = $this->deleteJson(route('api.v1.categories.destroy', $category));

        $response->assertNoContent();
        $this->assertDatabaseMissing('categories', ['id' => $category->id]);
    }

    /**
     * Test deleting category with linked products returns 409 Conflict when not forced.
     */
    public function test_cannot_delete_category_with_products_without_force(): void
    {
        $admin = $this->createAdminUser();
        Sanctum::actingAs($admin, ['*']);

        $category = Category::factory()->create();
        $product = Product::factory()->create(['category_id' => $category->id]);

        $response = $this->deleteJson(route('api.v1.categories.destroy', $category));

        $response->assertStatus(409)
            ->assertJson([
                'success' => false,
            ]);

        $this->assertDatabaseHas('categories', ['id' => $category->id]);
        $this->assertDatabaseHas('products', ['id' => $product->id, 'category_id' => $category->id]);
    }

    /**
     * Test deleting category with ?force=true unlinks products and deletes category.
     */
    public function test_can_delete_category_with_products_when_forced(): void
    {
        $admin = $this->createAdminUser();
        Sanctum::actingAs($admin, ['*']);

        $category = Category::factory()->create();
        $product = Product::factory()->create(['category_id' => $category->id]);

        $response = $this->deleteJson(route('api.v1.categories.destroy', ['category' => $category, 'force' => 'true']));

        $response->assertNoContent();

        $this->assertDatabaseMissing('categories', ['id' => $category->id]);
        $this->assertDatabaseHas('products', ['id' => $product->id, 'category_id' => null]);
    }

    /**
     * Test nested sub-resource endpoint /api/v1/categories/{category}/products (Public Endpoint).
     */
    public function test_can_list_products_of_category(): void
    {
        $categoryA = Category::factory()->create();
        $categoryB = Category::factory()->create();

        Product::factory()->count(4)->create(['category_id' => $categoryA->id]);
        Product::factory()->count(2)->create(['category_id' => $categoryB->id]);

        $response = $this->getJson(route('api.v1.categories.products', $categoryA));

        $response->assertOk()
            ->assertJsonStructure([
                'success',
                'message',
                'data' => [
                    '*' => ['id', 'name', 'price', 'stock', 'category_id'],
                ],
                'meta',
                'links',
            ]);

        $this->assertCount(4, $response->json('data'));
    }
}
