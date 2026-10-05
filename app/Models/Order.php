<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

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
        'idempotency_key',
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
     * Cancel this order and restore product inventory.
     */
    public function cancelAndRestock(?string $reason = null): void
    {
        DB::transaction(function () use ($reason) {
            $this->loadMissing('items.product');

            foreach ($this->items as $item) {
                if ($item->product_id && $item->product) {
                    $item->product->increment('stock', (int) $item->quantity);
                }
            }

            $noteSuffix = $reason ? " [Cancelled: {$reason}]" : ' [Cancelled]';
            $this->update([
                'status' => 'cancelled',
                'notes' => trim(($this->notes ?? '').$noteSuffix),
            ]);
        });
    }

    /**
     * Cancel all abandoned orders that have been in pending_payment for more than the specified minutes.
     */
    public static function cancelExpiredPendingOrders(int $minutes = 15): int
    {
        $expiredOrders = static::where('status', 'pending_payment')
            ->where('created_at', '<', now()->subMinutes($minutes))
            ->get();

        $count = 0;
        foreach ($expiredOrders as $order) {
            try {
                $order->cancelAndRestock("Payment timeout exceeded ({$minutes} mins)");
                $count++;
            } catch (\Throwable $e) {
                Log::error("Failed to cancel expired pending order #{$order->order_number}: {$e->getMessage()}");
            }
        }

        return $count;
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
