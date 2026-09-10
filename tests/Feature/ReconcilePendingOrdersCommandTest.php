<?php

namespace Tests\Feature;

use App\Models\Category;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Product;
use App\Models\User;
use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class ReconcilePendingOrdersCommandTest extends TestCase
{
    use RefreshDatabase;

    protected function createClient(array $attributes = []): User
    {
        static $counter = 1;
        $id = 81000000 + ($counter++);

        return User::create(array_merge([
            'identification' => $id,
            'name' => "Cliente{$counter}",
            'last_name' => 'Reconciliacion',
            'email' => "cliente_reconcile_{$counter}@example.com",
            'phone' => '3009876543',
            'direction' => 'Carrera 50 # 10 - 20',
            'user_name' => "cliente_rec_{$counter}",
            'password' => 'password123',
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
            'request_id' => 'REQ-' . uniqid(),
            'total_amount' => 100000.00,
            'currency' => 'COP',
            'customer_name' => "{$user->name} {$user->last_name}",
            'customer_email' => $user->email,
            'customer_phone' => $user->phone,
            'customer_address' => $user->direction,
        ], $attributes));
    }

    public function test_reconciles_pending_order_older_than_15_minutes_to_approved_and_deducts_stock(): void
    {
        $category = Category::create(['name' => 'Tecnología', 'description' => 'Dispositivos']);
        $product = Product::create([
            'name' => 'Laptop Gamer',
            'description' => 'Alta potencia',
            'price' => 100000.00,
            'stock' => 5,
            'is_active' => true,
            'category_id' => $category->id,
        ]);

        $client = $this->createClient();
        $order = $this->createOrder($client, [
            'request_id' => '10001',
            'status' => Order::STATUS_PENDING_PAYMENT,
        ]);

        OrderItem::create([
            'order_id' => $order->id,
            'product_id' => $product->id,
            'product_name' => $product->name,
            'unit_price' => $product->price,
            'quantity' => 2,
            'subtotal' => 200000.00,
        ]);

        // Mark updated_at as 20 minutes ago
        Order::where('id', $order->id)->update([
            'updated_at' => Carbon::now()->subMinutes(20),
        ]);

        Http::fake([
            'checkout-test.placetopay.com/api/session/10001' => Http::response([
                'requestId' => 10001,
                'status' => [
                    'status' => 'APPROVED',
                    'reason' => '00',
                    'message' => 'Aprobada',
                    'date' => now()->toIso8601String(),
                ],
            ], 200),
        ]);

        $this->artisan('orders:reconcile-pending')
            ->expectsOutputToContain('Iniciando reconciliación de órdenes pendientes')
            ->assertExitCode(0);

        $order->refresh();
        $this->assertEquals(Order::STATUS_APPROVED, $order->status);
        $this->assertTrue($order->isApproved());

        $product->refresh();
        $this->assertEquals(3, $product->stock); // 5 - 2 = 3
    }

    public function test_reconciles_pending_order_older_than_15_minutes_to_rejected(): void
    {
        $client = $this->createClient();
        $order = $this->createOrder($client, [
            'request_id' => '20002',
            'status' => Order::STATUS_PENDING_PAYMENT,
        ]);

        Order::where('id', $order->id)->update([
            'updated_at' => Carbon::now()->subMinutes(30),
        ]);

        Http::fake([
            'checkout-test.placetopay.com/api/session/20002' => Http::response([
                'requestId' => 20002,
                'status' => [
                    'status' => 'REJECTED',
                    'reason' => '?R',
                    'message' => 'Transacción rechazada',
                    'date' => now()->toIso8601String(),
                ],
            ], 200),
        ]);

        $this->artisan('orders:reconcile-pending')
            ->assertExitCode(0);

        $order->refresh();
        $this->assertEquals(Order::STATUS_REJECTED, $order->status);
        $this->assertTrue($order->isRejected());
    }

    public function test_reconciles_order_to_rejected_on_cancelled_or_expired_status(): void
    {
        $client = $this->createClient();
        $order = $this->createOrder($client, [
            'request_id' => '30003',
            'status' => Order::STATUS_PENDING_PAYMENT,
        ]);

        Order::where('id', $order->id)->update([
            'updated_at' => Carbon::now()->subMinutes(25),
        ]);

        Http::fake([
            'checkout-test.placetopay.com/api/session/30003' => Http::response([
                'requestId' => 30003,
                'status' => [
                    'status' => 'EXPIRED',
                    'reason' => 'EX',
                    'message' => 'Sesión expirada',
                    'date' => now()->toIso8601String(),
                ],
            ], 200),
        ]);

        $this->artisan('orders:reconcile-pending')
            ->assertExitCode(0);

        $order->refresh();
        $this->assertEquals(Order::STATUS_REJECTED, $order->status);
    }

    public function test_does_not_reconcile_orders_updated_within_15_minutes(): void
    {
        $client = $this->createClient();
        $recentOrder = $this->createOrder($client, [
            'request_id' => '40004',
            'status' => Order::STATUS_PENDING_PAYMENT,
        ]);

        // Updated only 5 minutes ago
        Order::where('id', $recentOrder->id)->update([
            'updated_at' => Carbon::now()->subMinutes(5),
        ]);

        Http::fake();

        $this->artisan('orders:reconcile-pending')
            ->expectsOutputToContain('No se encontraron órdenes pendientes para reconciliar.')
            ->assertExitCode(0);

        Http::assertNothingSent();

        $recentOrder->refresh();
        $this->assertEquals(Order::STATUS_PENDING_PAYMENT, $recentOrder->status);
    }

    public function test_does_not_reconcile_orders_with_non_pending_status(): void
    {
        $client = $this->createClient();
        $approvedOrder = $this->createOrder($client, [
            'request_id' => '50005',
            'status' => Order::STATUS_APPROVED,
        ]);

        Order::where('id', $approvedOrder->id)->update([
            'updated_at' => Carbon::now()->subMinutes(60),
        ]);

        Http::fake();

        $this->artisan('orders:reconcile-pending')
            ->expectsOutputToContain('No se encontraron órdenes pendientes para reconciliar.')
            ->assertExitCode(0);

        Http::assertNothingSent();
    }

    public function test_does_not_reconcile_orders_without_request_id(): void
    {
        $client = $this->createClient();
        $orderWithoutReq = $this->createOrder($client, [
            'request_id' => null,
            'status' => Order::STATUS_PENDING_PAYMENT,
        ]);

        Order::where('id', $orderWithoutReq->id)->update([
            'updated_at' => Carbon::now()->subMinutes(30),
        ]);

        Http::fake();

        $this->artisan('orders:reconcile-pending')
            ->expectsOutputToContain('No se encontraron órdenes pendientes para reconciliar.')
            ->assertExitCode(0);

        Http::assertNothingSent();
    }

    public function test_continues_processing_when_one_order_fails_in_gateway(): void
    {
        $client = $this->createClient();

        $orderA = $this->createOrder($client, [
            'request_id' => '60001',
            'status' => Order::STATUS_PENDING_PAYMENT,
        ]);
        $orderB = $this->createOrder($client, [
            'request_id' => '60002',
            'status' => Order::STATUS_PENDING_PAYMENT,
        ]);

        Order::where('id', $orderA->id)->update(['updated_at' => Carbon::now()->subMinutes(20)]);
        Order::where('id', $orderB->id)->update(['updated_at' => Carbon::now()->subMinutes(20)]);

        // Order A fails with gateway 500 error
        // Order B succeeds with APPROVED
        Http::fake([
            'checkout-test.placetopay.com/api/session/60001' => Http::response(['error' => 'Timeout'], 500),
            'checkout-test.placetopay.com/api/session/60002' => Http::response([
                'requestId' => 60002,
                'status' => [
                    'status' => 'APPROVED',
                    'reason' => '00',
                    'message' => 'Aprobada',
                    'date' => now()->toIso8601String(),
                ],
            ], 200),
        ]);

        $this->artisan('orders:reconcile-pending')
            ->assertExitCode(0);

        $orderA->refresh();
        $orderB->refresh();

        $this->assertEquals(Order::STATUS_PENDING_PAYMENT, $orderA->status);
        $this->assertEquals(Order::STATUS_APPROVED, $orderB->status);
    }

    public function test_respects_custom_minutes_option(): void
    {
        $client = $this->createClient();

        $order = $this->createOrder($client, [
            'request_id' => '70001',
            'status' => Order::STATUS_PENDING_PAYMENT,
        ]);

        // 8 minutes old
        Order::where('id', $order->id)->update(['updated_at' => Carbon::now()->subMinutes(8)]);

        Http::fake([
            'checkout-test.placetopay.com/api/session/70001' => Http::response([
                'requestId' => 70001,
                'status' => [
                    'status' => 'REJECTED',
                    'reason' => '01',
                    'message' => 'Rechazada',
                    'date' => now()->toIso8601String(),
                ],
            ], 200),
        ]);

        // Default 15 min will not find it
        $this->artisan('orders:reconcile-pending')
            ->expectsOutputToContain('No se encontraron órdenes pendientes para reconciliar.');

        // Custom --minutes=5 will find and reconcile it
        $this->artisan('orders:reconcile-pending', ['--minutes' => 5])
            ->assertExitCode(0);

        $order->refresh();
        $this->assertEquals(Order::STATUS_REJECTED, $order->status);
    }

    public function test_scheduler_has_reconciliation_command_registered(): void
    {
        $schedule = app(Schedule::class);
        $events = collect($schedule->events());

        $reconcileEvent = $events->first(function ($event) {
            return str_contains($event->command, 'orders:reconcile-pending');
        });

        $this->assertNotNull($reconcileEvent, 'El comando orders:reconcile-pending debe estar programado en el Scheduler');
        $this->assertEquals('*/10 * * * *', $reconcileEvent->expression);
    }
}
