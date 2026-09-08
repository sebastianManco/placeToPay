<?php

namespace App\Http\Controllers;

use App\Models\Order;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * Class OrderController
 *
 * Handles display and tracking of confirmed orders.
 */
class OrderController extends Controller
{
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

        return view('orders.show', compact('order'));
    }
}
