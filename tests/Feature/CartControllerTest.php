<?php

namespace Tests\Feature;

use App\Models\Category;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Product;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CartControllerTest extends TestCase
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

    private function createProduct(array $attributes = []): Product
    {
        return Product::create(array_merge([
            'name' => 'Arroz Diana 1kg',
            'description' => 'Arroz blanco seleccionado',
            'price' => 4500.00,
            'stock' => 10,
            'is_active' => true,
        ], $attributes));
    }

    /**
     * Test viewing the empty cart page.
     */
    public function test_user_can_view_empty_cart(): void
    {
        $response = $this->get(route('cart.index'));

        $response->assertStatus(200);
        $response->assertSee('Carrito de Compras');
        $response->assertSee('Tu carrito está vacío');
    }

    /**
     * Test adding a product to the cart redirects with success message.
     */
    public function test_user_can_add_product_to_cart(): void
    {
        $product = $this->createProduct(['price' => 5000.00, 'stock' => 8]);

        $response = $this->post(route('cart.items.store'), [
            'product_id' => $product->id,
            'quantity' => 2,
        ]);

        $response->assertRedirect();
        $response->assertSessionHas('success', 'Producto agregado al carrito.');

        $this->assertDatabaseHas('order_items', [
            'product_id' => $product->id,
            'quantity' => 2,
            'unit_price' => 5000.00,
            'subtotal' => 10000.00,
        ]);
    }

    /**
     * Test adding out of stock product shows validation or business error.
     */
    public function test_cannot_add_product_exceeding_stock(): void
    {
        $product = $this->createProduct(['stock' => 2]);

        $response = $this->post(route('cart.items.store'), [
            'product_id' => $product->id,
            'quantity' => 5,
        ]);

        $response->assertRedirect();
        $response->assertSessionHas('error');
    }

    /**
     * Test viewing cart with items displays correct subtotal and total.
     */
    public function test_user_can_view_cart_with_items(): void
    {
        $client = $this->createClientUser();
        $product = $this->createProduct(['name' => 'Aceite Vegetal 900ml', 'price' => 8500.00]);

        $this->actingAs($client)->post(route('cart.items.store'), [
            'product_id' => $product->id,
            'quantity' => 2,
        ]);

        $response = $this->actingAs($client)->get(route('cart.index'));

        $response->assertStatus(200);
        $response->assertSee('Aceite Vegetal 900ml');
        $response->assertSee('$8.500,00');
        $response->assertSee('$17.000,00');
        $response->assertSee('Confirmar Pedido');
    }

    /**
     * Test modifying item quantity in cart.
     */
    public function test_user_can_update_item_quantity_in_cart(): void
    {
        $client = $this->createClientUser();
        $product = $this->createProduct(['price' => 3000.00, 'stock' => 15]);

        $this->actingAs($client)->post(route('cart.items.store'), [
            'product_id' => $product->id,
            'quantity' => 1,
        ]);

        $item = OrderItem::first();

        $response = $this->actingAs($client)->put(route('cart.items.update', $item->id), [
            'quantity' => 4,
        ]);

        $response->assertRedirect(route('cart.index'));
        $response->assertSessionHas('success', 'Cantidad actualizada correctamente.');

        $this->assertDatabaseHas('order_items', [
            'id' => $item->id,
            'quantity' => 4,
            'subtotal' => 12000.00,
        ]);
    }

    /**
     * Test removing an item from the cart.
     */
    public function test_user_can_remove_item_from_cart(): void
    {
        $client = $this->createClientUser();
        $product = $this->createProduct();

        $this->actingAs($client)->post(route('cart.items.store'), [
            'product_id' => $product->id,
            'quantity' => 1,
        ]);

        $item = OrderItem::first();

        $response = $this->actingAs($client)->delete(route('cart.items.destroy', $item->id));

        $response->assertRedirect(route('cart.index'));
        $response->assertSessionHas('success', 'Producto eliminado del carrito.');

        $this->assertDatabaseMissing('order_items', [
            'id' => $item->id,
        ]);
    }

    /**
     * Test clearing the cart.
     */
    public function test_user_can_clear_cart(): void
    {
        $client = $this->createClientUser();
        $product = $this->createProduct();

        $this->actingAs($client)->post(route('cart.items.store'), [
            'product_id' => $product->id,
            'quantity' => 2,
        ]);

        $response = $this->actingAs($client)->delete(route('cart.clear'));

        $response->assertRedirect(route('cart.index'));
        $response->assertSessionHas('success', 'Carrito vaciado exitosamente.');

        $this->assertEquals(0, OrderItem::count());
    }

    /**
     * Test viewing checkout screen with order review.
     */
    public function test_user_can_view_checkout_order_review(): void
    {
        $client = $this->createClientUser();
        $product = $this->createProduct(['name' => 'Café Sello Rojo 500g', 'price' => 14000.00]);

        $this->actingAs($client)->post(route('cart.items.store'), [
            'product_id' => $product->id,
            'quantity' => 1,
        ]);

        $response = $this->actingAs($client)->get(route('cart.checkout'));

        $response->assertStatus(200);
        $response->assertSee('Resumen y Confirmación de la Orden');
        $response->assertSee('Café Sello Rojo 500g');
        $response->assertSee('$14.000,00');
        $response->assertSee('Confirmar y Proceder al Pago');
    }

    /**
     * Test confirming the order changes status to pending_payment.
     */
    public function test_user_can_confirm_order_and_proceed(): void
    {
        $client = $this->createClientUser();
        $product = $this->createProduct(['price' => 10000.00, 'stock' => 5]);

        $this->actingAs($client)->post(route('cart.items.store'), [
            'product_id' => $product->id,
            'quantity' => 2,
        ]);

        $response = $this->actingAs($client)->post(route('cart.checkout.confirm'), [
            'customer_name' => 'Cliente Confirmado',
            'customer_email' => 'confirmado@example.com',
            'customer_phone' => '3119876543',
            'customer_address' => 'Avenida Siempre Viva 123',
        ]);

        $order = Order::where('status', 'pending_payment')->first();
        $this->assertNotNull($order);
        $this->assertEquals(20000.00, (float) $order->total_amount);
        $this->assertEquals('Cliente Confirmado', $order->customer_name);

        $response->assertRedirect(route('orders.show', $order->id));
        $response->assertSessionHas('success');
    }
}
