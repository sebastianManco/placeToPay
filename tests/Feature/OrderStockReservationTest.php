<?php

namespace Tests\Feature;

use App\Contracts\PaymentGatewayInterface;
use App\Models\Category;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Product;
use App\Models\User;
use App\Notifications\PaymentApprovedStockShortageNotification;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Notification;
use Tests\TestCase;

class OrderStockReservationTest extends TestCase
{
    use RefreshDatabase;

    protected Category $category;

    protected function setUp(): void
    {
        parent::setUp();
        $this->category = Category::create([
            'name' => 'General',
            'slug' => 'general',
            'is_active' => true,
        ]);
    }

    protected function createClient(array $attributes = []): User
    {
        static $counter = 100;
        $id = 72000000 + ($counter++);

        return User::create(array_merge([
            'identification' => $id,
            'name' => "Cliente{$counter}",
            'last_Name' => 'Prueba',
            'email' => "cliente{$counter}@example.com",
            'phone' => '3009876543',
            'direction' => 'Calle 50 # 10 - 20',
            'user_Name' => "cliente{$counter}",
            'password' => 'secret123',
            'email_verified_at' => now(),
            'is_active' => true,
            'role' => 'client',
        ], $attributes));
    }

    protected function createProduct(array $attributes = []): Product
    {
        static $counter = 100;
        $counter++;

        return Product::create(array_merge([
            'category_id' => $this->category->id,
            'name' => "Producto {$counter}",
            'slug' => "producto-{$counter}",
            'description' => 'Descripción del producto de prueba',
            'price' => 50000.00,
            'stock' => 5,
            'is_active' => true,
        ], $attributes));
    }

    protected function createOrderWithItems(User $user, Product $product, int $quantity = 2): Order
    {
        $order = Order::create([
            'reference' => Order::generateUniqueReference(),
            'user_identification' => $user->identification,
            'status' => Order::STATUS_PENDING_PAYMENT,
            'total_amount' => $product->price * $quantity,
            'currency' => 'COP',
            'customer_name' => "{$user->name} {$user->last_Name}",
            'customer_email' => $user->email,
            'customer_phone' => $user->phone,
            'customer_address' => $user->direction,
        ]);

        OrderItem::create([
            'order_id' => $order->id,
            'product_id' => $product->id,
            'product_name' => $product->name,
            'unit_price' => $product->price,
            'quantity' => $quantity,
            'subtotal' => $product->price * $quantity,
        ]);

        return $order;
    }

    public function test_order_reserves_stock_when_payment_is_initiated(): void
    {
        $client = $this->createClient();
        $product = $this->createProduct(['stock' => 10]);
        $order = $this->createOrderWithItems($client, $product, 3);

        Http::fake([
            'checkout-test.placetopay.com/api/session' => Http::response([
                'status' => ['status' => 'OK'],
                'requestId' => 55501,
                'processUrl' => 'https://checkout-test.placetopay.com/session/55501/token',
            ], 200),
        ]);

        $response = $this->actingAs($client)->post(route('payment.pay', $order->id));

        $response->assertRedirect('https://checkout-test.placetopay.com/session/55501/token');

        $order->refresh();
        $product->refresh();

        // Stock must have decremented by 3 (from 10 to 7)
        $this->assertEquals(7, $product->stock);
        // Order must be marked with stock_reserved = true
        $this->assertTrue($order->stock_reserved);
        $this->assertNotNull($order->stock_reserved_at);
    }

    public function test_payment_initiation_fails_cleanly_if_stock_is_insufficient(): void
    {
        $client = $this->createClient();
        $product = $this->createProduct(['stock' => 1]); // only 1 in stock
        $order = $this->createOrderWithItems($client, $product, 3); // requires 3

        $response = $this->actingAs($client)->post(route('payment.pay', $order->id));

        $response->assertRedirect(route('orders.show', $order->id));
        $response->assertSessionHas('error');

        $order->refresh();
        $product->refresh();

        // Stock was not decremented
        $this->assertEquals(1, $product->stock);
        $this->assertFalse($order->stock_reserved);
    }

    public function test_payment_initiation_releases_stock_if_gateway_call_fails(): void
    {
        $client = $this->createClient();
        $product = $this->createProduct(['stock' => 5]);
        $order = $this->createOrderWithItems($client, $product, 2);

        Http::fake([
            'checkout-test.placetopay.com/api/session' => Http::response([
                'status' => ['status' => 'FAILED', 'message' => 'Gateway error'],
            ], 500),
        ]);

        $response = $this->actingAs($client)->post(route('payment.pay', $order->id));

        $response->assertRedirect(route('orders.show', $order->id));
        $response->assertSessionHas('error');

        $order->refresh();
        $product->refresh();

        // Stock reservation was rolled back
        $this->assertEquals(5, $product->stock);
        $this->assertFalse($order->stock_reserved);
    }

    public function test_approved_payment_with_active_reservation_finalizes_without_double_decrementing(): void
    {
        $client = $this->createClient();
        $product = $this->createProduct(['stock' => 10]);
        $order = $this->createOrderWithItems($client, $product, 4);

        // Reserve stock
        $order->reserveStock();
        $product->refresh();
        $this->assertEquals(6, $product->stock);
        $this->assertTrue($order->stock_reserved);

        // Gateway returns APPROVED
        $order->markAsApproved();

        $order->refresh();
        $product->refresh();

        $this->assertTrue($order->isApproved());
        $this->assertFalse($order->stock_reserved);
        // Stock remains 6 (not decremented again)
        $this->assertEquals(6, $product->stock);
    }

    public function test_rejected_payment_releases_reserved_stock(): void
    {
        $client = $this->createClient();
        $product = $this->createProduct(['stock' => 10]);
        $order = $this->createOrderWithItems($client, $product, 4);

        $order->reserveStock();
        $product->refresh();
        $this->assertEquals(6, $product->stock);

        $order->markAsRejected();

        $order->refresh();
        $product->refresh();

        $this->assertTrue($order->isRejected());
        $this->assertFalse($order->stock_reserved);
        // Stock restored back to 10
        $this->assertEquals(10, $product->stock);
    }

    public function test_cancelled_payment_releases_reserved_stock(): void
    {
        $client = $this->createClient();
        $product = $this->createProduct(['stock' => 8]);
        $order = $this->createOrderWithItems($client, $product, 3);

        $order->reserveStock();
        $product->refresh();
        $this->assertEquals(5, $product->stock);

        $order->markAsCancelled();

        $order->refresh();
        $product->refresh();

        $this->assertEquals(Order::STATUS_CANCELLED, $order->status);
        $this->assertFalse($order->stock_reserved);
        $this->assertEquals(8, $product->stock);
    }

    public function test_critical_scenario_approved_payment_with_stockout_is_never_marked_rejected_and_triggers_reversal(): void
    {
        Notification::fake();

        $client = $this->createClient();
        $product = $this->createProduct(['stock' => 0]); // Stock is 0
        $order = $this->createOrderWithItems($client, $product, 2);
        $order->request_id = '77701';
        $order->save();

        // PlaceToPay reverse endpoint returns APPROVED reversal
        Http::fake([
            'checkout-test.placetopay.com/api/reverse' => Http::response([
                'status' => [
                    'status' => 'APPROVED',
                    'reason' => '00',
                    'message' => 'Reversión aprobada exitosamente',
                    'date' => now()->toIso8601String(),
                ],
                'payment' => [
                    'status' => ['status' => 'APPROVED'],
                ],
            ], 200),
        ]);

        $gatewayData = [
            'status' => ['status' => 'APPROVED'],
            'payment' => [
                [
                    'internalReference' => 888123,
                    'status' => ['status' => 'APPROVED'],
                ],
            ],
        ];

        // Process approved callback when stock is 0
        $order->updateStatusFromGateway('APPROVED', $gatewayData);

        $order->refresh();

        // Must NOT be marked as rejected!
        $this->assertFalse($order->isRejected(), 'La orden no debe ser marcada como REJECTED si el pago fue aprobado.');
        $this->assertTrue($order->isReversed(), 'La orden debe ser transicionada a REVERSED tras la reversión.');
        $this->assertFalse($order->canRetryPayment(), 'La orden revertida no debe permitir reintento de pago.');

        // Notification must have been sent
        Notification::assertSentOnDemand(
            PaymentApprovedStockShortageNotification::class,
            function ($notification, $channels, $notifiable) use ($order) {
                return $notification->order->id === $order->id;
            }
        );

        Http::assertSent(function ($request) {
            return $request->url() === 'https://checkout-test.placetopay.com/api/reverse' &&
                $request['internalReference'] === 888123;
        });
    }

    public function test_critical_scenario_approved_payment_transitions_to_refund_pending_when_reversal_fails(): void
    {
        Notification::fake();

        $client = $this->createClient();
        $product = $this->createProduct(['stock' => 0]); // Stock exhausted
        $order = $this->createOrderWithItems($client, $product, 2);
        $order->request_id = '77702';
        $order->save();

        // PlaceToPay reverse fails (e.g. PSE / non-reversible payment method)
        Http::fake([
            'checkout-test.placetopay.com/api/reverse' => Http::response([
                'status' => [
                    'status' => 'FAILED',
                    'reason' => '100',
                    'message' => 'Medio de pago no permite reversión automática',
                    'date' => now()->toIso8601String(),
                ],
            ], 200),
        ]);

        $gatewayData = [
            'status' => ['status' => 'APPROVED'],
            'payment' => [
                [
                    'internalReference' => 888456,
                    'status' => ['status' => 'APPROVED'],
                ],
            ],
        ];

        $order->updateStatusFromGateway('APPROVED', $gatewayData);

        $order->refresh();

        // Must NOT be marked as rejected
        $this->assertFalse($order->isRejected());
        $this->assertTrue($order->isRefundPending(), 'La orden debe quedar en refund_pending para intervención de soporte.');
        $this->assertFalse($order->canRetryPayment());

        Notification::assertSentOnDemand(PaymentApprovedStockShortageNotification::class);
    }

    public function test_return_callback_displays_informative_warning_when_order_is_refund_pending(): void
    {
        $client = $this->createClient();
        $product = $this->createProduct(['stock' => 0]);
        $order = $this->createOrderWithItems($client, $product, 1);
        $order->request_id = '99901';
        $order->save();

        Http::fake([
            'checkout-test.placetopay.com/api/session/99901' => Http::response([
                'requestId' => 99901,
                'status' => [
                    'status' => 'APPROVED',
                    'message' => 'Aprobada',
                ],
                'payment' => [
                    [
                        'internalReference' => 112233,
                        'status' => ['status' => 'APPROVED'],
                    ],
                ],
            ], 200),
            'checkout-test.placetopay.com/api/reverse' => Http::response([
                'status' => ['status' => 'FAILED', 'message' => 'PSE no reversible'],
            ], 200),
        ]);

        $response = $this->actingAs($client)->get(route('payment.response', $order->id));

        $response->assertRedirect(route('orders.show', $order->id));
        $response->assertSessionHas('warning');

        $order->refresh();
        $this->assertTrue($order->isRefundPending());
    }
}
