<?php

namespace App\Models;

use App\Contracts\PaymentGatewayInterface;
use App\Notifications\PaymentApprovedStockShortageNotification;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Str;
use RuntimeException;
use Throwable;

/**
 * Class Order
 *
 * Represents a customer order or active shopping cart.
 *
 * @property int $id
 * @property string $reference
 * @property int|null $user_identification
 * @property string|null $session_id
 * @property string|null $request_id
 * @property string|null $process_url
 * @property string $status
 * @property float $total_amount
 * @property string $currency
 * @property string|null $customer_name
 * @property string|null $customer_email
 * @property string|null $customer_phone
 * @property string|null $customer_address
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 * @property-read \Illuminate\Database\Eloquent\Collection<int, \App\Models\OrderItem> $items
 * @property-read \App\Models\User|null $user
 */
class Order extends Model
{
    use HasFactory;

    /**
     * Order status constants.
     */
    public const STATUS_IN_CART = 'in_cart';
    public const STATUS_PENDING_PAYMENT = 'pending_payment';
    public const STATUS_APPROVED = 'approved';
    public const STATUS_REJECTED = 'rejected';
    public const STATUS_CANCELLED = 'cancelled';
    public const STATUS_REFUND_PENDING = 'refund_pending';
    public const STATUS_REVERSED = 'reversed';

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'reference',
        'user_identification',
        'session_id',
        'request_id',
        'process_url',
        'status',
        'stock_reserved',
        'stock_reserved_at',
        'total_amount',
        'currency',
        'customer_name',
        'customer_email',
        'customer_phone',
        'customer_address',
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'total_amount' => 'decimal:2',
            'user_identification' => 'integer',
            'stock_reserved' => 'boolean',
            'stock_reserved_at' => 'datetime',
        ];
    }

    /**
     * Relationship with the items contained in the order.
     *
     * @return \Illuminate\Database\Eloquent\Relations\HasMany<\App\Models\OrderItem, $this>
     */
    public function items(): HasMany
    {
        return $this->hasMany(OrderItem::class, 'order_id');
    }

    /**
     * Relationship with the user that owns the order.
     *
     * @return \Illuminate\Database\Eloquent\Relations\BelongsTo<\App\Models\User, $this>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_identification', 'identification');
    }

    /**
     * Determine if the order is still editable (cart state).
     *
     * @return bool
     */
    public function isEditable(): bool
    {
        return $this->status === self::STATUS_IN_CART;
    }

    /**
     * Calculate and update the total amount based on the sum of item subtotals.
     *
     * @return void
     */
    public function recalculateTotal(): void
    {
        $total = $this->items()->sum('subtotal');
        $this->update(['total_amount' => $total]);
    }

    /**
     * Get the total quantity of items in the order.
     *
     * @return int
     */
    public function getTotalQuantity(): int
    {
        return (int) $this->items()->sum('quantity');
    }

    /**
     * Check if the order is currently approved.
     *
     * @return bool
     */
    public function isApproved(): bool
    {
        return $this->status === self::STATUS_APPROVED;
    }

    /**
     * Check if the order is currently rejected.
     *
     * @return bool
     */
    public function isRejected(): bool
    {
        return $this->status === self::STATUS_REJECTED;
    }

    /**
     * Check if the order is currently pending payment.
     *
     * @return bool
     */
    public function isPendingPayment(): bool
    {
        return $this->status === self::STATUS_PENDING_PAYMENT;
    }

    /**
     * Check if the order is currently in refund pending status.
     *
     * @return bool
     */
    public function isRefundPending(): bool
    {
        return $this->status === self::STATUS_REFUND_PENDING;
    }

    /**
     * Check if the order was reversed.
     *
     * @return bool
     */
    public function isReversed(): bool
    {
        return $this->status === self::STATUS_REVERSED;
    }

    /**
     * Determine whether the order can initiate or retry a payment.
     *
     * @return bool
     */
    public function canBePaid(): bool
    {
        return in_array($this->status, [self::STATUS_PENDING_PAYMENT, self::STATUS_REJECTED], true);
    }

    /**
     * Determine whether the order was completed satisfactorily (approved).
     *
     * @return bool
     */
    public function isSatisfactory(): bool
    {
        return $this->isApproved();
    }

    /**
     * Determine whether the order was not satisfactory (rejected, pending payment, or cancelled).
     *
     * @return bool
     */
    public function isNotSatisfactory(): bool
    {
        return in_array($this->status, [
            self::STATUS_REJECTED,
            self::STATUS_PENDING_PAYMENT,
            self::STATUS_CANCELLED,
            self::STATUS_REFUND_PENDING,
            self::STATUS_REVERSED,
        ], true);
    }

    /**
     * Determine whether the order allows a payment retry.
     * Only non-satisfactory orders eligible for payment can be retried.
     * Orders with captured payment (refund_pending or reversed) cannot be retried.
     *
     * @return bool
     */
    public function canRetryPayment(): bool
    {
        return $this->canBePaid() && ! $this->isApproved() && ! $this->isRefundPending() && ! $this->isReversed();
    }

    /**
     * Reserve inventory stock for the order items with pessimistic locking.
     * Prevents race conditions and overselling while payment is being processed.
     *
     * @return void
     *
     * @throws \RuntimeException
     */
    public function reserveStock(): void
    {
        if ($this->stock_reserved) {
            return;
        }

        DB::transaction(function () {
            $items = $this->items()->get();
            $quantitiesByProduct = [];

            foreach ($items as $item) {
                if ($item->product_id) {
                    $quantitiesByProduct[$item->product_id] = ($quantitiesByProduct[$item->product_id] ?? 0) + $item->quantity;
                }
            }

            ksort($quantitiesByProduct);

            $lockedProducts = [];
            foreach ($quantitiesByProduct as $productId => $requiredQuantity) {
                /** @var Product|null $product */
                $product = Product::where('id', $productId)->lockForUpdate()->first();

                if (! $product || $product->stock < $requiredQuantity) {
                    $productName = $product ? $product->name : "ID {$productId}";
                    $available = $product ? $product->stock : 0;
                    throw new RuntimeException("Stock insuficiente para el producto '{$productName}'. Requerido: {$requiredQuantity}, Disponible: {$available}.");
                }

                $lockedProducts[] = [
                    'model' => $product,
                    'quantity' => $requiredQuantity,
                ];
            }

            foreach ($lockedProducts as $entry) {
                $entry['model']->decrement('stock', $entry['quantity']);
            }

            $this->update([
                'stock_reserved' => true,
                'stock_reserved_at' => now(),
            ]);
        });
    }

    /**
     * Release previously reserved stock back to inventory.
     *
     * @return void
     */
    public function releaseReservedStock(): void
    {
        if (! $this->stock_reserved) {
            return;
        }

        DB::transaction(function () {
            $items = $this->items()->get();
            $quantitiesByProduct = [];

            foreach ($items as $item) {
                if ($item->product_id) {
                    $quantitiesByProduct[$item->product_id] = ($quantitiesByProduct[$item->product_id] ?? 0) + $item->quantity;
                }
            }

            ksort($quantitiesByProduct);

            foreach ($quantitiesByProduct as $productId => $quantity) {
                $product = Product::where('id', $productId)->lockForUpdate()->first();
                if ($product) {
                    $product->increment('stock', $quantity);
                }
            }

            $this->update([
                'stock_reserved' => false,
                'stock_reserved_at' => null,
            ]);
        });
    }

    /**
     * Transition order to approved status and finalize inventory stock.
     * If stock was already reserved, secures approval and clears reservation flag.
     * If stock was not reserved, attempts deduction under pessimistic lock.
     * If stock deduction fails despite payment being captured by gateway, NEVER marks
     * as rejected: attempts automated reversal, transitions to refund_pending or reversed,
     * and alerts support immediately.
     *
     * @param  bool  $throwOnError
     * @param  array<string, mixed>|null  $gatewayPaymentData
     * @return bool Returns true if approved, false if contingency occurred.
     *
     * @throws \Throwable
     */
    public function markAsApproved(bool $throwOnError = false, ?array $gatewayPaymentData = null): bool
    {
        if ($this->status === self::STATUS_APPROVED) {
            return true;
        }

        // If stock is already reserved, it was already deducted when entering payment.
        if ($this->stock_reserved) {
            $this->update([
                'status' => self::STATUS_APPROVED,
                'stock_reserved' => false,
                'stock_reserved_at' => null,
            ]);

            return true;
        }

        // Fallback: stock was not pre-reserved, attempt pessimistic lock deduction
        $shortageDetails = [];
        try {
            DB::transaction(function () use (&$shortageDetails) {
                $items = $this->items()->get();
                $quantitiesByProduct = [];

                foreach ($items as $item) {
                    if ($item->product_id) {
                        $quantitiesByProduct[$item->product_id] = ($quantitiesByProduct[$item->product_id] ?? 0) + $item->quantity;
                    }
                }

                ksort($quantitiesByProduct);

                $lockedProducts = [];
                foreach ($quantitiesByProduct as $productId => $requiredQuantity) {
                    /** @var Product|null $product */
                    $product = Product::where('id', $productId)->lockForUpdate()->first();

                    if (! $product || $product->stock < $requiredQuantity) {
                        $productName = $product ? $product->name : "ID {$productId}";
                        $available = $product ? $product->stock : 0;
                        $shortageDetails[] = [
                            'product_id' => $productId,
                            'name' => $productName,
                            'required' => $requiredQuantity,
                            'available' => $available,
                        ];
                        throw new RuntimeException("Stock insuficiente para el producto '{$productName}'. Requerido: {$requiredQuantity}, Disponible: {$available}.");
                    }

                    $lockedProducts[] = [
                        'model' => $product,
                        'quantity' => $requiredQuantity,
                    ];
                }

                foreach ($lockedProducts as $entry) {
                    $entry['model']->decrement('stock', $entry['quantity']);
                }

                $this->update(['status' => self::STATUS_APPROVED]);
            });

            return true;
        } catch (Throwable $e) {
            return $this->handleStockShortageOnApprovedPayment($shortageDetails, $gatewayPaymentData, $e, $throwOnError);
        }
    }

    /**
     * Handle critical contingency where PlaceToPay approved the payment but inventory is exhausted.
     * Attempts automated reversal, records critical incident, notifies support, and transitions order.
     *
     * @param  array<int, array<string, mixed>>  $shortageDetails
     * @param  array<string, mixed>|null  $gatewayPaymentData
     * @param  \Throwable  $exception
     * @param  bool  $throwOnError
     * @return bool
     *
     * @throws \Throwable
     */
    protected function handleStockShortageOnApprovedPayment(
        array $shortageDetails,
        ?array $gatewayPaymentData,
        Throwable $exception,
        bool $throwOnError = false
    ): bool {
        Log::critical("INCIDENTE CRÍTICO: Orden #{$this->reference} cobrada/aprobada en PlaceToPay (RequestId: {$this->request_id}) pero falló por falta de stock: {$exception->getMessage()}", [
            'order_id' => $this->id,
            'reference' => $this->reference,
            'request_id' => $this->request_id,
            'total_amount' => $this->total_amount,
            'customer_email' => $this->customer_email,
            'shortage_details' => $shortageDetails,
        ]);

        $reversalResult = null;
        $reversalSuccessful = false;

        // Attempt automated reversal with payment gateway
        try {
            /** @var \App\Contracts\PaymentGatewayInterface $gateway */
            $gateway = app(PaymentGatewayInterface::class);

            $options = [];
            if ($gatewayPaymentData) {
                $payments = $gatewayPaymentData['payment'] ?? [];
                if (! empty($payments) && is_array($payments)) {
                    foreach ($payments as $payment) {
                        if (! empty($payment['internalReference']) && ($payment['status']['status'] ?? '') === 'APPROVED') {
                            $options['internalReference'] = $payment['internalReference'];
                            break;
                        }
                    }
                }
            }

            $reversalResult = $gateway->reversePayment($this, $options);
            $reversalStatus = strtoupper($reversalResult['status']['status'] ?? '');
            if (in_array($reversalStatus, ['APPROVED', 'OK'], true)) {
                $reversalSuccessful = true;
                Log::info("Reversión automática exitosa en PlaceToPay para orden #{$this->reference}: " . json_encode($reversalResult));
            } else {
                Log::warning("Reversión automática fallida o no soportada en PlaceToPay para orden #{$this->reference}: " . json_encode($reversalResult));
            }
        } catch (Throwable $revEx) {
            Log::error("Excepción al intentar reversión automática para orden #{$this->reference}: " . $revEx->getMessage());
        }

        if ($reversalSuccessful) {
            $this->update(['status' => self::STATUS_REVERSED]);
        } else {
            $this->update(['status' => self::STATUS_REFUND_PENDING]);
        }

        // Send urgent notification to admin / support
        try {
            $supportEmail = config('mail.from.address', 'support@mercatodo.com');
            Notification::route('mail', $supportEmail)
                ->notify(new PaymentApprovedStockShortageNotification($this, $shortageDetails, $reversalResult));
        } catch (Throwable $notifEx) {
            Log::error("Error enviando notificación de stockout en pago aprobado para orden #{$this->reference}: " . $notifEx->getMessage());
        }

        if ($throwOnError) {
            throw $exception;
        }

        return false;
    }

    /**
     * Transition order to rejected status and release any active stock reservation.
     *
     * @return void
     */
    public function markAsRejected(): void
    {
        $this->releaseReservedStock();
        $this->update(['status' => self::STATUS_REJECTED]);
    }

    /**
     * Transition order to cancelled status and release any active stock reservation.
     *
     * @return void
     */
    public function markAsCancelled(): void
    {
        $this->releaseReservedStock();
        $this->update(['status' => self::STATUS_CANCELLED]);
    }

    /**
     * Transition order to pending payment status.
     *
     * @return void
     */
    public function markAsPendingPayment(): void
    {
        $this->update(['status' => self::STATUS_PENDING_PAYMENT]);
    }

    /**
     * Update order status mapped from PlaceToPay status code.
     *
     * @param  string  $gatewayStatus
     * @param  array<string, mixed>|null  $gatewayPaymentData
     * @return void
     */
    public function updateStatusFromGateway(string $gatewayStatus, ?array $gatewayPaymentData = null): void
    {
        $normalized = strtoupper(trim($gatewayStatus));

        match ($normalized) {
            'APPROVED' => $this->markAsApproved(false, $gatewayPaymentData),
            'REJECTED', 'FAILED', 'PARTIAL_EXPIRED', 'CANCELLED', 'EXPIRED' => $this->markAsRejected(),
            'PENDING', 'OK' => $this->markAsPendingPayment(),
            default => null,
        };
    }

    /**
     * Generate a unique reference string for the order.
     *
     * @return string
     */
    public static function generateUniqueReference(): string
    {
        do {
            $reference = 'ORD-' . strtoupper(Str::random(10));
        } while (self::where('reference', $reference)->exists());

        return $reference;
    }
}
