<?php

namespace Tests\Feature;

use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Product;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AdminOrderManagementTest extends TestCase
{
    use RefreshDatabase;

    protected function createAdmin(): User
    {
        $user = User::create([
            'identification' => 70000001,
            'name' => 'Admin',
            'last_name' => 'Supervisor',
            'email' => 'admin_orders_test@example.com',
            'phone' => '123456789',
            'direction' => 'Calle Principal 1',
            'user_name' => 'adminsuper',
            'password' => 'secret123',
            'email_verified_at' => now(),
            'is_active' => true,
        ]);
        $user->syncRoles(['admin']);

        return $user;
    }

    protected function createClient(array $attributes = []): User
    {
        static $counter = 1;
        $id = 71000000 + ($counter++);

        return User::create(array_merge([
            'identification' => $id,
            'name' => "Cliente{$counter}",
            'last_name' => 'Apellido',
            'email' => "cliente{$counter}@admintest.com",
            'phone' => '3007654321',
            'direction' => 'Carrera 7 # 100 - 20',
            'user_name' => "clientadm{$counter}",
            'password' => 'secret123',
            'email_verified_at' => now(),
            'is_active' => true,
            'role' => 'client',
        ], $attributes));
    }

    protected function createProduct(array $attributes = []): Product
    {
        return Product::create(array_merge([
            'name' => 'Leche Entera 1L',
            'description' => 'Leche pasteurizada fresca',
            'price' => 3800.00,
            'stock' => 50,
            'is_active' => true,
        ], $attributes));
    }

    protected function createOrder(User $user, array $attributes = []): Order
    {
        return Order::create(array_merge([
            'reference' => Order::generateUniqueReference(),
            'user_identification' => $user->identification,
            'status' => Order::STATUS_PENDING_PAYMENT,
            'total_amount' => 19000.00,
            'currency' => 'COP',
            'customer_name' => $user->name . ' ' . $user->last_name,
            'customer_email' => $user->email,
            'customer_phone' => $user->phone,
            'customer_address' => $user->direction,
        ], $attributes));
    }

    public function test_admin_can_view_orders_index_with_statistics(): void
    {
        $admin = $this->createAdmin();
        $client = $this->createClient();

        $this->createOrder($client, ['status' => Order::STATUS_PENDING_PAYMENT]);
        $this->createOrder($client, ['status' => Order::STATUS_APPROVED]);

        $response = $this->actingAs($admin)->get(route('admin.orders.index'));

        $response->assertStatus(200);
        $response->assertSee('Panel de Gestión de Pedidos');
        $response->assertSee('Total Órdenes');
        $response->assertSee('Aprobadas');
        $response->assertSee('Pendientes');
    }

    public function test_admin_can_filter_orders_by_client(): void
    {
        $admin = $this->createAdmin();
        $clientTarget = $this->createClient(['name' => 'Carlos', 'last_name' => 'Gomez', 'email' => 'carlos@example.com']);
        $clientOther = $this->createClient(['name' => 'Maria', 'last_name' => 'Lopez', 'email' => 'maria@example.com']);

        $orderTarget = $this->createOrder($clientTarget, ['reference' => 'ORD-CARLOS-99']);
        $orderOther = $this->createOrder($clientOther, ['reference' => 'ORD-MARIA-88']);

        $response = $this->actingAs($admin)->get(route('admin.orders.index', ['client' => 'Carlos']));

        $response->assertStatus(200);
        $response->assertSee('ORD-CARLOS-99');
        $response->assertDontSee('ORD-MARIA-88');
    }

    public function test_admin_can_filter_orders_by_status(): void
    {
        $admin = $this->createAdmin();
        $client = $this->createClient();

        $approved = $this->createOrder($client, [
            'reference' => 'ORD-APPR-100',
            'status' => Order::STATUS_APPROVED,
        ]);
        $rejected = $this->createOrder($client, [
            'reference' => 'ORD-REJ-200',
            'status' => Order::STATUS_REJECTED,
        ]);

        $response = $this->actingAs($admin)->get(route('admin.orders.index', ['status' => Order::STATUS_APPROVED]));

        $response->assertStatus(200);
        $response->assertSee('ORD-APPR-100');
        $response->assertDontSee('ORD-REJ-200');
    }

    public function test_admin_can_filter_orders_by_date_range(): void
    {
        $admin = $this->createAdmin();
        $client = $this->createClient();

        $orderPast = $this->createOrder($client, [
            'reference' => 'ORD-PAST-10',
        ]);
        $orderPast->created_at = now()->subDays(15);
        $orderPast->saveQuietly();

        $orderRecent = $this->createOrder($client, [
            'reference' => 'ORD-RECENT-20',
        ]);

        $response = $this->actingAs($admin)->get(route('admin.orders.index', [
            'date_from' => now()->subDays(5)->format('Y-m-d'),
            'date_to' => now()->addDay()->format('Y-m-d'),
        ]));

        $response->assertStatus(200);
        $response->assertSee('ORD-RECENT-20');
        $response->assertDontSee('ORD-PAST-10');
    }

    public function test_admin_can_view_order_show_page_with_items_and_details(): void
    {
        $admin = $this->createAdmin();
        $client = $this->createClient();
        $product = $this->createProduct();

        $order = $this->createOrder($client, ['total_amount' => 19000.00]);
        $order->items()->create([
            'product_id' => $product->id,
            'product_name' => $product->name,
            'unit_price' => 3800.00,
            'quantity' => 5,
            'subtotal' => 19000.00,
        ]);

        $response = $this->actingAs($admin)->get(route('admin.orders.show', $order->id));

        $response->assertStatus(200);
        $response->assertSee($order->reference);
        $response->assertSee('Leche Entera 1L');
        $response->assertSee('$19.000,00');
        $response->assertSee('Actualizar Estado del Pedido');
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
        $response->assertSessionHas('success');
        $this->assertEquals(Order::STATUS_APPROVED, $order->fresh()->status);
    }

    public function test_admin_cannot_update_order_with_invalid_status(): void
    {
        $admin = $this->createAdmin();
        $client = $this->createClient();
        $order = $this->createOrder($client);

        $response = $this->actingAs($admin)->patch(route('admin.orders.update-status', $order->id), [
            'status' => 'non_existent_status',
        ]);

        $response->assertSessionHasErrors('status');
    }
}
