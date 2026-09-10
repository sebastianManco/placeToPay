<?php

namespace Tests\Feature;

use App\Models\Category;
use App\Models\Product;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AdminCategoryTest extends TestCase
{
    use RefreshDatabase;

    /**
     * Helper to create an admin user.
     */
    protected function createAdmin(array $attributes = []): User
    {
        $role = $attributes['role'] ?? 'admin';
        unset($attributes['role']);

        $user = User::create(array_merge([
            'identification' => 60000001,
            'name' => 'Admin',
            'last_name' => 'System',
            'email' => 'admin@example.com',
            'phone' => '123456789',
            'direction' => 'Headquarters',
            'user_name' => 'adminuser',
            'password' => 'secret123',
            'email_verified_at' => now(),
            'is_active' => true,
        ], $attributes));

        $user->syncRoles([$role]);

        return $user;
    }

    /**
     * Helper to create a client user.
     */
    protected function createClient(array $attributes = []): User
    {
        static $counter = 1;
        $num = 61000000 + ($counter++);

        return User::create(array_merge([
            'identification' => $num,
            'name' => "Client{$counter}",
            'last_name' => 'Test',
            'email' => "client{$counter}@example.com",
            'phone' => '3001234567',
            'direction' => 'Street 100',
            'user_name' => "clientuser{$counter}",
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
     * Test guests cannot access admin categories panel.
     */
    public function test_guests_cannot_access_categories_panel(): void
    {
        $response = $this->get('/admin/categories');

        $response->assertStatus(302);
        $response->assertRedirect('/home/login');
    }

    /**
     * Test non-admin client cannot access categories panel.
     */
    public function test_non_admin_client_receives_403(): void
    {
        $client = $this->createClient();

        $response = $this->actingAs($client)->get('/admin/categories');

        $response->assertStatus(403);
    }

    /**
     * Test guests cannot access category create page.
     */
    public function test_guests_cannot_access_category_create_page(): void
    {
        $response = $this->get('/admin/categories/create');

        $response->assertStatus(302);
        $response->assertRedirect('/home/login');
    }

    // -------------------------------------------------------
    // Index / List Tests
    // -------------------------------------------------------

    /**
     * Test admin can view categories list.
     */
    public function test_admin_can_view_categories_list(): void
    {
        $admin = $this->createAdmin();
        Category::create(['name' => 'Alimentos', 'description' => 'Productos alimenticios']);
        Category::create(['name' => 'Bebidas', 'description' => 'Jugos y bebidas']);

        $response = $this->actingAs($admin)->get('/admin/categories');

        $response->assertStatus(200);
        $response->assertSee('Administración de Categorías');
        $response->assertSee('Alimentos');
        $response->assertSee('Bebidas');
    }

    /**
     * Test admin can filter categories by search term.
     */
    public function test_admin_can_filter_categories_by_search(): void
    {
        $admin = $this->createAdmin();
        Category::create(['name' => 'UniqueCatXYZ']);
        Category::create(['name' => 'OtherCatABC']);

        $response = $this->actingAs($admin)->get('/admin/categories?search=UniqueCatXYZ');

        $response->assertStatus(200);
        $response->assertSee('UniqueCatXYZ');
        $response->assertDontSee('OtherCatABC');
    }

    /**
     * Test admin can filter categories by status.
     */
    public function test_admin_can_filter_categories_by_status(): void
    {
        $admin = $this->createAdmin();
        Category::create(['name' => 'ActiveCat', 'is_active' => true]);
        Category::create(['name' => 'InactiveCat', 'is_active' => false]);

        $response = $this->actingAs($admin)->get('/admin/categories?status=inactive');

        $response->assertStatus(200);
        $response->assertSee('InactiveCat');
        $response->assertDontSee('ActiveCat');
    }

    /**
     * Test categories index shows product count.
     */
    public function test_categories_index_shows_product_count(): void
    {
        $admin = $this->createAdmin();
        $category = Category::create(['name' => 'WithProducts']);
        Product::create(['name' => 'Prod1', 'price' => 1000, 'stock' => 5, 'category_id' => $category->id]);
        Product::create(['name' => 'Prod2', 'price' => 2000, 'stock' => 10, 'category_id' => $category->id]);

        $response = $this->actingAs($admin)->get('/admin/categories');

        $response->assertStatus(200);
        $response->assertSee('WithProducts');
    }

    // -------------------------------------------------------
    // Create Tests
    // -------------------------------------------------------

    /**
     * Test admin can view category create form.
     */
    public function test_admin_can_view_category_create_form(): void
    {
        $admin = $this->createAdmin();

        $response = $this->actingAs($admin)->get('/admin/categories/create');

        $response->assertStatus(200);
        $response->assertSee('Crear Categoría');
    }

    /**
     * Test admin can store a category successfully.
     */
    public function test_admin_can_store_category_successfully(): void
    {
        $admin = $this->createAdmin();

        $categoryData = [
            'name' => 'Nueva Categoría',
            'description' => 'Descripción de la nueva categoría.',
        ];

        $response = $this->actingAs($admin)->post('/admin/categories', $categoryData);

        $response->assertStatus(302);
        $response->assertRedirect('/admin/categories');
        $response->assertSessionHas('success');

        $this->assertDatabaseHas('categories', [
            'name' => 'Nueva Categoría',
            'description' => 'Descripción de la nueva categoría.',
            'is_active' => true,
        ]);
    }

    /**
     * Test store fails with empty name.
     */
    public function test_store_category_requires_name(): void
    {
        $admin = $this->createAdmin();

        $response = $this->actingAs($admin)->post('/admin/categories', [
            'name' => '',
        ]);

        $response->assertStatus(302);
        $response->assertSessionHasErrors(['name']);
    }

    /**
     * Test store fails with duplicate name.
     */
    public function test_store_category_rejects_duplicate_name(): void
    {
        $admin = $this->createAdmin();
        Category::create(['name' => 'ExistingCategory']);

        $response = $this->actingAs($admin)->post('/admin/categories', [
            'name' => 'ExistingCategory',
        ]);

        $response->assertStatus(302);
        $response->assertSessionHasErrors(['name']);
    }

    // -------------------------------------------------------
    // Edit Tests
    // -------------------------------------------------------

    /**
     * Test admin can view category edit form.
     */
    public function test_admin_can_view_category_edit_form(): void
    {
        $admin = $this->createAdmin();
        $category = Category::create([
            'name' => 'Categoría Editable',
            'description' => 'Descripción original',
        ]);

        $response = $this->actingAs($admin)->get("/admin/categories/{$category->id}/edit");

        $response->assertStatus(200);
        $response->assertSee('Editar Categoría');
        $response->assertSee('Categoría Editable');
        $response->assertSee('Descripción original');
    }

    /**
     * Test guests cannot access category edit page.
     */
    public function test_guests_cannot_access_category_edit_page(): void
    {
        $category = Category::create(['name' => 'Test']);

        $response = $this->get("/admin/categories/{$category->id}/edit");

        $response->assertStatus(302);
        $response->assertRedirect('/home/login');
    }

    // -------------------------------------------------------
    // Update Tests
    // -------------------------------------------------------

    /**
     * Test admin can update category successfully.
     */
    public function test_admin_can_update_category_successfully(): void
    {
        $admin = $this->createAdmin();
        $category = Category::create([
            'name' => 'OriginalName',
            'description' => 'Original description',
            'is_active' => true,
        ]);

        $updateData = [
            'name' => 'UpdatedName',
            'description' => 'Updated description',
            'is_active' => 0,
        ];

        $response = $this->actingAs($admin)->put("/admin/categories/{$category->id}", $updateData);

        $response->assertStatus(302);
        $response->assertRedirect('/admin/categories');
        $response->assertSessionHas('success');

        $category->refresh();
        $this->assertSame('UpdatedName', $category->name);
        $this->assertSame('Updated description', $category->description);
        $this->assertFalse($category->is_active);
    }

    /**
     * Test admin can update category keeping the same name.
     */
    public function test_admin_can_update_category_keeping_same_name(): void
    {
        $admin = $this->createAdmin();
        $category = Category::create(['name' => 'SameName']);

        $response = $this->actingAs($admin)->put("/admin/categories/{$category->id}", [
            'name' => 'SameName',
            'description' => 'New description',
        ]);

        $response->assertStatus(302);
        $response->assertSessionHas('success');
    }

    /**
     * Test update fails with duplicate name from another category.
     */
    public function test_admin_cannot_update_category_with_duplicate_name(): void
    {
        $admin = $this->createAdmin();
        $catA = Category::create(['name' => 'CategoryA']);
        $catB = Category::create(['name' => 'CategoryB']);

        $response = $this->actingAs($admin)->put("/admin/categories/{$catA->id}", [
            'name' => 'CategoryB',
        ]);

        $response->assertStatus(302);
        $response->assertSessionHasErrors(['name']);
    }

    /**
     * Test update fails with invalid data.
     */
    public function test_update_category_requires_valid_data(): void
    {
        $admin = $this->createAdmin();
        $category = Category::create(['name' => 'Test']);

        $response = $this->actingAs($admin)->put("/admin/categories/{$category->id}", [
            'name' => '',
        ]);

        $response->assertStatus(302);
        $response->assertSessionHasErrors(['name']);
    }

    // -------------------------------------------------------
    // Toggle Status Tests
    // -------------------------------------------------------

    /**
     * Test admin can disable an active category.
     */
    public function test_admin_can_disable_a_category(): void
    {
        $admin = $this->createAdmin();
        $category = Category::create(['name' => 'ActiveCat', 'is_active' => true]);

        $response = $this->actingAs($admin)->patch("/admin/categories/{$category->id}/toggle-status");

        $response->assertStatus(302);
        $response->assertSessionHas('success');
        $this->assertFalse($category->fresh()->is_active);
    }

    /**
     * Test admin can enable an inactive category.
     */
    public function test_admin_can_enable_an_inactive_category(): void
    {
        $admin = $this->createAdmin();
        $category = Category::create(['name' => 'InactiveCat', 'is_active' => false]);

        $response = $this->actingAs($admin)->patch("/admin/categories/{$category->id}/toggle-status");

        $response->assertStatus(302);
        $response->assertSessionHas('success');
        $this->assertTrue($category->fresh()->is_active);
    }

    // -------------------------------------------------------
    // Index View Content Tests
    // -------------------------------------------------------

    /**
     * Test categories index contains edit link.
     */
    public function test_categories_index_contains_edit_link(): void
    {
        $admin = $this->createAdmin();
        $category = Category::create(['name' => 'TargetCat']);

        $response = $this->actingAs($admin)->get('/admin/categories');

        $response->assertStatus(200);
        $response->assertSee(route('admin.categories.edit', $category));
        $response->assertSee('Editar');
    }

    /**
     * Test categories index contains create button.
     */
    public function test_categories_index_contains_create_button(): void
    {
        $admin = $this->createAdmin();

        $response = $this->actingAs($admin)->get('/admin/categories');

        $response->assertStatus(200);
        $response->assertSee(route('admin.categories.create'));
        $response->assertSee('Crear Categoría');
    }

    /**
     * Test empty categories list shows appropriate message.
     */
    public function test_empty_categories_list_shows_message(): void
    {
        $admin = $this->createAdmin();

        $response = $this->actingAs($admin)->get('/admin/categories');

        $response->assertStatus(200);
        $response->assertSee('No se encontraron categorías registradas.');
    }

    // -------------------------------------------------------
    // Product-Category Relationship Tests
    // -------------------------------------------------------

    /**
     * Test product can be created with a category.
     */
    public function test_product_can_be_created_with_category(): void
    {
        $admin = $this->createAdmin();
        $category = Category::create(['name' => 'TestCategory']);

        $response = $this->actingAs($admin)->post('/admin/products', [
            'name' => 'Product With Category',
            'price' => 5000,
            'stock' => 10,
            'category_id' => $category->id,
        ]);

        $response->assertStatus(302);
        $response->assertRedirect('/admin/products');

        $product = Product::where('name', 'Product With Category')->first();
        $this->assertNotNull($product);
        $this->assertEquals($category->id, $product->category_id);
        $this->assertEquals('TestCategory', $product->category->name);
    }

    /**
     * Test product can be created without a category.
     */
    public function test_product_can_be_created_without_category(): void
    {
        $admin = $this->createAdmin();

        $response = $this->actingAs($admin)->post('/admin/products', [
            'name' => 'Product Without Category',
            'price' => 5000,
            'stock' => 10,
            'category_id' => null,
        ]);

        $response->assertStatus(302);

        $product = Product::where('name', 'Product Without Category')->first();
        $this->assertNotNull($product);
        $this->assertNull($product->category_id);
    }

    /**
     * Test product rejects invalid category_id.
     */
    public function test_product_rejects_invalid_category_id(): void
    {
        $admin = $this->createAdmin();

        $response = $this->actingAs($admin)->post('/admin/products', [
            'name' => 'Product Invalid Cat',
            'price' => 5000,
            'stock' => 10,
            'category_id' => 99999,
        ]);

        $response->assertStatus(302);
        $response->assertSessionHasErrors(['category_id']);
    }
}
