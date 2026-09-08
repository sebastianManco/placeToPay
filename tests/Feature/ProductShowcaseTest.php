<?php

namespace Tests\Feature;

use App\Models\Category;
use App\Models\Product;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ProductShowcaseTest extends TestCase
{
    use RefreshDatabase;

    private function createClientUser(array $attributes = []): User
    {
        return User::create(array_merge([
            'identification' => 12345678,
            'name' => 'Cliente',
            'last_Name' => 'Prueba',
            'email' => 'cliente@example.com',
            'phone' => '3001234567',
            'direction' => 'Calle 123 # 45 - 67',
            'user_Name' => 'clienteprueba',
            'password' => 'secret1234',
            'email_verified_at' => now(),
            'is_active' => true,
            'role' => 'client',
        ], $attributes));
    }

    /**
     * Test guests cannot access the product showcase.
     */
    public function test_guests_cannot_view_product_showcase(): void
    {
        $response = $this->get('/dashboard');
        $response->assertStatus(302);
        $response->assertRedirect('/home/login');

        $responseAlias = $this->get('/showcase');
        $responseAlias->assertStatus(302);
        $responseAlias->assertRedirect('/home/login');
    }

    /**
     * Test unverified users cannot view the product showcase.
     */
    public function test_unverified_client_cannot_view_product_showcase(): void
    {
        $client = $this->createClientUser(['email_verified_at' => null]);

        $response = $this->actingAs($client)->get('/dashboard');
        $response->assertStatus(302);
        $response->assertRedirect('/email/verify');
    }

    /**
     * Test verified registered client can view the showcase.
     */
    public function test_verified_registered_client_can_view_product_showcase(): void
    {
        $client = $this->createClientUser();

        $response = $this->actingAs($client)->get('/dashboard');

        $response->assertStatus(200);
        $response->assertViewIs('dashboard');
        $response->assertSee('Vitrina de Productos');
    }

    /**
     * Test showcase displays active products with image, formatted price and details.
     */
    public function test_product_showcase_displays_active_products_with_photo_and_price(): void
    {
        $client = $this->createClientUser();

        $category = Category::create([
            'name' => 'Lácteos y Huevos',
            'description' => 'Productos lácteos frescos',
            'is_active' => true,
        ]);

        $product = Product::create([
            'name' => 'Leche Entera 1L',
            'description' => 'Bolsa de leche entera pasteurizada',
            'price' => 4500.50,
            'stock' => 25,
            'image' => 'products/leche_entera.jpg',
            'is_active' => true,
            'category_id' => $category->id,
        ]);

        $response = $this->actingAs($client)->get('/dashboard');

        $response->assertStatus(200);
        $response->assertSee('Leche Entera 1L');
        $response->assertSee('Lácteos y Huevos');
        $response->assertSee('$4.500,50');
        $response->assertSee('products/leche_entera.jpg');
        $response->assertSee('25');
    }

    /**
     * Test inactive products are not displayed in the client showcase.
     */
    public function test_product_showcase_does_not_display_inactive_products(): void
    {
        $client = $this->createClientUser();

        Product::create([
            'name' => 'Producto Oculto',
            'description' => 'No debe verse por clientes',
            'price' => 1000.00,
            'stock' => 5,
            'is_active' => false,
        ]);

        Product::create([
            'name' => 'Producto Visible',
            'description' => 'Debe verse en la vitrina',
            'price' => 2000.00,
            'stock' => 10,
            'is_active' => true,
        ]);

        $response = $this->actingAs($client)->get('/dashboard');

        $response->assertStatus(200);
        $response->assertSee('Producto Visible');
        $response->assertDontSee('Producto Oculto');
    }

    /**
     * Test showcase products are paginated.
     */
    public function test_product_showcase_is_paginated(): void
    {
        $client = $this->createClientUser();

        for ($i = 1; $i <= 15; $i++) {
            Product::create([
                'name' => sprintf('Producto %02d', $i),
                'description' => 'Descripción del producto ' . $i,
                'price' => 1000.00 + ($i * 100),
                'stock' => 10,
                'is_active' => true,
            ]);
        }

        // Page 1: Default 12 per page
        $responsePage1 = $this->actingAs($client)->get('/dashboard?page=1');
        $responsePage1->assertStatus(200);
        $responsePage1->assertSee('Producto 01');
        $responsePage1->assertSee('Producto 12');
        $responsePage1->assertDontSee('Producto 13');

        // Page 2: remaining products
        $responsePage2 = $this->actingAs($client)->get('/dashboard?page=2');
        $responsePage2->assertStatus(200);
        $responsePage2->assertSee('Producto 13');
        $responsePage2->assertSee('Producto 15');
        $responsePage2->assertDontSee('Producto 01');
    }

    /**
     * Test fallback placeholder is rendered when product has no image.
     */
    public function test_product_showcase_renders_placeholder_when_product_has_no_image(): void
    {
        $client = $this->createClientUser();

        Product::create([
            'name' => 'Producto Sin Foto',
            'description' => 'No tiene imagen adjunta',
            'price' => 5000.00,
            'stock' => 3,
            'image' => null,
            'is_active' => true,
        ]);

        $response = $this->actingAs($client)->get('/dashboard');

        $response->assertStatus(200);
        $response->assertSee('Producto Sin Foto');
        $response->assertSee('Sin imagen');
    }

    /**
     * Test client can filter showcase by keyword search.
     */
    public function test_product_showcase_can_filter_by_search(): void
    {
        $client = $this->createClientUser();

        Product::create([
            'name' => 'Café Colombiano Premium',
            'description' => 'Grano molido 500g',
            'price' => 15000.00,
            'stock' => 15,
            'is_active' => true,
        ]);

        Product::create([
            'name' => 'Azúcar Morena',
            'description' => 'Endulzante natural 1kg',
            'price' => 3000.00,
            'stock' => 20,
            'is_active' => true,
        ]);

        $response = $this->actingAs($client)->get('/dashboard?search=Café');

        $response->assertStatus(200);
        $response->assertSee('Café Colombiano Premium');
        $response->assertDontSee('Azúcar Morena');
    }

    /**
     * Test client can filter showcase by category.
     */
    public function test_product_showcase_can_filter_by_category(): void
    {
        $client = $this->createClientUser();

        $catBebidas = Category::create(['name' => 'Bebidas', 'is_active' => true]);
        $catSnacks = Category::create(['name' => 'Snacks', 'is_active' => true]);

        Product::create([
            'name' => 'Jugo de Naranja',
            'price' => 3500.00,
            'stock' => 8,
            'is_active' => true,
            'category_id' => $catBebidas->id,
        ]);

        Product::create([
            'name' => 'Papas Fritas',
            'price' => 2500.00,
            'stock' => 12,
            'is_active' => true,
            'category_id' => $catSnacks->id,
        ]);

        $response = $this->actingAs($client)->get('/dashboard?category=' . $catBebidas->id);

        $response->assertStatus(200);
        $response->assertSee('Jugo de Naranja');
        $response->assertDontSee('Papas Fritas');
    }

    /**
     * Test empty state message when no active products exist.
     */
    public function test_product_showcase_shows_empty_message_when_no_products(): void
    {
        $client = $this->createClientUser();

        $response = $this->actingAs($client)->get('/dashboard');

        $response->assertStatus(200);
        $response->assertSee('No se encontraron productos');
        $response->assertSee('Actualmente no hay productos disponibles en la vitrina.');
    }
}
