<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Order;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * Class OrderController
 *
 * Handles administrative management, filtering, and status updates for orders.
 */
class OrderController extends Controller
{
    /**
     * Display a global listing of orders with filters and summary statistics.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\View\View
     */
    public function index(Request $request): View
    {
        $query = Order::query()->with(['user', 'items']);

        // Filter by reference or general search
        if ($request->filled('search')) {
            $search = $request->input('search');
            $query->where(function ($q) use ($search) {
                $q->where('reference', 'like', "%{$search}%")
                  ->orWhere('customer_name', 'like', "%{$search}%")
                  ->orWhere('customer_email', 'like', "%{$search}%");
            });
        }

        // Filter by specific client (name, email or identification)
        if ($request->filled('client')) {
            $client = $request->input('client');
            $query->where(function ($q) use ($client) {
                $q->where('user_identification', 'like', "%{$client}%")
                  ->orWhere('customer_name', 'like', "%{$client}%")
                  ->orWhere('customer_email', 'like', "%{$client}%")
                  ->orWhereHas('user', function ($userQuery) use ($client) {
                      $userQuery->where('name', 'like', "%{$client}%")
                                ->orWhere('last_Name', 'like', "%{$client}%")
                                ->orWhere('email', 'like', "%{$client}%")
                                ->orWhere('identification', 'like', "%{$client}%");
                  });
            });
        }

        // Filter by order status
        if ($request->filled('status')) {
            $query->where('status', $request->input('status'));
        }

        // Filter by date range
        if ($request->filled('date_from')) {
            $query->whereDate('created_at', '>=', $request->input('date_from'));
        }

        if ($request->filled('date_to')) {
            $query->whereDate('created_at', '<=', $request->input('date_to'));
        }

        $orders = $query->orderByDesc('created_at')
            ->paginate(10)
            ->withQueryString();

        // Summary metrics
        $totalOrders = Order::count();
        $pendingOrders = Order::where('status', Order::STATUS_PENDING_PAYMENT)->count();
        $approvedOrders = Order::where('status', Order::STATUS_APPROVED)->count();
        $rejectedOrders = Order::where('status', Order::STATUS_REJECTED)->count();
        $cancelledOrders = Order::where('status', Order::STATUS_CANCELLED)->count();

        return view('admin.orders.index', compact(
            'orders',
            'totalOrders',
            'pendingOrders',
            'approvedOrders',
            'rejectedOrders',
            'cancelledOrders'
        ));
    }

    /**
     * Display the detailed administrative view of an order.
     *
     * @param  \App\Models\Order  $order
     * @return \Illuminate\View\View
     */
    public function show(Order $order): View
    {
        $order->load(['items.product', 'user']);

        return view('admin.orders.show', compact('order'));
    }

    /**
     * Update the status of a specific order.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  \App\Models\Order  $order
     * @return \Illuminate\Http\RedirectResponse
     */
    public function updateStatus(Request $request, Order $order): RedirectResponse
    {
        $this->authorize('updateStatus', $order);

        $validated = $request->validate([
            'status' => 'required|string|in:in_cart,pending_payment,approved,rejected,cancelled',
        ]);

        $order->update([
            'status' => $validated['status'],
        ]);

        $statusLabels = [
            'in_cart' => 'En Carrito',
            'pending_payment' => 'Pendiente de Pago',
            'approved' => 'Aprobado',
            'rejected' => 'Rechazado',
            'cancelled' => 'Cancelado',
        ];

        $statusLabel = $statusLabels[$validated['status']] ?? $validated['status'];

        return redirect()->back()->with('success', "Estado del pedido #{$order->reference} actualizado a: {$statusLabel}.");
    }
}
