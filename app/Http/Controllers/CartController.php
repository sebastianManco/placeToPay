<?php

namespace App\Http\Controllers;

use App\Models\Order;
use App\Models\Product;
use App\Services\CartService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;
use InvalidArgumentException;

/**
 * Class CartController
 *
 * Handles HTTP requests for shopping cart operations and checkout flow.
 */
class CartController extends Controller
{
    /**
     * @var \App\Services\CartService
     */
    protected CartService $cartService;

    /**
     * CartController constructor.
     *
     * @param  \App\Services\CartService  $cartService
     */
    public function __construct(CartService $cartService)
    {
        $this->cartService = $cartService;
    }

    /**
     * Helper to obtain the active shopping cart for the current session or user.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \App\Models\Order
     */
    private function resolveCart(Request $request): Order
    {
        return $this->cartService->getCart($request->user(), $request->session()->getId());
    }

    /**
     * Display the current shopping cart.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\View\View
     */
    public function index(Request $request): View
    {
        $cart = $this->resolveCart($request);
        $cart->load(['items.product']);

        return view('cart.index', compact('cart'));
    }

    /**
     * Add a catalog product to the shopping cart.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\RedirectResponse
     */
    public function addItem(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'product_id' => 'required|exists:products,id',
            'quantity' => 'nullable|integer|min:1',
        ]);

        $cart = $this->resolveCart($request);
        $product = Product::findOrFail($validated['product_id']);
        $quantity = (int) ($validated['quantity'] ?? 1);

        try {
            $this->cartService->addItem($cart, $product, $quantity);

            return redirect()->back()->with('success', 'Producto agregado al carrito.');
        } catch (InvalidArgumentException $e) {
            return redirect()->back()->with('error', $e->getMessage());
        }
    }

    /**
     * Update the quantity of a specific item in the cart.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  int  $itemId
     * @return \Illuminate\Http\RedirectResponse
     */
    public function updateItem(Request $request, int $itemId): RedirectResponse
    {
        $validated = $request->validate([
            'quantity' => 'required|integer|min:0',
        ]);

        $cart = $this->resolveCart($request);
        $isCheckout = $request->input('redirect_to') === 'checkout';

        try {
            $this->cartService->updateItemQuantity($cart, $itemId, (int) $validated['quantity']);

            if ($isCheckout) {
                if ($cart->items()->count() === 0) {
                    return redirect()->route('cart.index')->with('success', 'El carrito ha quedado vacío.');
                }
                return redirect()->route('cart.checkout')->with('success', 'Cantidad actualizada correctamente.');
            }

            return redirect()->route('cart.index')->with('success', 'Cantidad actualizada correctamente.');
        } catch (InvalidArgumentException $e) {
            $redirectRoute = $isCheckout ? 'cart.checkout' : 'cart.index';
            return redirect()->route($redirectRoute)->with('error', $e->getMessage());
        }
    }

    /**
     * Remove an item from the shopping cart.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  int  $itemId
     * @return \Illuminate\Http\RedirectResponse
     */
    public function removeItem(Request $request, int $itemId): RedirectResponse
    {
        $cart = $this->resolveCart($request);
        $this->cartService->removeItem($cart, $itemId);

        if ($request->input('redirect_to') === 'checkout') {
            if ($cart->items()->count() === 0) {
                return redirect()->route('cart.index')->with('success', 'Producto eliminado. El carrito ha quedado vacío.');
            }

            return redirect()->route('cart.checkout')->with('success', 'Producto eliminado del pedido.');
        }

        return redirect()->route('cart.index')->with('success', 'Producto eliminado del carrito.');
    }

    /**
     * Remove all items from the shopping cart.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\RedirectResponse
     */
    public function clear(Request $request): RedirectResponse
    {
        $cart = $this->resolveCart($request);
        $this->cartService->clearCart($cart);

        return redirect()->route('cart.index')->with('success', 'Carrito vaciado exitosamente.');
    }

    /**
     * Display the checkout and order review screen.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\View\View|\Illuminate\Http\RedirectResponse
     */
    public function checkout(Request $request): View|RedirectResponse
    {
        $cart = $this->resolveCart($request);
        $cart->load(['items.product']);

        if ($cart->items->isEmpty()) {
            return redirect()->route('cart.index')->with('error', 'El carrito se encuentra vacío.');
        }

        return view('cart.checkout', compact('cart'));
    }

    /**
     * Confirm the order and transition it to pending payment.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\RedirectResponse
     */
    public function confirm(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'customer_name' => 'required|string|max:128',
            'customer_email' => 'required|email|max:128',
            'customer_phone' => 'nullable|string|max:32',
            'customer_address' => 'nullable|string|max:255',
        ]);

        $cart = $this->resolveCart($request);

        try {
            $order = $this->cartService->confirmOrder($cart, $validated);

            return redirect()->route('orders.show', $order->id)
                ->with('success', 'Orden confirmada exitosamente. Lista para procesar pago.');
        } catch (InvalidArgumentException $e) {
            return redirect()->route('cart.checkout')->with('error', $e->getMessage());
        }
    }
}
