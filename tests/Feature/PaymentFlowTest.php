<?php

namespace Tests\Feature;

use App\Models\Order;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class PaymentFlowTest extends TestCase
{
    use RefreshDatabase;

    protected function createClient(array $attributes = []): User
    {
        static $counter = 1;
        $id = 71000000 + ($counter++);

        return User::create(array_merge([
            'identification' => $id,
            'name' => "Usuario{$counter}",
            'last_name' => 'Prueba',
            'email' => "usuario{$counter}@example.com",
            'phone' => '3001234567',
            'direction' => 'Calle 100 # 20 - 30',
            'user_name' => "usuario{$counter}",
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
            'total_amount' => 150000.00,
            'currency' => 'COP',
            'customer_name' => "{$user->name} {$user->last_name}",
            'customer_email' => $user->email,
            'customer_phone' => $user->phone,
            'customer_address' => $user->direction,
        ], $attributes));
    }

    public function test_authenticated_client_can_initiate_payment_and_is_redirected_to_process_url(): void
    {
        $client = $this->createClient();
        $order = $this->createOrder($client, [
            'total_amount' => 75000.00,
        ]);

        $mockProcessUrl = 'https://checkout-test.placetopay.com/session/10001/fake-secure-token';

        Http::fake([
            'checkout-test.placetopay.com/api/session' => Http::response([
                'status' => [
                    'status' => 'OK',
                    'reason' => 'PC',
                    'message' => 'La petición ha sido procesada correctamente',
                    'date' => now()->toIso8601String(),
                ],
                'requestId' => 10001,
                'processUrl' => $mockProcessUrl,
            ], 200),
        ]);

        $response = $this->actingAs($client)->post(route('payment.pay', $order->id));

        $response->assertRedirect($mockProcessUrl);

        $order->refresh();
        $this->assertEquals('10001', $order->request_id);
        $this->assertEquals($mockProcessUrl, $order->process_url);
        $this->assertEquals(Order::STATUS_PENDING_PAYMENT, $order->status);

        Http::assertSent(function ($request) use ($order) {
            return $request->url() === 'https://checkout-test.placetopay.com/api/session' &&
                $request['payment']['reference'] === $order->reference &&
                $request['payment']['amount']['total'] == 75000.00;
        });
    }

    public function test_return_callback_updates_order_to_approved_on_successful_payment(): void
    {
        $client = $this->createClient();
        $order = $this->createOrder($client, [
            'request_id' => '20002',
            'status' => Order::STATUS_PENDING_PAYMENT,
        ]);

        Http::fake([
            'checkout-test.placetopay.com/api/session/20002' => Http::response([
                'requestId' => 20002,
                'status' => [
                    'status' => 'APPROVED',
                    'reason' => '00',
                    'message' => 'La petición ha sido aprobada exitosamente',
                    'date' => now()->toIso8601String(),
                ],
                'payment' => [
                    [
                        'status' => ['status' => 'APPROVED', 'message' => 'Aprobada'],
                        'reference' => $order->reference,
                        'amount' => ['total' => 150000.00],
                    ],
                ],
            ], 200),
        ]);

        $response = $this->actingAs($client)->get(route('payment.response', $order->id));

        $response->assertRedirect(route('orders.show', $order->id));
        $response->assertSessionHas('success');

        $order->refresh();
        $this->assertTrue($order->isApproved());
        $this->assertEquals(Order::STATUS_APPROVED, $order->status);
    }

    public function test_return_callback_updates_order_to_rejected_on_rejected_payment(): void
    {
        $client = $this->createClient();
        $order = $this->createOrder($client, [
            'request_id' => '30003',
            'status' => Order::STATUS_PENDING_PAYMENT,
        ]);

        Http::fake([
            'checkout-test.placetopay.com/api/session/30003' => Http::response([
                'requestId' => 30003,
                'status' => [
                    'status' => 'REJECTED',
                    'reason' => '?R',
                    'message' => 'Transacción rechazada por el banco emisor',
                    'date' => now()->toIso8601String(),
                ],
            ], 200),
        ]);

        $response = $this->actingAs($client)->get(route('payment.response', $order->id));

        $response->assertRedirect(route('orders.show', $order->id));
        $response->assertSessionHas('error');

        $order->refresh();
        $this->assertTrue($order->isRejected());
        $this->assertEquals(Order::STATUS_REJECTED, $order->status);
    }

    public function test_return_callback_keeps_order_pending_when_still_pending(): void
    {
        $client = $this->createClient();
        $order = $this->createOrder($client, [
            'request_id' => '40004',
            'status' => Order::STATUS_PENDING_PAYMENT,
        ]);

        Http::fake([
            'checkout-test.placetopay.com/api/session/40004' => Http::response([
                'requestId' => 40004,
                'status' => [
                    'status' => 'PENDING',
                    'reason' => 'PT',
                    'message' => 'La transacción se encuentra pendiente',
                    'date' => now()->toIso8601String(),
                ],
            ], 200),
        ]);

        $response = $this->actingAs($client)->get(route('payment.response', $order->id));

        $response->assertRedirect(route('orders.show', $order->id));
        $response->assertSessionHas('info');

        $order->refresh();
        $this->assertTrue($order->isPendingPayment());
        $this->assertEquals(Order::STATUS_PENDING_PAYMENT, $order->status);
    }

    public function test_webhook_asynchronously_updates_order_status_without_csrf(): void
    {
        $client = $this->createClient();
        $order = $this->createOrder($client, [
            'request_id' => '50005',
            'status' => Order::STATUS_PENDING_PAYMENT,
        ]);

        Http::fake([
            'checkout-test.placetopay.com/api/session/50005' => Http::response([
                'requestId' => 50005,
                'status' => [
                    'status' => 'APPROVED',
                    'reason' => '00',
                    'message' => 'La petición ha sido aprobada exitosamente',
                    'date' => now()->toIso8601String(),
                ],
            ], 200),
        ]);

        // Webhook is called server-to-server with JSON payload
        $tranKey = (string) config('placetopay.tranKey', 'test_tran_key_12345');
        $signature = hash_hmac('sha256', '50005', $tranKey);

        $response = $this->withHeaders(['X-Signature' => $signature])->postJson(route('payment.notification'), [
            'requestId' => 50005,
            'status' => [
                'status' => 'APPROVED',
                'reason' => '00',
                'message' => 'Aprobada',
                'date' => now()->toIso8601String(),
            ],
        ]);

        $response->assertOk();
        $response->assertJson([
            'status' => 'OK',
            'order_status' => Order::STATUS_APPROVED,
        ]);

        $order->refresh();
        $this->assertEquals(Order::STATUS_APPROVED, $order->status);
    }

    public function test_client_cannot_initiate_payment_for_another_clients_order(): void
    {
        $clientA = $this->createClient();
        $clientB = $this->createClient();

        $orderB = $this->createOrder($clientB);

        $response = $this->actingAs($clientA)->post(route('payment.pay', $orderB->id));

        $response->assertForbidden();
    }

    public function test_already_approved_order_cannot_be_repaid(): void
    {
        $client = $this->createClient();
        $order = $this->createOrder($client, [
            'status' => Order::STATUS_APPROVED,
        ]);

        $response = $this->actingAs($client)->post(route('payment.pay', $order->id));

        $response->assertRedirect(route('orders.show', $order->id));
        $response->assertSessionHas('success', 'Esta orden ya se encuentra pagada y aprobada.');
    }

    public function test_order_detail_view_displays_placetopay_payment_button_when_pending(): void
    {
        $client = $this->createClient();
        $order = $this->createOrder($client, [
            'status' => Order::STATUS_PENDING_PAYMENT,
        ]);

        $response = $this->actingAs($client)->get(route('orders.show', $order->id));

        $response->assertOk();
        $response->assertSee('Pasarela de Pagos PlaceToPay');
        $response->assertSee('Pagar con PlaceToPay');
        $response->assertSee(route('payment.pay', $order->id));
    }
}
