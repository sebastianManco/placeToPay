<?php

namespace App\Http\Controllers;

use App\Models\Order;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * Class OrderController
 *
 * Handles display, tracking, and history of orders for customers.
 */
class OrderController extends Controller
{
    /**
     * Display a paginated listing of the authenticated customer's orders with optional filters.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\View\View
     */
    public function index(Request $request): View
    {
        $user = $request->user();

        $query = Order::with('items')
            ->where('user_identification', $user->identification)
            ->where('status', '!=', Order::STATUS_IN_CART);

        if ($request->filled('status')) {
            $query->where('status', $request->input('status'));
        }

        if ($request->filled('date_from')) {
            $query->whereDate('created_at', '>=', $request->input('date_from'));
        }

        if ($request->filled('date_to')) {
            $query->whereDate('created_at', '<=', $request->input('date_to'));
        }

        if ($request->filled('search')) {
            $search = $request->input('search');
            $query->where('reference', 'like', "%{$search}%");
        }

        $orders = $query->orderByDesc('created_at')
            ->paginate(10)
            ->appends($request->query());

        return view('orders.index', compact('orders'));
    }

    /**
     * Display the specified order details.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  int  $orderId
     * @return \Illuminate\View\View
     */
    public function show(Request $request, int $orderId): View
    {
        $order = Order::with('items.product')->findOrFail($orderId);

        $this->authorize('view', $order);

        return view('orders.show', compact('order'));
    }
}
