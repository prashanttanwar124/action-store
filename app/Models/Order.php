<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

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

        $candidate = "{$prefix}{$nextSequence}";

        if (static::where('order_number', $candidate)->exists()) {
            $entropy = strtoupper(substr(bin2hex(random_bytes(2)), 0, 4));
            $candidate = "{$prefix}{$nextSequence}-{$entropy}";
        }

        return $candidate;
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
