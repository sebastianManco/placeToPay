<?php

namespace Tests\Unit;

use App\Models\Category;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Product;
use App\Models\User;
use App\Services\CartService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use InvalidArgumentException;
use Tests\TestCase;

class CartServiceTest extends TestCase
{
    use RefreshDatabase;

    private CartService $cartService;

    protected function setUp(): void
    {
        parent::setUp();
        $this->cartService = new CartService();
    }

    private function createProduct(array $attributes = []): Product
    {
        return Product::create(array_merge([
            'name' => 'Producto de Prueba',
            'description' => 'Descripción del producto de prueba',
            'price' => 10000.00,
            'stock' => 10,
            'is_active' => true,
        ], $attributes));
    }

    private function createUser(array $attributes = []): User
    {
        return User::create(array_merge([
            'identification' => 12345678,
            'name' => 'Comprador',
            'last_name' => 'Prueba',
            'email' => 'comprador@example.com',
            'phone' => '3001234567',
            'direction' => 'Calle 100 # 20 - 30',
            'user_name' => 'comprador1',
            'password' => 'secret1234',
            'email_verified_at' => now(),
            'is_active' => true,
        ], $attributes));
    }

    /**
     * Test retrieving or creating a cart for a session or user.
     */
    public function test_get_cart_creates_new_order_in_cart_status(): void
    {
        $user = $this->createUser();
        $cart = $this->cartService->getCart($user);

        $this->assertInstanceOf(Order::class, $cart);
        $this->assertEquals('in_cart', $cart->status);
        $this->assertEquals($user->identification, $cart->user_identification);
        $this->assertEquals(0.00, (float) $cart->total_amount);
    }

    /**
     * Test adding a product to an empty cart creates an order item and updates total.
     */
    public function test_add_product_to_cart_creates_order_item(): void
    {
        $user = $this->createUser();
        $product = $this->createProduct(['price' => 15000.00, 'stock' => 5]);
        $cart = $this->cartService->getCart($user);

        $item = $this->cartService->addItem($cart, $product, 2);

        $this->assertInstanceOf(OrderItem::class, $item);
        $this->assertEquals(2, $item->quantity);
        $this->assertEquals(15000.00, (float) $item->unit_price);
        $this->assertEquals(30000.00, (float) $item->subtotal);

        $cart->refresh();
        $this->assertEquals(30000.00, (float) $cart->total_amount);
        $this->assertCount(1, $cart->items);
    }

    /**
     * Test adding the same product again increases quantity and recalculates.
     */
    public function test_add_existing_product_increments_quantity(): void
    {
        $user = $this->createUser();
        $product = $this->createProduct(['price' => 5000.00, 'stock' => 10]);
        $cart = $this->cartService->getCart($user);

        $this->cartService->addItem($cart, $product, 2);
        $this->cartService->addItem($cart, $product, 3);

        $cart->refresh();
        $this->assertCount(1, $cart->items);
        $this->assertEquals(5, $cart->items->first()->quantity);
        $this->assertEquals(25000.00, (float) $cart->total_amount);
    }

    /**
     * Test adding product exceeding available stock throws exception.
     */
    public function test_add_product_exceeding_stock_throws_exception(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('No hay suficiente stock disponible para este producto.');

        $user = $this->createUser();
        $product = $this->createProduct(['stock' => 3]);
        $cart = $this->cartService->getCart($user);

        $this->cartService->addItem($cart, $product, 4);
    }

    /**
     * Test adding inactive product throws exception.
     */
    public function test_add_inactive_product_throws_exception(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('El producto no se encuentra disponible.');

        $user = $this->createUser();
        $product = $this->createProduct(['is_active' => false]);
        $cart = $this->cartService->getCart($user);

        $this->cartService->addItem($cart, $product, 1);
    }

    /**
     * Test updating item quantity updates subtotal and total.
     */
    public function test_update_item_quantity_updates_totals(): void
    {
        $user = $this->createUser();
        $product = $this->createProduct(['price' => 4000.00, 'stock' => 10]);
        $cart = $this->cartService->getCart($user);
        $item = $this->cartService->addItem($cart, $product, 2);

        $this->cartService->updateItemQuantity($cart, $item->id, 5);

        $cart->refresh();
        $this->assertEquals(20000.00, (float) $cart->total_amount);
        $this->assertEquals(5, $cart->items->first()->quantity);
    }

    /**
     * Test updating item quantity to zero removes the item.
     */
    public function test_update_item_quantity_to_zero_removes_item(): void
    {
        $user = $this->createUser();
        $product = $this->createProduct();
        $cart = $this->cartService->getCart($user);
        $item = $this->cartService->addItem($cart, $product, 2);

        $this->cartService->updateItemQuantity($cart, $item->id, 0);

        $cart->refresh();
        $this->assertCount(0, $cart->items);
        $this->assertEquals(0.00, (float) $cart->total_amount);
    }

    /**
     * Test removing item removes it from cart and recalculates total.
     */
    public function test_remove_item_from_cart(): void
    {
        $user = $this->createUser();
        $prod1 = $this->createProduct(['price' => 10000.00]);
        $prod2 = $this->createProduct(['name' => 'Prod 2', 'price' => 20000.00]);
        $cart = $this->cartService->getCart($user);

        $item1 = $this->cartService->addItem($cart, $prod1, 1);
        $this->cartService->addItem($cart, $prod2, 1);

        $this->cartService->removeItem($cart, $item1->id);

        $cart->refresh();
        $this->assertCount(1, $cart->items);
        $this->assertEquals(20000.00, (float) $cart->total_amount);
    }

    /**
     * Test clearing the cart removes all items.
     */
    public function test_clear_cart_removes_all_items(): void
    {
        $user = $this->createUser();
        $prod = $this->createProduct();
        $cart = $this->cartService->getCart($user);
        $this->cartService->addItem($cart, $prod, 2);

        $this->cartService->clearCart($cart);

        $cart->refresh();
        $this->assertCount(0, $cart->items);
        $this->assertEquals(0.00, (float) $cart->total_amount);
    }

    /**
     * Test confirming order transitions status to pending_payment.
     */
    public function test_confirm_order_transitions_to_pending_payment(): void
    {
        $user = $this->createUser();
        $product = $this->createProduct(['price' => 12000.00, 'stock' => 5]);
        $cart = $this->cartService->getCart($user);
        $this->cartService->addItem($cart, $product, 2);

        $order = $this->cartService->confirmOrder($cart, [
            'customer_name' => 'Comprador Prueba',
            'customer_email' => 'comprador@example.com',
            'customer_phone' => '3001234567',
            'customer_address' => 'Carrera 15 # 45 - 60',
        ]);

        $this->assertEquals('pending_payment', $order->status);
        $this->assertEquals(24000.00, (float) $order->total_amount);
        $this->assertNotEmpty($order->reference);
        $this->assertEquals('Comprador Prueba', $order->customer_name);
    }

    /**
     * Test confirming empty cart throws exception.
     */
    public function test_confirm_empty_cart_throws_exception(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('El carrito se encuentra vacío.');

        $user = $this->createUser();
        $cart = $this->cartService->getCart($user);

        $this->cartService->confirmOrder($cart, [
            'customer_name' => 'Comprador',
            'customer_email' => 'comprador@example.com',
        ]);
    }

    /**
     * Test confirming order without user identification throws exception.
     */
    public function test_confirm_order_without_user_identification_throws_exception(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Debe identificarse para confirmar la compra.');

        $product = $this->createProduct(['price' => 10000.00]);
        // Create cart as guest (sessionId only, no user)
        $cart = $this->cartService->getCart(null, 'guest-session-test-id');
        $this->cartService->addItem($cart, $product, 1);

        $this->cartService->confirmOrder($cart, [
            'customer_name' => 'Invitado',
            'customer_email' => 'invitado@example.com',
        ]);
    }

    /**
     * Test migrating guest cart assigns cart to user when user has no active cart.
     */
    public function test_migrate_guest_cart_assigns_cart_to_user_without_cart(): void
    {
        $user = $this->createUser();
        $product = $this->createProduct(['price' => 20000.00, 'stock' => 10]);

        $guestCart = $this->cartService->getCart(null, 'guest-session-111');
        $this->cartService->addItem($guestCart, $product, 2);

        $this->assertNull($guestCart->user_identification);

        $migrated = $this->cartService->migrateGuestCart($user, 'guest-session-111', 'new-session-222');

        $this->assertNotNull($migrated);
        $this->assertEquals($user->identification, $migrated->user_identification);
        $this->assertEquals('new-session-222', $migrated->session_id);
        $this->assertCount(1, $migrated->items);
        $this->assertEquals(40000.00, (float) $migrated->total_amount);
    }

    /**
     * Test migrating guest cart merges items when user already has an active cart.
     */
    public function test_migrate_guest_cart_merges_items_into_user_existing_cart(): void
    {
        $user = $this->createUser();
        $prodA = $this->createProduct(['name' => 'Prod A', 'price' => 10000.00, 'stock' => 10]);
        $prodB = $this->createProduct(['name' => 'Prod B', 'price' => 15000.00, 'stock' => 10]);

        // User's existing cart has Prod A x 1
        $userCart = $this->cartService->getCart($user);
        $this->cartService->addItem($userCart, $prodA, 1);

        // Guest cart has Prod A x 2 and Prod B x 1
        $guestCart = $this->cartService->getCart(null, 'guest-sess-abc');
        $this->cartService->addItem($guestCart, $prodA, 2);
        $this->cartService->addItem($guestCart, $prodB, 1);

        $result = $this->cartService->migrateGuestCart($user, 'guest-sess-abc', 'new-sess-xyz');

        $this->assertNotNull($result);
        $this->assertEquals($user->identification, $result->user_identification);
        $this->assertEquals(2, $result->items()->count());

        $itemA = $result->items()->where('product_id', $prodA->id)->first();
        $this->assertNotNull($itemA);
        $this->assertEquals(3, $itemA->quantity); // 1 + 2

        $itemB = $result->items()->where('product_id', $prodB->id)->first();
        $this->assertNotNull($itemB);
        $this->assertEquals(1, $itemB->quantity);

        // Guest order record should be deleted
        $this->assertDatabaseMissing('orders', ['id' => $guestCart->id]);
    }
}
