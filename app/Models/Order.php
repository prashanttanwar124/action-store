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
        'refunded_total',
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
        'refunded_total' => 'decimal:2',
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
     * @return HasMany<RewardPoint, $this>
     */
    public function rewardPoints(): HasMany
    {
        return $this->hasMany(RewardPoint::class);
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

        static::saved(function (Order $order) {
            $order->syncRewardPoints();
        });

        static::deleting(function (Order $order) {
            $userId = $order->user_id;
            if (! empty($order->id)) {
                RewardPoint::where('order_id', $order->id)->delete();
            }
            if ($userId && ($user = User::find($userId))) {
                $user->refreshRewardPointsBalance();
            }
        });
    }

    /**
     * Synchronize reward points ledger entries and the customer's balance for this order.
     */
    public function syncRewardPoints(): void
    {
        if (! $this->exists || empty($this->id)) {
            return;
        }

        $currentUserId = $this->user_id ? (int) $this->user_id : null;

        // Clean up any ledger records belonging to previous or different users for this order
        $foreignOrStaleLedgers = RewardPoint::where('order_id', $this->id)
            ->when($currentUserId, fn ($q) => $q->where('user_id', '!=', $currentUserId))
            ->get();

        if ($foreignOrStaleLedgers->isNotEmpty()) {
            $affectedUserIds = $foreignOrStaleLedgers->pluck('user_id')->unique();
            RewardPoint::where('order_id', $this->id)
                ->when($currentUserId, fn ($q) => $q->where('user_id', '!=', $currentUserId))
                ->delete();

            foreach ($affectedUserIds as $affectedId) {
                if ($affectedUser = User::find($affectedId)) {
                    $affectedUser->refreshRewardPointsBalance();
                }
            }
        }

        if (! $currentUserId) {
            return;
        }

        $user = $this->relationLoaded('user') && $this->user ? $this->user : User::find($currentUserId);
        if (! $user) {
            return;
        }

        $targetPoints = $this->status === 'cancelled'
            ? 0
            : (int) floor(max(0, (float) $this->total - (float) ($this->refunded_total ?? 0)));

        $currentPoints = (int) RewardPoint::where('order_id', $this->id)->where('user_id', $user->id)->sum('points');
        $adjustment = $targetPoints - $currentPoints;

        if ($adjustment !== 0) {
            $description = match (true) {
                $this->status === 'cancelled' => "Cancelled order {$this->order_number}",
                $adjustment < 0 => "Refund adjustment for order {$this->order_number}",
                $currentPoints === 0 => "Points earned for order {$this->order_number}",
                default => "Points adjustment for order {$this->order_number}",
            };

            RewardPoint::create([
                'user_id' => $user->id,
                'order_id' => $this->id,
                'points' => $adjustment,
                'description' => $description,
            ]);

            $user->refreshRewardPointsBalance();
        }
    }

    /**
     * Count the placed (not cancelled) orders in a pickup window, given as "Y-m-d · <slot label>".
     *
     * Matched exactly: a slot label may contain LIKE wildcards or be the start of another label.
     */
    public static function bookedInSlot(string $capacitySlot): int
    {
        return static::query()
            ->where('pickup_slot', $capacitySlot)
            ->where('status', '!=', 'cancelled')
            ->count();
    }

    /**
     * Generate the next order number for today, e.g. #MM-20261004-1001-A3F9.
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
            return array_filter($this->reserved_stock, fn ($qty) => (int) $qty > 0);
        }

        return $this->items()
            ->whereNotNull('product_id')
            ->get()
            ->groupBy('product_id')
            ->map(fn ($items) => (int) $items->sum(fn ($i) => max(0, (int) $i->quantity - (int) $i->refunded_quantity)))
            ->filter(fn ($qty) => $qty > 0)
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
