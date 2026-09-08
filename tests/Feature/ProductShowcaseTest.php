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
     * Test guests cannot access the protected client dashboard.
     */
    public function test_guests_cannot_view_protected_dashboard(): void
    {
        $response = $this->get('/dashboard');
        $response->assertStatus(302);
        $response->assertRedirect('/home/login');
    }

    /**
     * Test guests can access the public product showcase and search.
     */
    public function test_guests_can_view_public_product_showcase_and_search(): void
    {
        $responseShowcase = $this->get('/showcase');
        $responseShowcase->assertStatus(200);
        $responseShowcase->assertViewIs('dashboard');
        $responseShowcase->assertSee('Vitrina de Productos');

        $responseHome = $this->get('/');
        $responseHome->assertStatus(200);
        $responseHome->assertViewIs('dashboard');
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
        $response->assertSee('Añadir al carrito');
    }

    /**
     * Test product showcase displays add to cart button for in stock products and agotado for zero stock.
     */
    public function test_product_showcase_displays_add_to_cart_and_out_of_stock_buttons(): void
    {
        Product::create([
            'name' => 'Producto Disponible',
            'price' => 5000.00,
            'stock' => 10,
            'is_active' => true,
        ]);

        Product::create([
            'name' => 'Producto Sin Existencias',
            'price' => 8000.00,
            'stock' => 0,
            'is_active' => true,
        ]);

        $response = $this->get('/showcase');

        $response->assertStatus(200);
        $response->assertSee('Añadir al carrito');
        $response->assertSee('Agotado');
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

    /**
     * Test client can filter showcase by minimum price.
     */
    public function test_product_showcase_can_filter_by_min_price(): void
    {
        $client = $this->createClientUser();

        Product::create([
            'name' => 'Caramelo Barato',
            'price' => 500.00,
            'stock' => 50,
            'is_active' => true,
        ]);

        Product::create([
            'name' => 'Vino Costoso',
            'price' => 45000.00,
            'stock' => 10,
            'is_active' => true,
        ]);

        $response = $this->actingAs($client)->get('/dashboard?min_price=1000');

        $response->assertStatus(200);
        $response->assertSee('Vino Costoso');
        $response->assertDontSee('Caramelo Barato');
    }

    /**
     * Test client can filter showcase by maximum price.
     */
    public function test_product_showcase_can_filter_by_max_price(): void
    {
        $client = $this->createClientUser();

        Product::create([
            'name' => 'Galletas Económicas',
            'price' => 1500.00,
            'stock' => 20,
            'is_active' => true,
        ]);

        Product::create([
            'name' => 'Whisky Importado',
            'price' => 90000.00,
            'stock' => 5,
            'is_active' => true,
        ]);

        $response = $this->actingAs($client)->get('/dashboard?max_price=5000');

        $response->assertStatus(200);
        $response->assertSee('Galletas Económicas');
        $response->assertDontSee('Whisky Importado');
    }

    /**
     * Test client can filter showcase by a combined price range.
     */
    public function test_product_showcase_can_filter_by_price_range(): void
    {
        $client = $this->createClientUser();

        Product::create(['name' => 'Producto Menor', 'price' => 1000.00, 'stock' => 5, 'is_active' => true]);
        Product::create(['name' => 'Producto Medio', 'price' => 5000.00, 'stock' => 5, 'is_active' => true]);
        Product::create(['name' => 'Producto Mayor', 'price' => 15000.00, 'stock' => 5, 'is_active' => true]);

        $response = $this->actingAs($client)->get('/dashboard?min_price=2000&max_price=8000');

        $response->assertStatus(200);
        $response->assertSee('Producto Medio');
        $response->assertDontSee('Producto Menor');
        $response->assertDontSee('Producto Mayor');
    }

    /**
     * Test client can filter showcase by stock availability.
     */
    public function test_product_showcase_can_filter_only_in_stock(): void
    {
        $client = $this->createClientUser();

        Product::create([
            'name' => 'Arroz con Stock',
            'price' => 3800.00,
            'stock' => 15,
            'is_active' => true,
        ]);

        Product::create([
            'name' => 'Aceite Agotado',
            'price' => 12000.00,
            'stock' => 0,
            'is_active' => true,
        ]);

        $response = $this->actingAs($client)->get('/dashboard?in_stock=1');

        $response->assertStatus(200);
        $response->assertSee('Arroz con Stock');
        $response->assertDontSee('Aceite Agotado');
    }

    /**
     * Test client can sort showcase by price ascending.
     */
    public function test_product_showcase_can_sort_by_price_ascending(): void
    {
        $client = $this->createClientUser();

        Product::create(['name' => 'Producto Caro', 'price' => 20000.00, 'stock' => 5, 'is_active' => true]);
        Product::create(['name' => 'Producto Barato', 'price' => 3000.00, 'stock' => 5, 'is_active' => true]);

        $response = $this->actingAs($client)->get('/dashboard?sort_by=price_asc');

        $response->assertStatus(200);
        $content = $response->getContent();
        $posBarato = strpos($content, 'Producto Barato');
        $posCaro = strpos($content, 'Producto Caro');

        $this->assertTrue($posBarato !== false && $posCaro !== false);
        $this->assertLessThan($posCaro, $posBarato);
    }

    /**
     * Test client can sort showcase by price descending.
     */
    public function test_product_showcase_can_sort_by_price_descending(): void
    {
        $client = $this->createClientUser();

        Product::create(['name' => 'Producto Barato', 'price' => 3000.00, 'stock' => 5, 'is_active' => true]);
        Product::create(['name' => 'Producto Caro', 'price' => 20000.00, 'stock' => 5, 'is_active' => true]);

        $response = $this->actingAs($client)->get('/dashboard?sort_by=price_desc');

        $response->assertStatus(200);
        $content = $response->getContent();
        $posBarato = strpos($content, 'Producto Barato');
        $posCaro = strpos($content, 'Producto Caro');

        $this->assertTrue($posBarato !== false && $posCaro !== false);
        $this->assertLessThan($posBarato, $posCaro);
    }

    /**
     * Test client can sort showcase by name descending (Z - A).
     */
    public function test_product_showcase_can_sort_by_name_descending(): void
    {
        $client = $this->createClientUser();

        Product::create(['name' => 'Avena Natural', 'price' => 2500.00, 'stock' => 10, 'is_active' => true]);
        Product::create(['name' => 'Zapallo Criollo', 'price' => 4000.00, 'stock' => 10, 'is_active' => true]);

        $response = $this->actingAs($client)->get('/dashboard?sort_by=name_desc');

        $response->assertStatus(200);
        $content = $response->getContent();
        $posAvena = strpos($content, 'Avena Natural');
        $posZapallo = strpos($content, 'Zapallo Criollo');

        $this->assertTrue($posAvena !== false && $posZapallo !== false);
        $this->assertLessThan($posAvena, $posZapallo);
    }

    /**
     * Test client can combine multiple custom search filters simultaneously.
     */
    public function test_product_showcase_can_combine_custom_search_filters(): void
    {
        $client = $this->createClientUser();

        $catBebidas = Category::create(['name' => 'Bebidas Frescas', 'is_active' => true]);
        $catPanaderia = Category::create(['name' => 'Panadería', 'is_active' => true]);

        // Target match:
        Product::create([
            'name' => 'Jugo Natural de Naranja',
            'description' => 'Delicioso jugo recién exprimido',
            'price' => 4000.00,
            'stock' => 10,
            'is_active' => true,
            'category_id' => $catBebidas->id,
        ]);

        // Wrong category:
        Product::create([
            'name' => 'Pan de Naranja',
            'description' => 'Pan dulce con sabor a naranja',
            'price' => 3500.00,
            'stock' => 10,
            'is_active' => true,
            'category_id' => $catPanaderia->id,
        ]);

        // Out of price range:
        Product::create([
            'name' => 'Jugo de Mora Concentrado',
            'description' => 'Jugo especial importado',
            'price' => 15000.00,
            'stock' => 10,
            'is_active' => true,
            'category_id' => $catBebidas->id,
        ]);

        // Out of stock:
        Product::create([
            'name' => 'Jugo de Maracuyá',
            'description' => 'Jugo tropical',
            'price' => 4000.00,
            'stock' => 0,
            'is_active' => true,
            'category_id' => $catBebidas->id,
        ]);

        $response = $this->actingAs($client)->get('/dashboard?' . http_build_query([
            'search' => 'Jugo',
            'category' => $catBebidas->id,
            'min_price' => 2000,
            'max_price' => 6000,
            'in_stock' => 1,
            'sort_by' => 'price_asc',
        ]));

        $response->assertStatus(200);
        $response->assertSee('Jugo Natural de Naranja');
        $response->assertDontSee('Pan de Naranja');
        $response->assertDontSee('Jugo de Mora Concentrado');
        $response->assertDontSee('Jugo de Maracuyá');
    }

    /**
     * Test pagination links preserve custom search query parameters.
     */
    public function test_product_showcase_pagination_preserves_custom_search_filters(): void
    {
        $client = $this->createClientUser();

        for ($i = 1; $i <= 15; $i++) {
            Product::create([
                'name' => sprintf('Producto Premium %02d', $i),
                'price' => 5000.00,
                'stock' => 10,
                'is_active' => true,
            ]);
        }

        $response = $this->actingAs($client)->get('/dashboard?' . http_build_query([
            'search' => 'Premium',
            'min_price' => 2000,
            'max_price' => 10000,
            'in_stock' => 1,
            'sort_by' => 'price_desc',
        ]));

        $response->assertStatus(200);
        $response->assertSee('search=Premium');
        $response->assertSee('min_price=2000');
        $response->assertSee('max_price=10000');
        $response->assertSee('in_stock=1');
        $response->assertSee('sort_by=price_desc');
    }

    /**
     * Test showcase displays active filter badges and reset button.
     */
    public function test_product_showcase_displays_active_filter_badges_and_reset_button(): void
    {
        $client = $this->createClientUser();

        $catLacteos = Category::create(['name' => 'Lácteos', 'is_active' => true]);

        Product::create([
            'name' => 'Yogurt Griego',
            'price' => 4500.00,
            'stock' => 8,
            'is_active' => true,
            'category_id' => $catLacteos->id,
        ]);

        $response = $this->actingAs($client)->get('/dashboard?' . http_build_query([
            'search' => 'Yogurt',
            'category' => $catLacteos->id,
            'min_price' => 2000,
            'max_price' => 6000,
            'in_stock' => 1,
            'sort_by' => 'price_asc',
        ]));

        $response->assertStatus(200);
        $response->assertSee('Filtros aplicados:');
        $response->assertSee('Yogurt');
        $response->assertSee('Lácteos');
        $response->assertSee('Solo disponibles');
        $response->assertSee('Limpiar');
    }
}
