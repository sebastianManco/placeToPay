<?php

namespace Tests\Feature;

use App\Models\Order;
use App\Models\Product;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class OrderAccessPolicyTest extends TestCase
{
    use RefreshDatabase;

    protected function createAdmin(array $attributes = []): User
    {
        return User::create(array_merge([
            'identification' => 50000001,
            'name' => 'Admin',
            'last_Name' => 'User',
            'email' => 'admin_orders@example.com',
            'phone' => '123456789',
            'direction' => 'HQ Street',
            'user_Name' => 'adminorders',
            'password' => 'secret123',
            'email_verified_at' => now(),
            'is_active' => true,
            'role' => 'admin',
        ], $attributes));
    }

    protected function createClient(array $attributes = []): User
    {
        static $counter = 1;
        $id = 51000000 + ($counter++);

        return User::create(array_merge([
            'identification' => $id,
            'name' => "Client{$counter}",
            'last_Name' => 'Tester',
            'email' => "client{$counter}_orders@example.com",
            'phone' => '3001112233',
            'direction' => 'Avenue 45 # 10',
            'user_Name' => "clientord{$counter}",
            'password' => 'secret123',
            'email_verified_at' => now(),
            'is_active' => true,
            'role' => 'client',
        ], $attributes));
    }

    protected function createOrder(User $user, array $attributes = []): Order
    {
        return Order::create(array_merge([
            'reference' => Order::generateUniqueReference(),
            'user_identification' => $user->identification,
            'status' => Order::STATUS_PENDING_PAYMENT,
            'total_amount' => 50000.00,
            'currency' => 'COP',
            'customer_name' => $user->name . ' ' . $user->last_Name,
            'customer_email' => $user->email,
            'customer_phone' => $user->phone,
            'customer_address' => $user->direction,
        ], $attributes));
    }

    public function test_guests_cannot_access_customer_orders_history(): void
    {
        $response = $this->get(route('orders.index'));

        $response->assertRedirect(route('login'));
    }

    public function test_guests_cannot_access_order_detail(): void
    {
        $client = $this->createClient();
        $order = $this->createOrder($client);

        $response = $this->get(route('orders.show', $order->id));

        $response->assertRedirect(route('login'));
    }

    public function test_guests_cannot_access_admin_orders_panel(): void
    {
        $response = $this->get(route('admin.orders.index'));

        $response->assertRedirect(route('login'));
    }

    public function test_client_cannot_access_admin_orders_panel(): void
    {
        $client = $this->createClient();

        $response = $this->actingAs($client)->get(route('admin.orders.index'));

        $response->assertStatus(403);
    }

    public function test_client_cannot_access_admin_order_detail(): void
    {
        $client = $this->createClient();
        $order = $this->createOrder($client);

        $response = $this->actingAs($client)->get(route('admin.orders.show', $order->id));

        $response->assertStatus(403);
    }

    public function test_client_can_view_own_order_detail(): void
    {
        $client = $this->createClient();
        $order = $this->createOrder($client);

        $response = $this->actingAs($client)->get(route('orders.show', $order->id));

        $response->assertStatus(200);
        $response->assertSee($order->reference);
    }

    public function test_client_cannot_view_other_clients_order_detail(): void
    {
        $clientA = $this->createClient();
        $clientB = $this->createClient();
        $orderOfB = $this->createOrder($clientB);

        $response = $this->actingAs($clientA)->get(route('orders.show', $orderOfB->id));

        $response->assertStatus(403);
    }

    public function test_client_cannot_update_order_status(): void
    {
        $client = $this->createClient();
        $order = $this->createOrder($client);

        $response = $this->actingAs($client)->patch(route('admin.orders.update-status', $order->id), [
            'status' => Order::STATUS_APPROVED,
        ]);

        $response->assertStatus(403);
        $this->assertEquals(Order::STATUS_PENDING_PAYMENT, $order->fresh()->status);
    }

    public function test_admin_can_access_admin_orders_panel(): void
    {
        $admin = $this->createAdmin();

        $response = $this->actingAs($admin)->get(route('admin.orders.index'));

        $response->assertStatus(200);
        $response->assertSee('Panel de Gestión de Pedidos');
    }

    public function test_admin_can_view_any_order_detail_in_admin_panel(): void
    {
        $admin = $this->createAdmin();
        $client = $this->createClient();
        $order = $this->createOrder($client);

        $response = $this->actingAs($admin)->get(route('admin.orders.show', $order->id));

        $response->assertStatus(200);
        $response->assertSee($order->reference);
        $response->assertSee($client->email);
    }

    public function test_admin_can_view_any_order_detail_in_customer_route(): void
    {
        $admin = $this->createAdmin();
        $client = $this->createClient();
        $order = $this->createOrder($client);

        $response = $this->actingAs($admin)->get(route('orders.show', $order->id));

        $response->assertStatus(200);
        $response->assertSee($order->reference);
    }

    public function test_admin_can_update_order_status(): void
    {
        $admin = $this->createAdmin();
        $client = $this->createClient();
        $order = $this->createOrder($client, ['status' => Order::STATUS_PENDING_PAYMENT]);

        $response = $this->actingAs($admin)->patch(route('admin.orders.update-status', $order->id), [
            'status' => Order::STATUS_APPROVED,
        ]);

        $response->assertRedirect();
        $this->assertEquals(Order::STATUS_APPROVED, $order->fresh()->status);
    }
}
