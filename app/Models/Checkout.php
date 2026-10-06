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
 * A payment attempt. It holds the priced cart until it is paid (and turned into an order) or
 * released. Stock and slot capacity are not reserved: both are checked again when the payment
 * succeeds, and the payment is refunded if either has run out. Orders only ever exist for paid checkouts.
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
     * Values of fulfillment_failure: why a paid checkout could not become an order.
     */
    public const FAILURE_OUT_OF_STOCK = 'out_of_stock';

    public const FAILURE_SLOT_FULL = 'slot_full';

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
        'fulfillment_failure',
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
     * Checkouts whose customer is still within the inactivity lifetime.
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

        usort($items, fn ($a, $b) => strcmp($a['id'].':'.$a['type'], $b['id'].':'.$b['type']));

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
     * The fulfillment failure recorded on this checkout, if any.
     */
    public function fulfillmentFailure(): ?InsufficientStockException
    {
        return match ($this->fulfillment_failure) {
            self::FAILURE_SLOT_FULL => new SlotCapacityExceededException(slot: $this->capacity_slot),
            self::FAILURE_OUT_OF_STOCK => new InsufficientStockException,
            default => null,
        };
    }

    /**
     * Turn this paid checkout into an order, exactly once.
     *
     * The checkout row is locked, so when the browser and the webhook race, only one creates
     * the order and the other receives that same order. If stock or slot capacity has run out,
     * the failure is saved on the checkout before the exception is thrown, so every later
     * attempt only refunds it, even once stock or capacity comes back.
     *
     * @throws InsufficientStockException
     */
    public function convertToOrder(?string $paymentIntentId = null): ?Order
    {
        $paymentIntentId ??= $this->stripe_payment_id;
        $failure = null;

        $order = DB::transaction(function () use ($paymentIntentId, &$failure) {
            $locked = static::whereKey($this->id)->lockForUpdate()->first();
            if (! $locked) {
                return null;
            }

            if ($failure = $locked->fulfillmentFailure()) {
                return null;
            }

            try {
                $productsToDecrement = $locked->lockProductsToDecrement();
                $locked->assertSlotHasCapacity();
            } catch (InsufficientStockException $e) {
                // Committed before any refund is attempted, so a racing request cannot fulfil it meanwhile
                $locked->update([
                    'fulfillment_failure' => $e instanceof SlotCapacityExceededException ? self::FAILURE_SLOT_FULL : self::FAILURE_OUT_OF_STOCK,
                ]);
                $failure = $e;

                return null;
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

        if ($failure) {
            throw $failure;
        }

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
     * Lock this checkout's products and confirm there is enough stock for all of them.
     * Call inside a database transaction.
     *
     * @return list<array{product: Product, qty: int}>
     *
     * @throws InsufficientStockException
     */
    private function lockProductsToDecrement(): array
    {
        $reservedStock = $this->reserved_stock;
        $productsToDecrement = [];

        if (is_array($reservedStock) && ! empty($reservedStock)) {
            // Lock products in ascending id order, so concurrent conversions lock them in the same order
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

        return $productsToDecrement;
    }

    /**
     * Confirm the chosen pickup window still has room. Call inside a database transaction:
     * the store settings row is locked so concurrent conversions count placed orders one at a time.
     *
     * @throws SlotCapacityExceededException
     */
    private function assertSlotHasCapacity(): void
    {
        if (empty($this->capacity_slot)) {
            return;
        }

        $storeInfo = StoreSetting::current();
        $maxCapacity = (int) ($storeInfo->max_orders_per_slot ?? 0);
        if ($maxCapacity <= 0) {
            return;
        }

        if ($storeInfo->id) {
            StoreSetting::whereKey($storeInfo->id)->lockForUpdate()->first();
        }

        if (Order::bookedInSlot($this->capacity_slot) >= $maxCapacity) {
            throw new SlotCapacityExceededException(
                "The pickup window ({$this->capacity_slot}) is no longer available as capacity was reached.",
                $this->capacity_slot
            );
        }
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
