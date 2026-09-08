<?php

namespace Tests\Feature;

use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Product;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CustomerOrderTest extends TestCase
{
    use RefreshDatabase;

    protected function createClient(array $attributes = []): User
    {
        static $counter = 1;
        $id = 61000000 + ($counter++);

        return User::create(array_merge([
            'identification' => $id,
            'name' => "Cliente{$counter}",
            'last_Name' => 'Prueba',
            'email' => "cliente{$counter}@test.com",
            'phone' => '3001234567',
            'direction' => 'Carrera 50 # 20 - 10',
            'user_Name' => "cliuser{$counter}",
            'password' => 'secret123',
            'email_verified_at' => now(),
            'is_active' => true,
            'role' => 'client',
        ], $attributes));
    }

    protected function createProduct(array $attributes = []): Product
    {
        return Product::create(array_merge([
            'name' => 'Aceite Vegetal 1L',
            'description' => 'Aceite de cocina de alta calidad',
            'price' => 12000.00,
            'stock' => 20,
            'is_active' => true,
        ], $attributes));
    }

    protected function createOrder(User $user, array $attributes = []): Order
    {
        return Order::create(array_merge([
            'reference' => Order::generateUniqueReference(),
            'user_identification' => $user->identification,
            'status' => Order::STATUS_PENDING_PAYMENT,
            'total_amount' => 24000.00,
            'currency' => 'COP',
            'customer_name' => $user->name . ' ' . $user->last_Name,
            'customer_email' => $user->email,
            'customer_phone' => $user->phone,
            'customer_address' => $user->direction,
        ], $attributes));
    }

    public function test_client_can_view_orders_history_with_only_their_orders(): void
    {
        $clientA = $this->createClient();
        $clientB = $this->createClient();

        $orderA = $this->createOrder($clientA, ['reference' => 'ORD-CLI-AAA-01']);
        $orderB = $this->createOrder($clientB, ['reference' => 'ORD-CLI-BBB-02']);

        $response = $this->actingAs($clientA)->get(route('orders.index'));

        $response->assertStatus(200);
        $response->assertSee('ORD-CLI-AAA-01');
        $response->assertDontSee('ORD-CLI-BBB-02');
    }

    public function test_my_orders_alias_redirects_to_orders(): void
    {
        $client = $this->createClient();

        $response = $this->actingAs($client)->get('/my-orders');

        $response->assertRedirect(route('orders.index'));
    }

    public function test_client_can_filter_orders_by_status(): void
    {
        $client = $this->createClient();

        $pendingOrder = $this->createOrder($client, [
            'reference' => 'ORD-PENDING-1',
            'status' => Order::STATUS_PENDING_PAYMENT,
        ]);

        $approvedOrder = $this->createOrder($client, [
            'reference' => 'ORD-APPROVED-1',
            'status' => Order::STATUS_APPROVED,
        ]);

        $response = $this->actingAs($client)->get(route('orders.index', ['status' => Order::STATUS_APPROVED]));

        $response->assertStatus(200);
        $response->assertSee('ORD-APPROVED-1');
        $response->assertDontSee('ORD-PENDING-1');
    }

    public function test_client_can_filter_orders_by_date_range(): void
    {
        $client = $this->createClient();

        $orderOld = $this->createOrder($client, [
            'reference' => 'ORD-OLD-001',
        ]);
        $orderOld->created_at = now()->subDays(10);
        $orderOld->saveQuietly();

        $orderRecent = $this->createOrder($client, [
            'reference' => 'ORD-RECENT-002',
        ]);

        $response = $this->actingAs($client)->get(route('orders.index', [
            'date_from' => now()->subDays(2)->format('Y-m-d'),
            'date_to' => now()->addDay()->format('Y-m-d'),
        ]));

        $response->assertStatus(200);
        $response->assertSee('ORD-RECENT-002');
        $response->assertDontSee('ORD-OLD-001');
    }

    public function test_client_can_search_orders_by_reference(): void
    {
        $client = $this->createClient();

        $this->createOrder($client, ['reference' => 'ORD-ALPHA-777']);
        $this->createOrder($client, ['reference' => 'ORD-BETA-888']);

        $response = $this->actingAs($client)->get(route('orders.index', ['search' => 'ALPHA']));

        $response->assertStatus(200);
        $response->assertSee('ORD-ALPHA-777');
        $response->assertDontSee('ORD-BETA-888');
    }

    public function test_client_can_modify_item_quantity_in_checkout_before_confirmation(): void
    {
        $client = $this->createClient();
        $product = $this->createProduct(['price' => 10000.00, 'stock' => 10]);

        $this->actingAs($client)->post(route('cart.items.store'), [
            'product_id' => $product->id,
            'quantity' => 2,
        ]);

        $cart = Order::where('user_identification', $client->identification)
            ->where('status', Order::STATUS_IN_CART)
            ->first();
        $item = $cart->items()->first();

        // Client modifies quantity directly with redirect_to=checkout
        $response = $this->actingAs($client)->put(route('cart.items.update', $item->id), [
            'quantity' => 4,
            'redirect_to' => 'checkout',
        ]);

        $response->assertRedirect(route('cart.checkout'));
        $response->assertSessionHas('success');

        $this->assertEquals(4, $item->fresh()->quantity);
        $this->assertEquals(40000.00, (float) $cart->fresh()->total_amount);
    }

    public function test_client_can_remove_item_in_checkout_before_confirmation(): void
    {
        $client = $this->createClient();
        $productA = $this->createProduct(['name' => 'Prod A', 'price' => 10000.00]);
        $productB = $this->createProduct(['name' => 'Prod B', 'price' => 15000.00]);

        $this->actingAs($client)->post(route('cart.items.store'), ['product_id' => $productA->id, 'quantity' => 1]);
        $this->actingAs($client)->post(route('cart.items.store'), ['product_id' => $productB->id, 'quantity' => 1]);

        $cart = Order::where('user_identification', $client->identification)
            ->where('status', Order::STATUS_IN_CART)
            ->first();
        $itemA = $cart->items()->where('product_id', $productA->id)->first();

        $response = $this->actingAs($client)->delete(route('cart.items.destroy', $itemA->id), [
            'redirect_to' => 'checkout',
        ]);

        $response->assertRedirect(route('cart.checkout'));
        $response->assertSessionHas('success');

        $this->assertEquals(1, $cart->fresh()->items()->count());
        $this->assertEquals(15000.00, (float) $cart->fresh()->total_amount);
    }

    /**
     * Test that the purchase history view displays the retry payment button for rejected orders.
     *
     * @return void
     */
    public function test_client_purchase_history_shows_retry_payment_button_for_rejected_order(): void
    {
        $client = $this->createClient();
        $order = $this->createOrder($client, [
            'reference' => 'ORD-REJ-001',
            'status' => Order::STATUS_REJECTED,
        ]);

        $response = $this->actingAs($client)->get(route('orders.index'));

        $response->assertStatus(200);
        $response->assertSee('ORD-REJ-001');
        $response->assertSee('Rechazado');
        $response->assertSee('Reintentar Pago');
    }

    /**
     * Test that the purchase history view displays the pay button for pending payment orders.
     *
     * @return void
     */
    public function test_client_purchase_history_shows_pay_button_for_pending_order(): void
    {
        $client = $this->createClient();
        $order = $this->createOrder($client, [
            'reference' => 'ORD-PEND-002',
            'status' => Order::STATUS_PENDING_PAYMENT,
        ]);

        $response = $this->actingAs($client)->get(route('orders.index'));

        $response->assertStatus(200);
        $response->assertSee('ORD-PEND-002');
        $response->assertSee('Pendiente de Pago');
        $response->assertSee('Pagar');
    }

    /**
     * Test that the purchase history view does not display retry buttons for approved orders.
     *
     * @return void
     */
    public function test_client_purchase_history_does_not_show_retry_payment_button_for_approved_order(): void
    {
        $client = $this->createClient();
        $order = $this->createOrder($client, [
            'reference' => 'ORD-APP-003',
            'status' => Order::STATUS_APPROVED,
        ]);

        $response = $this->actingAs($client)->get(route('orders.index'));

        $response->assertStatus(200);
        $response->assertSee('ORD-APP-003');
        $response->assertSee('Aprobado');
        $response->assertDontSee('Reintentar Pago');
    }

    /**
     * Test that a client can retry payment for a rejected order and is redirected to PlaceToPay.
     *
     * @return void
     */
    public function test_client_can_retry_payment_for_rejected_order_and_is_redirected_to_gateway(): void
    {
        \Illuminate\Support\Facades\Http::fake([
            'checkout-test.placetopay.com/api/session' => \Illuminate\Support\Facades\Http::response([
                'status' => [
                    'status' => 'OK',
                    'reason' => 'PC',
                    'message' => 'Sesión creada exitosamente',
                    'date' => now()->toIso8601String(),
                ],
                'requestId' => 8888,
                'processUrl' => 'https://checkout-test.placetopay.com/session/8888/token',
            ], 200),
        ]);

        $client = $this->createClient();
        $order = $this->createOrder($client, [
            'reference' => 'ORD-RETRY-004',
            'status' => Order::STATUS_REJECTED,
            'request_id' => 'old-session-1234',
        ]);

        $response = $this->actingAs($client)->post(route('orders.retry-payment', $order->id));

        $response->assertRedirect('https://checkout-test.placetopay.com/session/8888/token');

        $order->refresh();
        $this->assertEquals(Order::STATUS_PENDING_PAYMENT, $order->status);
        $this->assertEquals('8888', $order->request_id);
    }

    /**
     * Test that a client cannot retry payment for another client's order.
     *
     * @return void
     */
    public function test_client_cannot_retry_payment_for_another_clients_order(): void
    {
        $clientA = $this->createClient();
        $clientB = $this->createClient();

        $orderOfB = $this->createOrder($clientB, [
            'status' => Order::STATUS_REJECTED,
        ]);

        $response = $this->actingAs($clientA)->post(route('payment.pay', $orderOfB->id));

        $response->assertStatus(403);
    }

    /**
     * Test that an already approved order cannot be repaid or retried.
     *
     * @return void
     */
    public function test_client_cannot_retry_payment_for_already_approved_order(): void
    {
        $client = $this->createClient();
        $order = $this->createOrder($client, [
            'status' => Order::STATUS_APPROVED,
        ]);

        $response = $this->actingAs($client)->post(route('payment.pay', $order->id));

        $response->assertRedirect(route('orders.show', $order->id));
        $response->assertSessionHas('success', 'Esta orden ya se encuentra pagada y aprobada.');
    }

    /**
     * Test that guests cannot retry payment and are redirected to login.
     *
     * @return void
     */
    public function test_guest_cannot_retry_payment(): void
    {
        $client = $this->createClient();
        $order = $this->createOrder($client, [
            'status' => Order::STATUS_REJECTED,
        ]);

        $response = $this->post(route('payment.pay', $order->id));

        $response->assertRedirect(route('login'));
    }

    /**
     * Test that order detail displays rejection alert and retry button when order is rejected.
     *
     * @return void
     */
    public function test_order_detail_view_displays_retry_button_and_rejection_notice_when_rejected(): void
    {
        $client = $this->createClient();
        $order = $this->createOrder($client, [
            'reference' => 'ORD-FAIL-005',
            'status' => Order::STATUS_REJECTED,
        ]);

        $response = $this->actingAs($client)->get(route('orders.show', $order->id));

        $response->assertStatus(200);
        $response->assertSee('ORD-FAIL-005');
        $response->assertSee('Rechazada');
        $response->assertSee('Reintentar Pago con PlaceToPay');
    }
}

