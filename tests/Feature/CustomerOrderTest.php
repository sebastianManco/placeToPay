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
}
