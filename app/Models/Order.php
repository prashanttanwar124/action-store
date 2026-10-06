<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Facades\DB;

class Order extends Model
{
    use HasFactory;

    /**
     * @var list<string>
     */
    protected $fillable = [
        'order_number',
        'user_id',
        'customer_name',
        'customer_email',
        'customer_phone',
        'subtotal',
        'discount',
        'delivery_fee',
        'total',
        'points_earned',
        'payment_method',
        'stripe_payment_id',
        'fulfillment_type',
        'pickup_slot',
        'pickup_location',
        'delivery_address',
        'status',
        'notes',
        'reserved_stock',
    ];

    /**
     * @var array<string, string>
     */
    protected $casts = [
        'subtotal' => 'decimal:2',
        'discount' => 'decimal:2',
        'delivery_fee' => 'decimal:2',
        'total' => 'decimal:2',
        'points_earned' => 'integer',
        'reserved_stock' => 'array',
    ];

    /**
     * @return HasMany<OrderItem, $this>
     */
    public function items(): HasMany
    {
        return $this->hasMany(OrderItem::class);
    }

    /**
     * The "booted" method of the model.
     */
    protected static function booted(): void
    {
        static::creating(function (Order $order) {
            if (empty($order->order_number)) {
                $order->order_number = static::generateOrderNumber();
            }
        });
    }

    /**
     * Generate an enterprise, collision-free, chronological order number.
     * Format: #MM-YYYYMMDD-XXXX (e.g. #MM-20261004-1001)
     */
    public static function generateOrderNumber(): string
    {
        $datePrefix = date('Ymd');
        $prefix = "#MM-{$datePrefix}-";

        // Query the latest order number created today
        $latestOrderNumber = static::where('order_number', 'like', "{$prefix}%")
            ->orderByDesc('id')
            ->value('order_number');

        if ($latestOrderNumber && preg_match('/#MM-\d{8}-(\d+)/', $latestOrderNumber, $matches)) {
            $nextSequence = (int) $matches[1] + 1;
        } else {
            $nextSequence = 1001;
        }

        $entropy = strtoupper(substr(bin2hex(random_bytes(2)), 0, 4));

        return "{$prefix}{$nextSequence}-{$entropy}";
    }

    /**
     * Cancel this order and return its reserved stock.
     *
     * The row is locked and its status re-read, so concurrent callers (admin, refund webhook)
     * restock only once.
     *
     * @return bool Whether this call cancelled the order.
     */
    public function cancelAndRestock(?string $reason = null): bool
    {
        $cancelled = DB::transaction(function () use ($reason) {
            $locked = static::whereKey($this->id)->lockForUpdate()->first();
            if (! $locked || $locked->status === 'cancelled') {
                return false;
            }

            Product::returnToStock($locked->reservedStockQuantities());

            $noteSuffix = $reason ? " [Cancelled: {$reason}]" : ' [Cancelled]';
            $locked->update([
                'status' => 'cancelled',
                'notes' => trim(($locked->notes ?? '').$noteSuffix),
            ]);

            return true;
        });

        $this->refresh();

        return $cancelled;
    }

    /**
     * The product quantities this order took from stock.
     *
     * Orders placed before reserved stock was recorded fall back to their product line items.
     *
     * @return array<int, int>
     */
    public function reservedStockQuantities(): array
    {
        if (! empty($this->reserved_stock)) {
            return $this->reserved_stock;
        }

        return $this->items()
            ->whereNotNull('product_id')
            ->get()
            ->groupBy('product_id')
            ->map(fn ($items) => (int) $items->sum('quantity'))
            ->all();
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
