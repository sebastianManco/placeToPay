<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Str;

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
        ], true);
    }

    /**
     * Determine whether the order allows a payment retry.
     * Only non-satisfactory orders eligible for payment can be retried.
     *
     * @return bool
     */
    public function canRetryPayment(): bool
    {
        return $this->canBePaid() && ! $this->isApproved();
    }

    /**
     * Transition order to approved status.
     *
     * @return void
     */
    public function markAsApproved(): void
    {
        $this->update(['status' => self::STATUS_APPROVED]);
    }

    /**
     * Transition order to rejected status.
     *
     * @return void
     */
    public function markAsRejected(): void
    {
        $this->update(['status' => self::STATUS_REJECTED]);
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
     * @return void
     */
    public function updateStatusFromGateway(string $gatewayStatus): void
    {
        $normalized = strtoupper(trim($gatewayStatus));

        match ($normalized) {
            'APPROVED' => $this->markAsApproved(),
            'REJECTED', 'FAILED', 'PARTIAL_EXPIRED' => $this->markAsRejected(),
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
