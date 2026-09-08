<?php

namespace Tests\Feature;

use App\Models\Product;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class AdminProductTest extends TestCase
{
    use RefreshDatabase;

    /**
     * Helper to create an admin user.
     */
    protected function createAdmin(array $attributes = []): User
    {
        return User::create(array_merge([
            'identification' => 50000001,
            'name' => 'Admin',
            'last_Name' => 'System',
            'email' => 'admin@example.com',
            'phone' => '123456789',
            'direction' => 'Headquarters',
            'user_Name' => 'adminuser',
            'password' => 'secret123',
            'email_verified_at' => now(),
            'is_active' => true,
            'role' => 'admin',
        ], $attributes));
    }

    /**
     * Helper to create a client user.
     */
    protected function createClient(array $attributes = []): User
    {
        static $counter = 1;
        $num = 51000000 + ($counter++);

        return User::create(array_merge([
            'identification' => $num,
            'name' => "Client{$counter}",
            'last_Name' => 'Test',
            'email' => "client{$counter}@example.com",
            'phone' => '3001234567',
            'direction' => 'Street 100',
            'user_Name' => "clientuser{$counter}",
            'password' => 'secret123',
            'email_verified_at' => now(),
            'is_active' => true,
            'role' => 'client',
        ], $attributes));
    }

    // -------------------------------------------------------
    // Authorization Tests
    // -------------------------------------------------------

    /**
     * Test guests cannot access admin products panel.
     */
    public function test_guests_cannot_access_products_panel(): void
    {
        $response = $this->get('/admin/products');

        $response->assertStatus(302);
        $response->assertRedirect('/home/login');
    }

    /**
     * Test non-admin client cannot access products panel.
     */
    public function test_non_admin_client_receives_403(): void
    {
        $client = $this->createClient();

        $response = $this->actingAs($client)->get('/admin/products');

        $response->assertStatus(403);
    }

    /**
     * Test guests cannot access product create page.
     */
    public function test_guests_cannot_access_product_create_page(): void
    {
        $response = $this->get('/admin/products/create');

        $response->assertStatus(302);
        $response->assertRedirect('/home/login');
    }

    // -------------------------------------------------------
    // Index / List Tests
    // -------------------------------------------------------

    /**
     * Test admin can view products list.
     */
    public function test_admin_can_view_products_list(): void
    {
        $admin = $this->createAdmin();
        Product::create(['name' => 'Arroz Premium', 'price' => 15000, 'stock' => 50]);
        Product::create(['name' => 'Aceite Girasol', 'price' => 12000, 'stock' => 30]);

        $response = $this->actingAs($admin)->get('/admin/products');

        $response->assertStatus(200);
        $response->assertSee('Administración de Productos');
        $response->assertSee('Arroz Premium');
        $response->assertSee('Aceite Girasol');
    }

    /**
     * Test admin can filter products by search term.
     */
    public function test_admin_can_filter_products_by_search(): void
    {
        $admin = $this->createAdmin();
        Product::create(['name' => 'UniqueProductXYZ', 'price' => 5000, 'stock' => 10]);
        Product::create(['name' => 'OtherProductABC', 'price' => 8000, 'stock' => 20]);

        $response = $this->actingAs($admin)->get('/admin/products?search=UniqueProductXYZ');

        $response->assertStatus(200);
        $response->assertSee('UniqueProductXYZ');
        $response->assertDontSee('OtherProductABC');
    }

    /**
     * Test admin can filter products by status.
     */
    public function test_admin_can_filter_products_by_status(): void
    {
        $admin = $this->createAdmin();
        Product::create(['name' => 'ActiveProduct', 'price' => 5000, 'stock' => 10, 'is_active' => true]);
        Product::create(['name' => 'InactiveProduct', 'price' => 8000, 'stock' => 0, 'is_active' => false]);

        $response = $this->actingAs($admin)->get('/admin/products?status=inactive');

        $response->assertStatus(200);
        $response->assertSee('InactiveProduct');
        $response->assertDontSee('ActiveProduct');
    }

    // -------------------------------------------------------
    // Create Tests
    // -------------------------------------------------------

    /**
     * Test admin can view product create form.
     */
    public function test_admin_can_view_product_create_form(): void
    {
        $admin = $this->createAdmin();

        $response = $this->actingAs($admin)->get('/admin/products/create');

        $response->assertStatus(200);
        $response->assertSee('Crear Producto');
    }

    /**
     * Test admin can store a product successfully.
     */
    public function test_admin_can_store_product_successfully(): void
    {
        $admin = $this->createAdmin();

        $productData = [
            'name' => 'Nuevo Producto Test',
            'description' => 'Descripción del producto de prueba.',
            'price' => 25000.50,
            'stock' => 100,
        ];

        $response = $this->actingAs($admin)->post('/admin/products', $productData);

        $response->assertStatus(302);
        $response->assertRedirect('/admin/products');
        $response->assertSessionHas('success');

        $this->assertDatabaseHas('products', [
            'name' => 'Nuevo Producto Test',
            'description' => 'Descripción del producto de prueba.',
            'stock' => 100,
            'is_active' => true,
        ]);
    }

    /**
     * Test admin can store a product with image.
     */
    public function test_admin_can_store_product_with_image(): void
    {
        Storage::fake('public');
        $admin = $this->createAdmin();

        $productData = [
            'name' => 'Producto Con Imagen',
            'price' => 15000,
            'stock' => 20,
            'image' => UploadedFile::fake()->create('product.jpg', 100, 'image/jpeg'),
        ];

        $response = $this->actingAs($admin)->post('/admin/products', $productData);

        $response->assertStatus(302);
        $response->assertRedirect('/admin/products');

        $product = Product::where('name', 'Producto Con Imagen')->first();
        $this->assertNotNull($product);
        $this->assertNotNull($product->image);
        Storage::disk('public')->assertExists($product->image);
    }

    /**
     * Test store fails with missing required fields.
     */
    public function test_store_product_requires_valid_data(): void
    {
        $admin = $this->createAdmin();

        $response = $this->actingAs($admin)->post('/admin/products', [
            'name' => '',
            'price' => '',
            'stock' => '',
        ]);

        $response->assertStatus(302);
        $response->assertSessionHasErrors(['name', 'price', 'stock']);
    }

    /**
     * Test store fails with negative price.
     */
    public function test_store_product_rejects_negative_price(): void
    {
        $admin = $this->createAdmin();

        $response = $this->actingAs($admin)->post('/admin/products', [
            'name' => 'Producto Negativo',
            'price' => -100,
            'stock' => 10,
        ]);

        $response->assertStatus(302);
        $response->assertSessionHasErrors(['price']);
    }

    // -------------------------------------------------------
    // Edit Tests
    // -------------------------------------------------------

    /**
     * Test admin can view product edit form.
     */
    public function test_admin_can_view_product_edit_form(): void
    {
        $admin = $this->createAdmin();
        $product = Product::create([
            'name' => 'Producto Editable',
            'description' => 'Descripción original',
            'price' => 10000,
            'stock' => 25,
        ]);

        $response = $this->actingAs($admin)->get("/admin/products/{$product->id}/edit");

        $response->assertStatus(200);
        $response->assertSee('Editar Producto');
        $response->assertSee('Producto Editable');
        $response->assertSee('Descripción original');
    }

    /**
     * Test guests cannot access product edit page.
     */
    public function test_guests_cannot_access_product_edit_page(): void
    {
        $product = Product::create(['name' => 'Test', 'price' => 1000, 'stock' => 1]);

        $response = $this->get("/admin/products/{$product->id}/edit");

        $response->assertStatus(302);
        $response->assertRedirect('/home/login');
    }

    /**
     * Test non-admin cannot access product edit page.
     */
    public function test_non_admin_cannot_access_product_edit_page(): void
    {
        $client = $this->createClient();
        $product = Product::create(['name' => 'Test', 'price' => 1000, 'stock' => 1]);

        $response = $this->actingAs($client)->get("/admin/products/{$product->id}/edit");

        $response->assertStatus(403);
    }

    // -------------------------------------------------------
    // Update Tests
    // -------------------------------------------------------

    /**
     * Test admin can update product successfully.
     */
    public function test_admin_can_update_product_successfully(): void
    {
        $admin = $this->createAdmin();
        $product = Product::create([
            'name' => 'OriginalName',
            'description' => 'Original description',
            'price' => 10000,
            'stock' => 25,
            'is_active' => true,
        ]);

        $updateData = [
            'name' => 'UpdatedName',
            'description' => 'Updated description',
            'price' => 20000.75,
            'stock' => 50,
            'is_active' => 0,
        ];

        $response = $this->actingAs($admin)->put("/admin/products/{$product->id}", $updateData);

        $response->assertStatus(302);
        $response->assertRedirect('/admin/products');
        $response->assertSessionHas('success');

        $product->refresh();
        $this->assertSame('UpdatedName', $product->name);
        $this->assertSame('Updated description', $product->description);
        $this->assertEquals(50, $product->stock);
        $this->assertFalse($product->is_active);
    }

    /**
     * Test update fails with invalid data.
     */
    public function test_update_product_requires_valid_data(): void
    {
        $admin = $this->createAdmin();
        $product = Product::create(['name' => 'Test', 'price' => 1000, 'stock' => 1]);

        $response = $this->actingAs($admin)->put("/admin/products/{$product->id}", [
            'name' => '',
            'price' => 'not-a-number',
            'stock' => -5,
        ]);

        $response->assertStatus(302);
        $response->assertSessionHasErrors(['name', 'price', 'stock']);
    }

    // -------------------------------------------------------
    // Toggle Status Tests
    // -------------------------------------------------------

    /**
     * Test admin can disable an active product.
     */
    public function test_admin_can_disable_a_product(): void
    {
        $admin = $this->createAdmin();
        $product = Product::create([
            'name' => 'Active Product',
            'price' => 5000,
            'stock' => 10,
            'is_active' => true,
        ]);

        $response = $this->actingAs($admin)->patch("/admin/products/{$product->id}/toggle-status");

        $response->assertStatus(302);
        $response->assertSessionHas('success');
        $this->assertFalse($product->fresh()->is_active);
    }

    /**
     * Test admin can enable an inactive product.
     */
    public function test_admin_can_enable_an_inactive_product(): void
    {
        $admin = $this->createAdmin();
        $product = Product::create([
            'name' => 'Inactive Product',
            'price' => 5000,
            'stock' => 10,
            'is_active' => false,
        ]);

        $response = $this->actingAs($admin)->patch("/admin/products/{$product->id}/toggle-status");

        $response->assertStatus(302);
        $response->assertSessionHas('success');
        $this->assertTrue($product->fresh()->is_active);
    }

    // -------------------------------------------------------
    // Index View Content Tests
    // -------------------------------------------------------

    /**
     * Test products index contains edit link for each product.
     */
    public function test_products_index_contains_edit_link(): void
    {
        $admin = $this->createAdmin();
        $product = Product::create(['name' => 'TargetProduct', 'price' => 5000, 'stock' => 10]);

        $response = $this->actingAs($admin)->get('/admin/products');

        $response->assertStatus(200);
        $response->assertSee(route('admin.products.edit', $product));
        $response->assertSee('Editar');
    }

    /**
     * Test products index contains create button.
     */
    public function test_products_index_contains_create_button(): void
    {
        $admin = $this->createAdmin();

        $response = $this->actingAs($admin)->get('/admin/products');

        $response->assertStatus(200);
        $response->assertSee(route('admin.products.create'));
        $response->assertSee('Crear Producto');
    }

    /**
     * Test empty products list shows appropriate message.
     */
    public function test_empty_products_list_shows_message(): void
    {
        $admin = $this->createAdmin();

        $response = $this->actingAs($admin)->get('/admin/products');

        $response->assertStatus(200);
        $response->assertSee('No se encontraron productos registrados.');
    }
}
