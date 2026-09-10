<?php

namespace Tests\Feature\Api\V1;

use App\Models\Category;
use App\Models\Product;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class ProductApiTest extends TestCase
{
    use RefreshDatabase;

    /**
     * Helper to create an admin user for testing.
     */
    protected function createAdminUser(): User
    {
        $user = User::create([
            'identification' => 60000001,
            'name' => 'Admin',
            'last_name' => 'Tester',
            'email' => 'admin.product@test.com',
            'phone' => '3001234567',
            'direction' => 'Admin Avenue',
            'user_name' => 'adminproduct',
            'password' => 'secret123',
            'email_verified_at' => now(),
            'is_active' => true,
        ]);
        $user->syncRoles(['admin']);

        return $user;
    }

    /**
     * Helper to create a regular client user without permissions.
     */
    protected function createClientUser(): User
    {
        return User::create([
            'identification' => 60000002,
            'name' => 'Client',
            'last_name' => 'Tester',
            'email' => 'client.product@test.com',
            'phone' => '3009876543',
            'direction' => 'Client Street',
            'user_name' => 'clientproduct',
            'password' => 'secret123',
            'email_verified_at' => now(),
            'is_active' => true,
            'role' => 'client',
        ]);
    }

    /**
     * Test listing products with pagination (Public Endpoint).
     */
    public function test_can_list_products_with_pagination(): void
    {
        $category = Category::factory()->create();
        Product::factory()->count(12)->create(['category_id' => $category->id]);

        $response = $this->getJson(route('api.v1.products.index', ['per_page' => 5]));

        $response->assertOk()
            ->assertJsonStructure([
                'success',
                'message',
                'data' => [
                    '*' => [
                        'id',
                        'name',
                        'description',
                        'price',
                        'formatted_price',
                        'stock',
                        'in_stock',
                        'image_url',
                        'is_active',
                        'category_id',
                        'category' => [
                            'id',
                            'name',
                        ],
                        'created_at',
                        'updated_at',
                    ],
                ],
                'meta' => [
                    'current_page',
                    'per_page',
                    'total',
                    'last_page',
                ],
                'links',
            ]);

        $this->assertCount(5, $response->json('data'));
        $this->assertEquals(12, $response->json('meta.total'));
    }

    /**
     * Test filtering products by category and stock (Public Endpoint).
     */
    public function test_can_filter_products_by_category_and_stock(): void
    {
        $catA = Category::factory()->create();
        $catB = Category::factory()->create();

        Product::factory()->create(['category_id' => $catA->id, 'stock' => 10, 'price' => 50000]);
        Product::factory()->create(['category_id' => $catA->id, 'stock' => 0, 'price' => 70000]);
        Product::factory()->create(['category_id' => $catB->id, 'stock' => 5, 'price' => 30000]);

        // Filter by Category A
        $responseCat = $this->getJson(route('api.v1.products.index', ['category' => $catA->id]));
        $responseCat->assertOk();
        $this->assertCount(2, $responseCat->json('data'));

        // Filter by Category A and in_stock
        $responseInStock = $this->getJson(route('api.v1.products.index', [
            'category' => $catA->id,
            'in_stock' => 1,
        ]));
        $responseInStock->assertOk();
        $this->assertCount(1, $responseInStock->json('data'));
    }

    /**
     * Test filtering by price range and searching (Public Endpoint).
     */
    public function test_can_filter_by_price_and_search(): void
    {
        Product::factory()->create(['name' => 'Smartphone Pro Max', 'price' => 1200000]);
        Product::factory()->create(['name' => 'Audífonos Bluetooth', 'price' => 150000]);
        Product::factory()->create(['name' => 'Cable USB-C', 'price' => 25000]);

        // Search text
        $searchResponse = $this->getJson(route('api.v1.products.index', ['search' => 'Smart']));
        $searchResponse->assertOk();
        $this->assertCount(1, $searchResponse->json('data'));
        $this->assertEquals('Smartphone Pro Max', $searchResponse->json('data.0.name'));

        // Price range
        $priceResponse = $this->getJson(route('api.v1.products.index', [
            'min_price' => 100000,
            'max_price' => 500000,
        ]));
        $priceResponse->assertOk();
        $this->assertCount(1, $priceResponse->json('data'));
        $this->assertEquals('Audífonos Bluetooth', $priceResponse->json('data.0.name'));
    }

    /**
     * Test sorting products (Public Endpoint).
     */
    public function test_can_sort_products(): void
    {
        Product::factory()->create(['name' => 'Producto Caro', 'price' => 900000]);
        Product::factory()->create(['name' => 'Producto Barato', 'price' => 10000]);

        $sortAsc = $this->getJson(route('api.v1.products.index', ['sort_by' => 'price_asc']));
        $sortAsc->assertOk();
        $this->assertEquals('Producto Barato', $sortAsc->json('data.0.name'));

        $sortDesc = $this->getJson(route('api.v1.products.index', ['sort_by' => 'price_desc']));
        $sortDesc->assertOk();
        $this->assertEquals('Producto Caro', $sortDesc->json('data.0.name'));
    }

    /**
     * Test retrieving a single product (Public Endpoint).
     */
    public function test_can_show_product(): void
    {
        $category = Category::factory()->create(['name' => 'Tecnología']);
        $product = Product::factory()->create([
            'name' => 'Laptop Gamer',
            'category_id' => $category->id,
            'price' => 3500000,
            'stock' => 8,
        ]);

        $response = $this->getJson(route('api.v1.products.show', $product));

        $response->assertOk()
            ->assertJson([
                'success' => true,
                'data' => [
                    'id' => $product->id,
                    'name' => 'Laptop Gamer',
                    'price' => 3500000.00,
                    'stock' => 8,
                    'in_stock' => true,
                    'category' => [
                        'id' => $category->id,
                        'name' => 'Tecnología',
                    ],
                ],
            ]);
    }

    /**
     * Test 404 response for non-existent product.
     */
    public function test_returns_404_when_product_not_found(): void
    {
        $response = $this->getJson('/api/v1/products/88888');

        $response->assertNotFound();
    }

    /**
     * Test unauthenticated request to create product returns 401.
     */
    public function test_unauthenticated_user_cannot_create_product(): void
    {
        $response = $this->postJson(route('api.v1.products.store'), [
            'name' => 'Intento No Autorizado',
        ]);

        $response->assertUnauthorized();
    }

    /**
     * Test client without permissions cannot create product and gets 403 Forbidden.
     */
    public function test_client_without_permission_cannot_create_product(): void
    {
        $client = $this->createClientUser();
        Sanctum::actingAs($client, ['*']);

        $category = Category::factory()->create();

        $response = $this->postJson(route('api.v1.products.store'), [
            'name' => 'Nuevo Producto Bloqueado',
            'description' => 'Test',
            'price' => 10000,
            'stock' => 5,
            'category_id' => $category->id,
        ]);

        $response->assertForbidden();
    }

    /**
     * Test creating a product with authentication returns 201 Created.
     */
    public function test_can_create_product(): void
    {
        $admin = $this->createAdminUser();
        Sanctum::actingAs($admin, ['*']);

        $category = Category::factory()->create();

        $payload = [
            'name' => 'Monitor 27 Pulgadas 4K',
            'description' => 'Monitor IPS con resolución UHD',
            'price' => 1250000.50,
            'stock' => 15,
            'category_id' => $category->id,
            'is_active' => true,
        ];

        $response = $this->postJson(route('api.v1.products.store'), $payload);

        $response->assertCreated()
            ->assertHeader('Location')
            ->assertJson([
                'success' => true,
                'data' => [
                    'name' => 'Monitor 27 Pulgadas 4K',
                    'price' => 1250000.50,
                    'stock' => 15,
                    'category_id' => $category->id,
                ],
            ]);

        $this->assertDatabaseHas('products', [
            'name' => 'Monitor 27 Pulgadas 4K',
            'stock' => 15,
        ]);
    }

    /**
     * Test creating product with image upload.
     */
    public function test_can_create_product_with_image(): void
    {
        $admin = $this->createAdminUser();
        Sanctum::actingAs($admin, ['*']);

        Storage::fake('public');

        $file = UploadedFile::fake()->create('monitor.jpg', 100, 'image/jpeg');

        $response = $this->post(route('api.v1.products.store'), [
            'name' => 'Teclado Mecánico RGB',
            'description' => 'Switches Red para gaming',
            'price' => 180000,
            'stock' => 20,
            'image' => $file,
        ], ['Accept' => 'application/json']);

        $response->assertCreated()
            ->assertJson([
                'success' => true,
                'data' => [
                    'name' => 'Teclado Mecánico RGB',
                ],
            ]);

        $product = Product::where('name', 'Teclado Mecánico RGB')->first();
        $this->assertNotNull($product->image);
        Storage::disk('public')->assertExists($product->image);
    }

    /**
     * Test validation error returns 422 with structured errors.
     */
    public function test_cannot_create_product_with_invalid_data(): void
    {
        $admin = $this->createAdminUser();
        Sanctum::actingAs($admin, ['*']);

        $response = $this->postJson(route('api.v1.products.store'), [
            'name' => '',
            'price' => -100,
            'stock' => -5,
            'category_id' => 99999,
        ]);

        $response->assertStatus(422)
            ->assertJson([
                'success' => false,
                'message' => 'Los datos enviados no son válidos.',
            ])
            ->assertJsonValidationErrors(['name', 'price', 'stock', 'category_id']);
    }

    /**
     * Test full update via PUT.
     */
    public function test_can_update_product_via_put(): void
    {
        $admin = $this->createAdminUser();
        Sanctum::actingAs($admin, ['*']);

        $category = Category::factory()->create();
        $product = Product::factory()->create(['category_id' => $category->id]);

        $payload = [
            'name' => 'Nombre Totalmente Modificado',
            'description' => 'Nueva descripción completa',
            'price' => 450000,
            'stock' => 35,
            'category_id' => $category->id,
            'is_active' => false,
        ];

        $response = $this->putJson(route('api.v1.products.update', $product), $payload);

        $response->assertOk()
            ->assertJson([
                'success' => true,
                'data' => [
                    'id' => $product->id,
                    'name' => 'Nombre Totalmente Modificado',
                    'price' => 450000.00,
                    'stock' => 35,
                    'is_active' => false,
                ],
            ]);

        $this->assertDatabaseHas('products', [
            'id' => $product->id,
            'name' => 'Nombre Totalmente Modificado',
            'stock' => 35,
        ]);
    }

    /**
     * Test partial update via PATCH.
     */
    public function test_can_update_product_via_patch(): void
    {
        $admin = $this->createAdminUser();
        Sanctum::actingAs($admin, ['*']);

        $product = Product::factory()->create([
            'name' => 'Nombre Original',
            'price' => 100000,
            'stock' => 10,
        ]);

        $response = $this->patchJson(route('api.v1.products.patch', $product), [
            'price' => 120000,
        ]);

        $response->assertOk()
            ->assertJson([
                'success' => true,
                'data' => [
                    'id' => $product->id,
                    'name' => 'Nombre Original',
                    'price' => 120000.00,
                    'stock' => 10,
                ],
            ]);

        $this->assertDatabaseHas('products', [
            'id' => $product->id,
            'price' => 120000,
            'name' => 'Nombre Original',
        ]);
    }

    /**
     * Test updating product stock via PATCH /products/{id}/stock with absolute value.
     */
    public function test_can_update_stock_with_absolute_value(): void
    {
        $admin = $this->createAdminUser();
        Sanctum::actingAs($admin, ['*']);

        $product = Product::factory()->create(['stock' => 10]);

        $response = $this->patchJson(route('api.v1.products.stock', $product), [
            'stock' => 50,
        ]);

        $response->assertOk()
            ->assertJson([
                'success' => true,
                'data' => [
                    'id' => $product->id,
                    'stock' => 50,
                ],
            ]);

        $this->assertEquals(50, $product->fresh()->stock);
    }

    /**
     * Test updating product stock via PATCH /products/{id}/stock with relative adjustment.
     */
    public function test_can_update_stock_with_relative_adjustment(): void
    {
        $admin = $this->createAdminUser();
        Sanctum::actingAs($admin, ['*']);

        $product = Product::factory()->create(['stock' => 20]);

        // Add 15
        $responseAdd = $this->patchJson(route('api.v1.products.stock', $product), [
            'adjustment' => 15,
        ]);
        $responseAdd->assertOk();
        $this->assertEquals(35, $product->fresh()->stock);

        // Deduct 10
        $responseDeduct = $this->patchJson(route('api.v1.products.stock', $product), [
            'adjustment' => -10,
        ]);
        $responseDeduct->assertOk();
        $this->assertEquals(25, $product->fresh()->stock);
    }

    /**
     * Test adjustment resulting in negative stock returns 422 Unprocessable Content.
     */
    public function test_cannot_adjust_stock_to_negative(): void
    {
        $admin = $this->createAdminUser();
        Sanctum::actingAs($admin, ['*']);

        $product = Product::factory()->create(['stock' => 5]);

        $response = $this->patchJson(route('api.v1.products.stock', $product), [
            'adjustment' => -10,
        ]);

        $response->assertStatus(422)
            ->assertJson([
                'success' => false,
            ]);

        $this->assertEquals(5, $product->fresh()->stock);
    }

    /**
     * Test deleting a product returns 204 No Content.
     */
    public function test_can_delete_product(): void
    {
        $admin = $this->createAdminUser();
        Sanctum::actingAs($admin, ['*']);

        $product = Product::factory()->create();

        $response = $this->deleteJson(route('api.v1.products.destroy', $product));

        $response->assertNoContent();
        $this->assertDatabaseMissing('products', ['id' => $product->id]);
    }

    /**
     * Test exporting products to spreadsheet (.xlsx or .csv) requires authentication.
     */
    public function test_unauthenticated_user_cannot_export_products(): void
    {
        $response = $this->get(route('api.v1.products.export', ['format' => 'csv']), ['Accept' => 'application/json']);

        $response->assertUnauthorized();
    }

    /**
     * Test exporting products to spreadsheet (.xlsx or .csv) when authenticated.
     */
    public function test_can_export_products_when_authenticated(): void
    {
        $admin = $this->createAdminUser();
        Sanctum::actingAs($admin, ['*']);

        Product::factory()->count(3)->create();

        $response = $this->get(route('api.v1.products.export', ['format' => 'csv']));

        $response->assertOk();
        $this->assertTrue(
            str_contains($response->headers->get('content-type', ''), 'text/csv') ||
            str_contains($response->headers->get('content-disposition', ''), 'productos_mercatodo_')
        );
    }
}
