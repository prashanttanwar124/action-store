<?php

namespace App\Models;

use App\Events\OrderPlaced;
use App\Exceptions\InsufficientStockException;
use App\Exceptions\SlotCapacityExceededException;
use Database\Factories\CheckoutFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * A payment attempt. It reserves stock and holds the cart until it is paid (and turned into
 * an order) or released. Orders only ever exist for paid checkouts.
 */
class Checkout extends Model
{
    /** @use HasFactory<CheckoutFactory> */
    use HasFactory;

    /**
     * Minutes of inactivity after which an unpaid checkout is released. Each "Pay" click restarts the timer.
     */
    public const LIFETIME_MINUTES = 15;

    /**
     * @var list<string>
     */
    protected $fillable = [
        'user_id',
        'fingerprint',
        'stripe_payment_id',
        'customer_name',
        'customer_email',
        'customer_phone',
        'subtotal',
        'delivery_fee',
        'total',
        'payment_method',
        'fulfillment_type',
        'pickup_slot',
        'capacity_slot',
        'pickup_location',
        'delivery_address',
        'notes',
        'items',
        'reserved_stock',
        'expires_at',
    ];

    protected function casts(): array
    {
        return [
            'subtotal' => 'decimal:2',
            'delivery_fee' => 'decimal:2',
            'total' => 'decimal:2',
            'items' => 'array',
            'reserved_stock' => 'array',
            'expires_at' => 'datetime',
        ];
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Checkouts still holding stock and a pickup slot.
     *
     * @param  Builder<Checkout>  $query
     */
    public function scopeActive(Builder $query): void
    {
        $query->where('expires_at', '>', now());
    }

    /**
     * Checkouts whose customer has been inactive for longer than the lifetime.
     *
     * @param  Builder<Checkout>  $query
     */
    public function scopeExpired(Builder $query): void
    {
        $query->where('expires_at', '<=', now());
    }

    /**
     * Restart the inactivity timer, e.g. when the customer retries the payment.
     */
    public function extendLifetime(): void
    {
        $this->update(['expires_at' => now()->addMinutes(self::LIFETIME_MINUTES)]);
    }

    /**
     * A stable hash of everything that defines what the customer is buying and how they get it.
     * The same hash on a later "Pay" click means the customer is retrying the same checkout.
     *
     * @param  array<string, mixed>  $validated
     */
    public static function fingerprintFor(array $validated): string
    {
        $items = array_map(fn (array $item) => [
            'id' => (string) $item['id'],
            'type' => $item['type'] ?? 'product',
            'quantity' => (int) $item['quantity'],
            'is_subscribed' => ! empty($item['is_subscribed']),
        ], $validated['items']);

        $fields = [
            'payment_method', 'fulfillment_type', 'pickup_timing_mode', 'pickup_timing_type', 'pickup_date',
            'pickup_time', 'pickup_slot', 'pickup_location', 'delivery_address', 'customer_phone', 'notes',
        ];

        $details = [];
        foreach ($fields as $field) {
            $details[$field] = $validated[$field] ?? null;
        }

        return hash('sha256', json_encode([$items, $details]));
    }

    /**
     * Turn this paid checkout into an order, exactly once.
     *
     * The checkout row is locked, so when the browser and the webhook race, only one creates
     * the order and the other receives that same order.
     */
    public function convertToOrder(?string $paymentIntentId = null): ?Order
    {
        $paymentIntentId ??= $this->stripe_payment_id;

        $order = DB::transaction(function () use ($paymentIntentId) {
            $locked = static::whereKey($this->id)->lockForUpdate()->first();
            if (! $locked) {
                return null;
            }

            // Deduct stock upon successful payment confirmation
            $reservedStock = $locked->reserved_stock;
            $productsToDecrement = [];

            if (is_array($reservedStock) && ! empty($reservedStock)) {
                // Batch-load and lock products in consistent ascending ID order to eliminate deadlock risk
                $productIds = array_map('intval', array_keys($reservedStock));
                sort($productIds);

                $products = Product::whereIn('id', $productIds)
                    ->orderBy('id')
                    ->lockForUpdate()
                    ->get()
                    ->keyBy('id');

                foreach ($reservedStock as $productId => $qty) {
                    $requiredQty = (int) $qty;
                    if ($requiredQty <= 0) {
                        continue;
                    }

                    $product = $products->get((int) $productId);
                    if (! $product) {
                        throw new InsufficientStockException(
                            'One or more products could not be located.',
                            (int) $productId
                        );
                    }

                    $currentStock = (int) ($product->stock ?? 0);
                    if ($currentStock < $requiredQty) {
                        throw new InsufficientStockException(
                            "Insufficient stock for '{$product->name}'. Only {$currentStock} available.",
                            (int) $productId
                        );
                    }

                    $productsToDecrement[] = ['product' => $product, 'qty' => $requiredQty];
                }
            }

            // Check pickup slot capacity inside transaction to prevent overbooking races
            if (! empty($locked->capacity_slot)) {
                $storeInfo = StoreSetting::current();
                $maxCapacity = (int) ($storeInfo->max_orders_per_slot ?? 0);
                if ($maxCapacity > 0) {
                    if ($storeInfo->id) {
                        StoreSetting::whereKey($storeInfo->id)->lockForUpdate()->first();
                    }

                    $bookedCount = Order::query()
                        ->where('pickup_slot', 'LIKE', "{$locked->capacity_slot}%")
                        ->where('status', '!=', 'cancelled')
                        ->count();

                    if ($bookedCount >= $maxCapacity) {
                        throw new SlotCapacityExceededException(
                            "The pickup window ({$locked->capacity_slot}) is no longer available as capacity was reached.",
                            $locked->capacity_slot
                        );
                    }
                }
            }

            foreach ($productsToDecrement as $item) {
                $item['product']->decrementStock($item['qty']);
            }

            $order = Order::create([
                'order_number' => Order::generateOrderNumber(),
                'user_id' => $locked->user_id,
                'customer_name' => $locked->customer_name,
                'customer_email' => $locked->customer_email,
                'customer_phone' => $locked->customer_phone,
                'subtotal' => $locked->subtotal,
                'discount' => 0.00,
                'delivery_fee' => $locked->delivery_fee,
                'total' => $locked->total,
                'points_earned' => (int) floor((float) $locked->subtotal),
                'payment_method' => $locked->payment_method,
                'stripe_payment_id' => $paymentIntentId,
                'fulfillment_type' => $locked->fulfillment_type,
                'pickup_slot' => $locked->pickup_slot,
                'pickup_location' => $locked->pickup_location,
                'delivery_address' => $locked->delivery_address,
                'status' => 'confirmed',
                'notes' => $locked->notes,
                'reserved_stock' => $locked->reserved_stock,
            ]);

            $order->items()->createMany($locked->items);
            $locked->delete();

            return $order;
        });

        if (! $order) {
            // A concurrent request already converted this checkout
            return $paymentIntentId ? Order::where('stripe_payment_id', $paymentIntentId)->first() : null;
        }

        try {
            OrderPlaced::dispatch($order->load(['items', 'user']));
        } catch (\Throwable $e) {
            Log::warning("OrderPlaced dispatch failed for {$order->order_number}: {$e->getMessage()}");
        }

        return $order;
    }

    /**
     * Delete this unpaid checkout.
     *
     * Only call this once Stripe can no longer take payment for it (see StripePayments::releaseCheckout).
     *
     * @return bool Whether this call released the checkout (false if it was already gone).
     */
    public function release(): bool
    {
        return DB::transaction(function () {
            $locked = static::whereKey($this->id)->lockForUpdate()->first();
            if (! $locked) {
                return false;
            }

            $locked->delete();

            return true;
        });
    }
}
