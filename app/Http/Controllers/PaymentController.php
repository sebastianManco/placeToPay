<?php

namespace App\Http\Controllers;

use App\Contracts\PaymentGatewayInterface;
use App\Models\Order;
use Exception;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

/**
 * Class PaymentController
 *
 * Coordinates checkout payment initiation, customer return callback,
 * and asynchronous webhook notifications from PlaceToPay.
 */
class PaymentController extends Controller
{
    /**
     * @var \App\Contracts\PaymentGatewayInterface
     */
    protected PaymentGatewayInterface $gateway;

    /**
     * PaymentController constructor.
     *
     * @param  \App\Contracts\PaymentGatewayInterface  $gateway
     */
    public function __construct(PaymentGatewayInterface $gateway)
    {
        $this->gateway = $gateway;
    }

    /**
     * Check if the authenticated user is allowed to access and pay for the order.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  \App\Models\Order  $order
     * @return void
     */
    protected function authorizeOrderAccess(Request $request, Order $order): void
    {
        $user = $request->user();

        if (! $user) {
            abort(401, 'No autenticado');
        }

        // Admin can access any order; client can only access their own orders
        if (! $user->isAdmin() && $order->user_identification !== $user->identification) {
            abort(403, 'No autorizado para acceder a esta orden.');
        }
    }

    /**
     * Initiate payment session with PlaceToPay and redirect the user.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  \App\Models\Order  $order
     * @return \Illuminate\Http\RedirectResponse
     */
    public function pay(Request $request, Order $order): RedirectResponse
    {
        $this->authorizeOrderAccess($request, $order);

        if ($order->isApproved()) {
            return redirect()->route('orders.show', $order->id)
                ->with('success', 'Esta orden ya se encuentra pagada y aprobada.');
        }

        if (! $order->canRetryPayment()) {
            return redirect()->route('orders.show', $order->id)
                ->with('error', 'Esta orden no permite procesar o reintentar el pago.');
        }

        if ($order->total_amount <= 0) {
            return redirect()->route('orders.show', $order->id)
                ->with('error', 'La orden debe tener un valor mayor a cero para ser pagada.');
        }

        try {
            $session = $this->gateway->createSession($order, [
                'returnUrl' => route('payment.response', $order->id),
                'ipAddress' => $request->ip(),
                'userAgent' => $request->userAgent(),
            ]);

            $order->update([
                'status' => Order::STATUS_PENDING_PAYMENT,
                'request_id' => (string) ($session['requestId'] ?? ''),
                'process_url' => (string) ($session['processUrl'] ?? ''),
            ]);

            if (! empty($session['processUrl'])) {
                return redirect()->away($session['processUrl']);
            }

            return redirect()->route('orders.show', $order->id)
                ->with('error', 'No fue posible obtener la URL de procesamiento de pago.');
        } catch (Exception $e) {
            Log::error("Error al iniciar sesión de pago para orden #{$order->reference}: " . $e->getMessage());

            return redirect()->route('orders.show', $order->id)
                ->with('error', 'Error al conectar con la pasarela de pagos: ' . $e->getMessage());
        }
    }

    /**
     * Handle user redirection back from PlaceToPay checkout.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  \App\Models\Order  $order
     * @return \Illuminate\Http\RedirectResponse
     */
    public function processResponse(Request $request, Order $order): RedirectResponse
    {
        if ($request->user()) {
            $this->authorizeOrderAccess($request, $order);
        }

        if ($order->request_id) {
            try {
                $statusData = $this->gateway->getSessionStatus($order->request_id);
                $gatewayStatus = $statusData['status']['status'] ?? 'PENDING';
                $order->updateStatusFromGateway($gatewayStatus);
            } catch (Exception $e) {
                Log::error("Error consultando estado en retorno de orden #{$order->reference}: " . $e->getMessage());
            }
        }

        $order->refresh();

        if ($order->isApproved()) {
            return redirect()->route('orders.show', $order->id)
                ->with('success', '¡Transacción aprobada! Tu pago ha sido procesado con éxito.');
        }

        if ($order->isRejected()) {
            return redirect()->route('orders.show', $order->id)
                ->with('error', 'La transacción fue rechazada o cancelada. Puedes intentar nuevamente.');
        }

        return redirect()->route('orders.show', $order->id)
            ->with('info', 'Tu pago se encuentra pendiente de confirmación.');
    }

    /**
     * Handle asynchronous server-to-server webhook notification from PlaceToPay.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function webhook(Request $request): JsonResponse
    {
        $requestId = $request->input('requestId') ?? $request->input('request_id');

        if (! $requestId) {
            return response()->json([
                'status' => 'FAILED',
                'message' => 'requestId es requerido en la notificación.',
            ], 400);
        }

        $order = Order::where('request_id', (string) $requestId)->first();

        if (! $order) {
            return response()->json([
                'status' => 'FAILED',
                'message' => 'Orden no encontrada para el requestId suministrado.',
            ], 404);
        }

        try {
            // Verify and fetch authoritative status directly from the gateway
            $statusData = $this->gateway->getSessionStatus($requestId);
            $gatewayStatus = $statusData['status']['status'] ?? 'PENDING';
            $order->updateStatusFromGateway($gatewayStatus);

            return response()->json([
                'status' => 'OK',
                'order_status' => $order->status,
            ]);
        } catch (Exception $e) {
            Log::error("Error en webhook PlaceToPay para requestId {$requestId}: " . $e->getMessage());

            return response()->json([
                'status' => 'ERROR',
                'message' => $e->getMessage(),
            ], 500);
        }
    }
}
