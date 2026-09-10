<?php

namespace App\Services;

use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Product;
use App\Models\User;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

/**
 * Class CartService
 *
 * Handles domain logic for shopping cart operations, calculations,
 * stock validation, item modifications, and order preparation.
 */
class CartService
{
    /**
     * Retrieve the current active cart for a user or session, or create a new one.
     *
     * @param  \App\Models\User|null  $user
     * @param  string|null  $sessionId
     * @return \App\Models\Order
     */
    public function getCart(?User $user = null, ?string $sessionId = null): Order
    {
        if ($user) {
            // Check if there is an active cart for the authenticated user
            $cart = Order::where('user_identification', $user->identification)
                ->where('status', Order::STATUS_IN_CART)
                ->first();

            // If user has no cart but has a session with an active cart, attach it
            if (! $cart && $sessionId) {
                $sessionCart = Order::where('session_id', $sessionId)
                    ->whereNull('user_identification')
                    ->where('status', Order::STATUS_IN_CART)
                    ->first();

                if ($sessionCart) {
                    $sessionCart->update(['user_identification' => $user->identification]);
                    return $sessionCart;
                }
            }

            if ($cart) {
                return $cart;
            }

            return Order::create([
                'reference' => Order::generateUniqueReference(),
                'user_identification' => $user->identification,
                'session_id' => $sessionId,
                'status' => Order::STATUS_IN_CART,
                'total_amount' => 0.00,
                'currency' => 'COP',
            ]);
        }

        if ($sessionId) {
            $cart = Order::where('session_id', $sessionId)
                ->whereNull('user_identification')
                ->where('status', Order::STATUS_IN_CART)
                ->first();

            if ($cart) {
                return $cart;
            }

            return Order::create([
                'reference' => Order::generateUniqueReference(),
                'session_id' => $sessionId,
                'status' => Order::STATUS_IN_CART,
                'total_amount' => 0.00,
                'currency' => 'COP',
            ]);
        }

        // Fallback if neither user nor sessionId is provided
        return Order::create([
            'reference' => Order::generateUniqueReference(),
            'status' => Order::STATUS_IN_CART,
            'total_amount' => 0.00,
            'currency' => 'COP',
        ]);
    }

    /**
     * Add a product to the cart with stock and availability validations.
     *
     * @param  \App\Models\Order  $cart
     * @param  \App\Models\Product  $product
     * @param  int  $quantity
     * @return \App\Models\OrderItem
     *
     * @throws \InvalidArgumentException
     */
    public function addItem(Order $cart, Product $product, int $quantity = 1): OrderItem
    {
        if (! $product->isActive()) {
            throw new InvalidArgumentException('El producto no se encuentra disponible.');
        }

        if ($quantity <= 0) {
            throw new InvalidArgumentException('La cantidad debe ser mayor a 0.');
        }

        /** @var \App\Models\OrderItem|null $existingItem */
        $existingItem = $cart->items()->where('product_id', $product->id)->first();
        $currentQuantityInCart = $existingItem ? $existingItem->quantity : 0;
        $requestedTotalQuantity = $currentQuantityInCart + $quantity;

        if ($requestedTotalQuantity > $product->stock) {
            throw new InvalidArgumentException('No hay suficiente stock disponible para este producto.');
        }

        return DB::transaction(function () use ($cart, $product, $existingItem, $requestedTotalQuantity, $quantity) {
            if ($existingItem) {
                $existingItem->quantity = $requestedTotalQuantity;
                $existingItem->unit_price = $product->price;
                $existingItem->subtotal = bcmul((string) $product->price, (string) $requestedTotalQuantity, 2);
                $existingItem->save();
                $item = $existingItem;
            } else {
                $subtotal = bcmul((string) $product->price, (string) $quantity, 2);
                $item = $cart->items()->create([
                    'product_id' => $product->id,
                    'product_name' => $product->name,
                    'unit_price' => $product->price,
                    'quantity' => $quantity,
                    'subtotal' => $subtotal,
                ]);
            }

            $cart->recalculateTotal();

            return $item;
        });
    }

    /**
     * Update the quantity of an item in the cart.
     *
     * @param  \App\Models\Order  $cart
     * @param  int  $itemId
     * @param  int  $quantity
     * @return \App\Models\OrderItem|null
     *
     * @throws \InvalidArgumentException
     */
    public function updateItemQuantity(Order $cart, int $itemId, int $quantity): ?OrderItem
    {
        /** @var \App\Models\OrderItem $item */
        $item = $cart->items()->where('id', $itemId)->firstOrFail();

        if ($quantity <= 0) {
            $item->delete();
            $cart->recalculateTotal();
            return null;
        }

        if ($quantity > $item->product->stock) {
            throw new InvalidArgumentException('No hay suficiente stock disponible para este producto.');
        }

        return DB::transaction(function () use ($cart, $item, $quantity) {
            $item->quantity = $quantity;
            $item->subtotal = bcmul((string) $item->unit_price, (string) $quantity, 2);
            $item->save();

            $cart->recalculateTotal();

            return $item;
        });
    }

    /**
     * Remove a single item from the cart.
     *
     * @param  \App\Models\Order  $cart
     * @param  int  $itemId
     * @return bool
     */
    public function removeItem(Order $cart, int $itemId): bool
    {
        $item = $cart->items()->where('id', $itemId)->first();

        if (! $item) {
            return false;
        }

        $item->delete();
        $cart->recalculateTotal();

        return true;
    }

    /**
     * Clear all items from the cart.
     *
     * @param  \App\Models\Order  $cart
     * @return void
     */
    public function clearCart(Order $cart): void
    {
        $cart->items()->delete();
        $cart->recalculateTotal();
    }

    /**
     * Confirm the order and transition its status from cart to pending payment.
     *
     * @param  \App\Models\Order  $cart
     * @param  array<string, mixed>  $customerData
     * @return \App\Models\Order
     *
     * @throws \InvalidArgumentException
     */
    public function confirmOrder(Order $cart, array $customerData): Order
    {
        if (empty($cart->user_identification)) {
            throw new InvalidArgumentException('Debe identificarse para confirmar la compra.');
        }

        $items = $cart->items()->with('product')->get();

        if ($items->isEmpty()) {
            throw new InvalidArgumentException('El carrito se encuentra vacío.');
        }

        // Validate stock availability for all items before confirmation
        foreach ($items as $item) {
            if ($item->product->stock < $item->quantity) {
                throw new InvalidArgumentException("Stock insuficiente para el producto: {$item->product_name}.");
            }
        }

        return DB::transaction(function () use ($cart, $items, $customerData) {
            // Recalculate totals and freeze prices
            $cart->recalculateTotal();

            $cart->update([
                'status' => Order::STATUS_PENDING_PAYMENT,
                'customer_name' => $customerData['customer_name'] ?? $cart->customer_name,
                'customer_email' => $customerData['customer_email'] ?? $cart->customer_email,
                'customer_phone' => $customerData['customer_phone'] ?? $cart->customer_phone,
                'customer_address' => $customerData['customer_address'] ?? $cart->customer_address,
            ]);

            return $cart->fresh(['items']);
        });
    }

    /**
     * Migrate guest session cart to the authenticated user upon login.
     *
     * @param  \App\Models\User  $user
     * @param  string|null  $previousSessionId
     * @param  string|null  $newSessionId
     * @return \App\Models\Order|null
     */
    public function migrateGuestCart(User $user, ?string $previousSessionId, ?string $newSessionId = null): ?Order
    {
        if (! $previousSessionId) {
            return null;
        }

        /** @var \App\Models\Order|null $sessionCart */
        $sessionCart = Order::where('session_id', $previousSessionId)
            ->whereNull('user_identification')
            ->where('status', Order::STATUS_IN_CART)
            ->first();

        if (! $sessionCart) {
            return null;
        }

        /** @var \App\Models\Order|null $userCart */
        $userCart = Order::where('user_identification', $user->identification)
            ->where('status', Order::STATUS_IN_CART)
            ->first();

        if (! $userCart) {
            $sessionCart->update([
                'user_identification' => $user->identification,
                'session_id' => $newSessionId,
            ]);

            return $sessionCart->fresh();
        }

        return DB::transaction(function () use ($userCart, $sessionCart, $newSessionId) {
            foreach ($sessionCart->items as $sessionItem) {
                /** @var \App\Models\OrderItem|null $existingItem */
                $existingItem = $userCart->items()->where('product_id', $sessionItem->product_id)->first();

                if ($existingItem) {
                    $newQty = $existingItem->quantity + $sessionItem->quantity;
                    $maxStock = $sessionItem->product->stock;
                    $cappedQty = min($newQty, $maxStock);

                    $existingItem->update([
                        'quantity' => $cappedQty,
                        'subtotal' => bcmul((string) $existingItem->unit_price, (string) $cappedQty, 2),
                    ]);
                } else {
                    $userCart->items()->create([
                        'product_id' => $sessionItem->product_id,
                        'product_name' => $sessionItem->product_name,
                        'unit_price' => $sessionItem->unit_price,
                        'quantity' => $sessionItem->quantity,
                        'subtotal' => $sessionItem->subtotal,
                    ]);
                }
            }

            if ($newSessionId) {
                $userCart->update(['session_id' => $newSessionId]);
            }

            $userCart->recalculateTotal();

            $sessionCart->items()->delete();
            $sessionCart->delete();

            return $userCart->fresh(['items']);
        });
    }
}
