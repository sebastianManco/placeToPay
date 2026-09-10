<?php

namespace Tests\Unit;

use App\Models\Order;
use App\Services\Payment\PlaceToPayGateway;
use Exception;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class PlaceToPayGatewayTest extends TestCase
{
    protected array $config = [
        'login' => 'test_login_user',
        'tranKey' => 'test_secret_key_123',
        'url' => 'https://checkout-test.placetopay.com',
        'rest_type' => 'sha256',
        'timeout' => 15,
    ];

    public function test_generates_correct_sha256_authentication_block(): void
    {
        $gateway = new PlaceToPayGateway($this->config);

        $seed = '2026-09-08T15:00:00-05:00';
        $rawNonce = '1234567890abcdef';

        $auth = $gateway->generateAuth($seed, $rawNonce);

        $expectedNonce = base64_encode($rawNonce);
        $expectedTranKey = base64_encode(hash('sha256', $rawNonce . $seed . $this->config['tranKey'], true));

        $this->assertEquals('test_login_user', $auth['login']);
        $this->assertEquals($seed, $auth['seed']);
        $this->assertEquals($expectedNonce, $auth['nonce']);
        $this->assertEquals($expectedTranKey, $auth['tranKey']);
    }

    public function test_generates_correct_sha1_authentication_block(): void
    {
        $sha1Config = array_merge($this->config, ['rest_type' => 'sha1']);
        $gateway = new PlaceToPayGateway($sha1Config);

        $seed = '2026-09-08T15:00:00-05:00';
        $rawNonce = '1234567890abcdef';

        $auth = $gateway->generateAuth($seed, $rawNonce);

        $expectedNonce = base64_encode($rawNonce);
        $expectedTranKey = base64_encode(sha1($rawNonce . $seed . $sha1Config['tranKey'], true));

        $this->assertEquals('test_login_user', $auth['login']);
        $this->assertEquals($seed, $auth['seed']);
        $this->assertEquals($expectedNonce, $auth['nonce']);
        $this->assertEquals($expectedTranKey, $auth['tranKey']);
    }

    public function test_create_session_sends_proper_payload_and_returns_session_data(): void
    {
        Http::fake([
            'checkout-test.placetopay.com/api/session' => Http::response([
                'status' => [
                    'status' => 'OK',
                    'reason' => 'PC',
                    'message' => 'La petición ha sido procesada correctamente',
                    'date' => '2026-09-08T15:30:00-05:00',
                ],
                'requestId' => 98765,
                'processUrl' => 'https://checkout-test.placetopay.com/session/98765/mock-token-abc',
            ], 200),
        ]);

        $gateway = new PlaceToPayGateway($this->config);

        $order = new Order([
            'reference' => 'ORD-TEST1234',
            'total_amount' => 125000.50,
            'currency' => 'COP',
            'customer_name' => 'Carlos Perez',
            'customer_email' => 'carlos@example.com',
            'customer_phone' => '3101234567',
            'customer_address' => 'Calle 100 #15-20',
        ]);
        $order->id = 42;

        $response = $gateway->createSession($order);

        $this->assertIsArray($response);
        $this->assertEquals(98765, $response['requestId']);
        $this->assertEquals('https://checkout-test.placetopay.com/session/98765/mock-token-abc', $response['processUrl']);
        $this->assertEquals('OK', $response['status']['status']);

        Http::assertSent(function ($request) {
            $data = $request->data();

            return $request->url() === 'https://checkout-test.placetopay.com/api/session' &&
                $data['payment']['reference'] === 'ORD-TEST1234' &&
                $data['payment']['amount']['total'] == 125000.50 &&
                $data['payment']['amount']['currency'] === 'COP' &&
                $data['buyer']['email'] === 'carlos@example.com' &&
                isset($data['auth']['login']) &&
                isset($data['auth']['tranKey']);
        });
    }

    public function test_create_session_throws_exception_on_http_error(): void
    {
        $this->expectException(Exception::class);
        $this->expectExceptionMessage('Error de comunicación con PlaceToPay');

        Http::fake([
            'checkout-test.placetopay.com/api/session' => Http::response(['error' => 'Server error'], 500),
        ]);

        $gateway = new PlaceToPayGateway($this->config);

        $order = new Order([
            'reference' => 'ORD-FAIL',
            'total_amount' => 50000,
        ]);
        $order->id = 1;

        $gateway->createSession($order);
    }

    public function test_create_session_throws_exception_on_gateway_status_failed(): void
    {
        $this->expectException(Exception::class);
        $this->expectExceptionMessage('Credenciales de autenticación no válidas');

        Http::fake([
            'checkout-test.placetopay.com/api/session' => Http::response([
                'status' => [
                    'status' => 'FAILED',
                    'reason' => '401',
                    'message' => 'Credenciales de autenticación no válidas',
                ],
            ], 200),
        ]);

        $gateway = new PlaceToPayGateway($this->config);

        $order = new Order([
            'reference' => 'ORD-FAILED-AUTH',
            'total_amount' => 50000,
        ]);
        $order->id = 1;

        $gateway->createSession($order);
    }

    public function test_get_session_status_returns_gateway_response(): void
    {
        Http::fake([
            'checkout-test.placetopay.com/api/session/12345' => Http::response([
                'requestId' => 12345,
                'status' => [
                    'status' => 'APPROVED',
                    'reason' => '00',
                    'message' => 'La petición ha sido aprobada exitosamente',
                    'date' => '2026-09-08T15:35:00-05:00',
                ],
                'payment' => [
                    [
                        'status' => [
                            'status' => 'APPROVED',
                            'message' => 'Aprobada',
                        ],
                        'reference' => 'ORD-TEST1234',
                        'amount' => ['total' => 125000.50],
                    ],
                ],
            ], 200),
        ]);

        $gateway = new PlaceToPayGateway($this->config);
        $status = $gateway->getSessionStatus(12345);

        $this->assertEquals('APPROVED', $status['status']['status']);
        $this->assertEquals(12345, $status['requestId']);

        Http::assertSent(function ($request) {
            return $request->url() === 'https://checkout-test.placetopay.com/api/session/12345' &&
                isset($request->data()['auth']['login']);
        });
    }

    public function test_reverse_payment_sends_proper_payload_to_reverse_endpoint(): void
    {
        Http::fake([
            'checkout-test.placetopay.com/api/reverse' => Http::response([
                'status' => [
                    'status' => 'APPROVED',
                    'reason' => '00',
                    'message' => 'Reversión aprobada',
                    'date' => '2026-09-08T16:00:00-05:00',
                ],
                'payment' => [
                    'status' => ['status' => 'APPROVED'],
                ],
            ], 200),
        ]);

        $gateway = new PlaceToPayGateway($this->config);
        $order = new Order([
            'reference' => 'ORD-REVERSE123',
            'request_id' => '12345',
        ]);

        $result = $gateway->reversePayment($order, ['internalReference' => 999888]);

        $this->assertEquals('APPROVED', $result['status']['status']);

        Http::assertSent(function ($request) {
            return $request->url() === 'https://checkout-test.placetopay.com/api/reverse' &&
                $request['internalReference'] === 999888 &&
                isset($request['auth']['tranKey']);
        });
    }

    public function test_reverse_payment_returns_failed_when_no_internal_reference(): void
    {
        $gateway = new PlaceToPayGateway($this->config);
        $order = new Order([
            'reference' => 'ORD-NOREF',
        ]);

        $result = $gateway->reversePayment($order);

        $this->assertEquals('FAILED', $result['status']['status']);
    }
}
