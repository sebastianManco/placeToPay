<?php

namespace App\Services\Payment;

use App\Contracts\PaymentGatewayInterface;
use App\Models\Order;
use Carbon\Carbon;
use Exception;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;

/**
 * Class PlaceToPayGateway
 *
 * Implements the PlaceToPay WebCheckout REST protocol using the Adapter Pattern.
 */
class PlaceToPayGateway implements PaymentGatewayInterface
{
    /**
     * @var string
     */
    protected string $login;

    /**
     * @var string
     */
    protected string $tranKey;

    /**
     * @var string
     */
    protected string $baseUrl;

    /**
     * @var string
     */
    protected string $restType;

    /**
     * @var int
     */
    protected int $timeout;

    /**
     * PlaceToPayGateway constructor.
     *
     * @param  array<string, mixed>|null  $config
     */
    public function __construct(?array $config = null)
    {
        $cfg = $config ?? config('placetopay', []);

        $this->login = (string) ($cfg['login'] ?? '');
        $this->tranKey = (string) ($cfg['tranKey'] ?? '');
        $this->baseUrl = (string) ($cfg['url'] ?? 'https://checkout-test.placetopay.com');
        $this->restType = strtolower((string) ($cfg['rest_type'] ?? 'sha256'));
        $this->timeout = (int) ($cfg['timeout'] ?? 30);
    }

    /**
     * Generate the cryptographic authentication block required by PlaceToPay REST API.
     *
     * @param  string|null  $fixedSeed
     * @param  string|null  $fixedRawNonce
     * @return array{login: string, tranKey: string, nonce: string, seed: string}
     */
    public function generateAuth(?string $fixedSeed = null, ?string $fixedRawNonce = null): array
    {
        $seed = $fixedSeed ?? Carbon::now()->toIso8601String();
        $rawNonce = $fixedRawNonce ?? Str::random(16);
        $encodedNonce = base64_encode($rawNonce);

        if ($this->restType === 'sha1') {
            $tranKeyHash = base64_encode(sha1($rawNonce . $seed . $this->tranKey, true));
        } else {
            $tranKeyHash = base64_encode(hash('sha256', $rawNonce . $seed . $this->tranKey, true));
        }

        return [
            'login' => $this->login,
            'tranKey' => $tranKeyHash,
            'nonce' => $encodedNonce,
            'seed' => $seed,
        ];
    }

    /**
     * Create a checkout payment session in PlaceToPay WebCheckout.
     *
     * @param  \App\Models\Order  $order
     * @param  array<string, mixed>  $options
     * @return array<string, mixed>
     *
     * @throws \Exception
     */
    public function createSession(Order $order, array $options = []): array
    {
        $endpoint = rtrim($this->baseUrl, '/') . '/api/session';

        $expiration = $options['expiration'] ?? Carbon::now()->addHours(24)->toIso8601String();
        $returnUrl = $options['returnUrl'] ?? route('payment.response', $order->id);
        $ipAddress = $options['ipAddress'] ?? request()->ip() ?? '127.0.0.1';
        $userAgent = $options['userAgent'] ?? request()->userAgent() ?? 'PlaceToPay/1.0';

        $payload = [
            'auth' => $this->generateAuth(),
            'locale' => 'es_CO',
            'buyer' => [
                'name' => $order->customer_name ?: 'Cliente General',
                'email' => $order->customer_email ?: 'cliente@example.com',
                'mobile' => $order->customer_phone ?: '3000000000',
                'address' => [
                    'street' => $order->customer_address ?: 'Dirección no especificada',
                ],
            ],
            'payment' => [
                'reference' => $order->reference,
                'description' => "Pago de la orden #{$order->reference}",
                'amount' => [
                    'currency' => $order->currency ?: 'COP',
                    'total' => (float) $order->total_amount,
                ],
                'allowPartial' => false,
            ],
            'expiration' => $expiration,
            'returnUrl' => $returnUrl,
            'ipAddress' => $ipAddress,
            'userAgent' => $userAgent,
        ];

        $response = Http::timeout($this->timeout)->post($endpoint, $payload);

        if (! $response->successful()) {
            throw new Exception("Error de comunicación con PlaceToPay: Código {$response->status()}");
        }

        $data = $response->json();

        if (! is_array($data)) {
            throw new Exception("Respuesta inválida recibida de PlaceToPay.");
        }

        if (isset($data['status']['status']) && $data['status']['status'] === 'FAILED') {
            $msg = $data['status']['message'] ?? 'Fallo al inicializar sesión en PlaceToPay';
            throw new Exception($msg);
        }

        return $data;
    }

    /**
     * Query session information from PlaceToPay WebCheckout.
     *
     * @param  string|int  $requestId
     * @return array<string, mixed>
     *
     * @throws \Exception
     */
    public function getSessionStatus(string|int $requestId): array
    {
        $endpoint = rtrim($this->baseUrl, '/') . "/api/session/{$requestId}";

        $payload = [
            'auth' => $this->generateAuth(),
        ];

        $response = Http::timeout($this->timeout)->post($endpoint, $payload);

        if (! $response->successful()) {
            throw new Exception("Error al consultar el estado de la sesión en PlaceToPay: Código {$response->status()}");
        }

        $data = $response->json();

        if (! is_array($data)) {
            throw new Exception("Respuesta inválida recibida al consultar sesión en PlaceToPay.");
        }

        return $data;
    }

    /**
     * Return configured base endpoint URL.
     *
     * @return string
     */
    public function getBaseUrl(): string
    {
        return $this->baseUrl;
    }

    /**
     * Validate the cryptographic signature or authenticity of an incoming webhook notification.
     * Supports HMAC-SHA256 (via X-Signature header or signature payload field) and PlaceToPay hash standards.
     *
     * @param  mixed  $request
     * @return bool
     */
    public function isValidWebhookNotification(mixed $request): bool
    {
        if (empty($this->tranKey)) {
            return false;
        }

        $content = '';
        $data = [];
        $headerSignature = null;

        if ($request instanceof Request) {
            $content = (string) $request->getContent();
            $data = $request->all();
            $headerSignature = $request->header('X-Signature')
                ?? $request->header('Signature')
                ?? $request->header('x-signature');
        } elseif (is_array($request)) {
            $data = $request;
            $content = (string) json_encode($request);
            $headerSignature = $request['headers']['x-signature']
                ?? $request['headers']['X-Signature']
                ?? null;
        } else {
            return false;
        }

        $rawSignature = $headerSignature ?? $data['signature'] ?? $data['sign'] ?? null;

        if (empty($rawSignature) || ! is_string($rawSignature)) {
            return false;
        }

        // Strip prefixes if present (e.g. "sha256:...", "sha256=...", "sha1:...")
        $cleanSignature = trim($rawSignature);
        if (preg_match('/^(?:sha256|sha1)[=:]\s*(.+)$/i', $cleanSignature, $matches)) {
            $cleanSignature = trim($matches[1]);
        }

        $requestId = (string) ($data['requestId'] ?? $data['request_id'] ?? '');
        $status = is_array($data['status'] ?? null)
            ? (string) ($data['status']['status'] ?? '')
            : (string) ($data['status'] ?? '');
        $date = is_array($data['status'] ?? null)
            ? (string) ($data['status']['date'] ?? '')
            : (string) ($data['date'] ?? '');

        // Generate valid signature variations across PlaceToPay HMAC and hashing conventions
        $validSignatures = [];

        // 1. HMAC-SHA256 of raw body (Standard PlaceToPay webhook / X-Signature)
        if ($content !== '') {
            $validSignatures[] = hash_hmac('sha256', $content, $this->tranKey);
            $validSignatures[] = base64_encode(hash_hmac('sha256', $content, $this->tranKey, true));
        }

        // 2. HMAC-SHA256 of concatenated fields (requestId + status + date)
        if ($requestId !== '' || $status !== '') {
            $concatWithDate = $requestId . $status . $date;
            $validSignatures[] = hash_hmac('sha256', $concatWithDate, $this->tranKey);
            $validSignatures[] = base64_encode(hash_hmac('sha256', $concatWithDate, $this->tranKey, true));

            // HMAC-SHA256 of requestId + status
            $concatWithoutDate = $requestId . $status;
            $validSignatures[] = hash_hmac('sha256', $concatWithoutDate, $this->tranKey);

            // HMAC-SHA256 of requestId alone
            if ($requestId !== '') {
                $validSignatures[] = hash_hmac('sha256', $requestId, $this->tranKey);
            }

            // Standard PlaceToPay WebCheckout SHA-256 hash
            $validSignatures[] = hash('sha256', $concatWithDate . $this->tranKey);
            $validSignatures[] = hash('sha256', $concatWithoutDate . $this->tranKey);

            // Standard PlaceToPay WebCheckout SHA-1 hash
            $validSignatures[] = sha1($concatWithDate . $this->tranKey);
            $validSignatures[] = sha1($concatWithoutDate . $this->tranKey);

            // HMAC-SHA1
            $validSignatures[] = hash_hmac('sha1', $concatWithDate, $this->tranKey);
        }

        // Constant-time comparison against each valid candidate to prevent timing attacks
        foreach ($validSignatures as $candidate) {
            if (hash_equals(strtolower($candidate), strtolower($cleanSignature)) || hash_equals($candidate, $cleanSignature)) {
                return true;
            }
        }

        return false;
    }

    /**
     * Generate HMAC-SHA256 signature for webhook parameters.
     *
     * @param  string|int  $requestId
     * @param  string  $status
     * @param  string  $date
     * @return string
     */
    public function generateWebhookSignature(string|int $requestId, string $status, string $date): string
    {
        return hash_hmac('sha256', "{$requestId}{$status}{$date}", $this->tranKey);
    }
}
