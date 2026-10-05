<?php

namespace App\Http\Controllers;

use App\Events\OrderPlaced;
use App\Models\Order;
use App\Models\Product;
use App\Models\RecipeKit;
use App\Models\StoreSetting;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;
use Stripe\Exception\ApiErrorException;
use Stripe\PaymentIntent;
use Stripe\Refund;
use Stripe\Stripe;

class OrderController extends Controller
{
    /**
     * Store a newly created order in storage.
     */
    public function store(Request $request): JsonResponse|RedirectResponse
    {
        $validated = $request->validate([
            'items' => ['required', 'array', 'min:1'],
            'items.*.id' => ['required', 'regex:/^[a-zA-Z0-9_-]+$/'],
            'items.*.type' => ['sometimes', 'in:product,recipe-kit'],
            'items.*.name' => ['required', 'string', 'max:255'],
            'items.*.price' => ['required', 'numeric', 'min:0'],
            'items.*.quantity' => ['required', 'integer', 'min:1'],
            'items.*.size' => ['nullable', 'string', 'max:100'],
            'items.*.weight' => ['nullable', 'string', 'max:100'],
            'items.*.image' => ['nullable', 'string', 'max:500'],
            'items.*.is_subscribed' => ['nullable', 'boolean'],
            'expected_total' => ['nullable', 'numeric', 'min:0'],
            'idempotency_key' => ['nullable', 'string', 'max:64'],
            'payment_method' => ['nullable', 'string', 'in:card,stripe,apple-pay,google-pay'],
            'stripe_payment_id' => ['nullable', 'string', 'max:255'],
            'fulfillment_type' => ['nullable', 'in:Store Pickup,Home Delivery'],
            'pickup_timing_mode' => ['sometimes', 'in:asap,scheduled'],
            'pickup_timing_type' => ['required_if:pickup_timing_mode,scheduled', 'in:slot,custom'],
            'pickup_date' => ['required_if:pickup_timing_mode,scheduled', 'date_format:Y-m-d'],
            'pickup_time' => ['nullable', 'date_format:H:i'],
            'pickup_slot' => ['nullable', 'string', 'max:100'],
            'pickup_location' => ['nullable', 'string', 'max:255'],
            'delivery_address' => ['required_if:fulfillment_type,Home Delivery', 'nullable', 'string', 'max:500'],
            'customer_name' => ['nullable', 'string', 'max:255'],
            'customer_email' => ['nullable', 'email', 'max:255'],
            'customer_phone' => ['nullable', 'string', 'max:50'],
            'notes' => ['nullable', 'string', 'max:1000'],
        ]);

        // Duplicate / concurrent order protection via idempotency key or stripe_payment_id (e.g. webhook won the race)
        $idempotencyKey = $validated['idempotency_key'] ?? $request->header('X-Idempotency-Key');
        $stripePaymentId = $validated['stripe_payment_id'] ?? null;

        $existingOrder = null;
        if (! empty($idempotencyKey)) {
            $existingOrder = Order::where('idempotency_key', $idempotencyKey)->first();
        }
        if (! $existingOrder && ! empty($stripePaymentId)) {
            $existingOrder = Order::where('stripe_payment_id', $stripePaymentId)->first();
        }

        if ($existingOrder) {
            $existingOrder->load(['items', 'user']);
            $request->session()->push('placed_order_numbers', $existingOrder->order_number);

            if ($request->wantsJson()) {
                return response()->json([
                    'success' => true,
                    'order' => $existingOrder,
                ], 200);
            }

            return redirect()->route('account')->with('success', "Order {$existingOrder->order_number} confirmed!");
        }

        $user = Auth::user();
        $customerName = $user?->name ?? $validated['customer_name'] ?? 'Guest Customer';
        $customerEmail = $user?->email ?? $validated['customer_email'] ?? 'guest@example.com';
        $customerPhone = $validated['customer_phone'] ?? null;
        $stripePaymentId = $validated['stripe_payment_id'] ?? null;

        try {
            $order = DB::transaction(function () use ($request, $validated, $user, $customerName, $customerEmail, $customerPhone, $idempotencyKey) {
                $subtotal = 0;
                $resolvedItems = [];
                $requiredProductQuantities = [];

                foreach ($validated['items'] as $index => $item) {
                    $model = ($item['type'] ?? 'product') === 'recipe-kit' ? RecipeKit::class : Product::class;
                    $catalogItem = $model::query()
                        ->where(is_numeric($item['id']) ? 'id' : 'slug', $item['id'])->first();

                    if (! $catalogItem && ! isset($item['type'])) {
                        $catalogItem = RecipeKit::query()
                            ->where(is_numeric($item['id']) ? 'id' : 'slug', $item['id'])->first();
                    }

                    if (! $catalogItem || ($catalogItem instanceof RecipeKit && ! $catalogItem->is_active)) {
                        throw ValidationException::withMessages(["items.{$index}.id" => 'This item is no longer available. Please update your cart.']);
                    }

                    $isSubscribed = ! empty($item['is_subscribed']);
                    if ($isSubscribed && ! ($catalogItem instanceof Product && $catalogItem->has_subscription)) {
                        throw ValidationException::withMessages(["items.{$index}.is_subscribed" => 'This item is not eligible for subscription pricing.']);
                    }

                    $unitPrice = round($catalogItem->price * ($isSubscribed ? 0.95 : 1), 2);
                    $quantity = (int) $item['quantity'];

                    // Track total quantities required per unique product ID (including kit ingredients)
                    if ($catalogItem instanceof Product) {
                        $requiredProductQuantities[$catalogItem->id] = ($requiredProductQuantities[$catalogItem->id] ?? 0) + $quantity;
                    } else {
                        $catalogItem->load('products');
                        foreach ($catalogItem->products as $kitProduct) {
                            $needed = ($kitProduct->pivot->quantity ?? 1) * $quantity;
                            $requiredProductQuantities[$kitProduct->id] = ($requiredProductQuantities[$kitProduct->id] ?? 0) + $needed;
                        }
                    }

                    $item['price'] = $unitPrice;
                    $item['name'] = $catalogItem->name;
                    $item['image'] = $catalogItem->image;
                    $item['size'] = $catalogItem instanceof Product ? $catalogItem->size_main : null;
                    $item['catalog_item'] = $catalogItem;
                    $resolvedItems[] = $item;
                    $subtotal += round($unitPrice * $quantity, 2);
                }
                $subtotal = round($subtotal, 2);

                // Lock each unique product, check aggregate quantity against stock, and decrement atomically
                $lockedProducts = Product::query()
                    ->whereIn('id', array_keys($requiredProductQuantities))
                    ->lockForUpdate()
                    ->get()
                    ->keyBy('id');

                foreach ($requiredProductQuantities as $productId => $totalQty) {
                    $product = $lockedProducts->get($productId);
                    if (! $product) {
                        throw ValidationException::withMessages(['items' => ['One or more products could not be located for inventory check.']]);
                    }
                    $currentStock = (int) ($product->stock ?? 0);
                    if ($currentStock < $totalQty) {
                        throw ValidationException::withMessages([
                            'items' => ["Insufficient stock for '{$product->name}'. Total requested: {$totalQty}, but only {$currentStock} available."],
                            'error_code' => 'out_of_stock',
                        ]);
                    }
                    $product->decrementStock($totalQty);
                }

                $storeInfo = StoreSetting::current();

                // Check Minimum Order Amount
                if (($storeInfo->min_order_amount ?? 0) > 0 && $subtotal < $storeInfo->min_order_amount) {
                    $minFormatted = number_format($storeInfo->min_order_amount, 2);
                    throw ValidationException::withMessages([
                        'total' => "Minimum order amount is \${$minFormatted}. Please add more items to your cart.",
                        'error_code' => 'min_order_not_met',
                    ]);
                }

                $fulfillmentType = $validated['fulfillment_type'] ?? 'Store Pickup';
                $pickupSlot = $this->pickupSlot($validated, $storeInfo, $fulfillmentType);
                $deliveryFee = 0.00;
                if ($fulfillmentType === 'Home Delivery') {
                    if ($subtotal < ($storeInfo->free_delivery_threshold ?? 50.00)) {
                        $deliveryFee = (float) ($storeInfo->delivery_fee ?? 4.99);
                    }
                }

                $discount = 0.00;
                $total = max(0, round($subtotal - $discount + $deliveryFee, 2));

                // Validate expected total if provided from client
                if (isset($validated['expected_total'])) {
                    $expectedTotal = round((float) $validated['expected_total'], 2);
                    if (abs($total - $expectedTotal) > 0.05) {
                        throw ValidationException::withMessages([
                            'total' => "Order total has changed. Expected \${$expectedTotal}, but current total is \${$total}. Please review and confirm your order.",
                            'error_code' => 'price_changed',
                        ]);
                    }
                }

                $pointsEarned = (int) floor($subtotal);

                // Generate clean, collision-free, chronological order number (e.g. #MM-20261004-1001)
                $orderNumber = Order::generateOrderNumber();

                $deliveryAddress = $validated['delivery_address'] ?? null;
                $defaultLocation = $fulfillmentType === 'Home Delivery'
                    ? ($deliveryAddress ?: 'Delivery Address')
                    : ($storeInfo->address.' · '.$storeInfo->name);

                $stripePaymentId = $validated['stripe_payment_id'] ?? null;

                // Concurrency check: if a webhook or parallel request finished creating the order just now
                if (! empty($stripePaymentId)) {
                    $alreadyUsed = Order::where('stripe_payment_id', $stripePaymentId)->first();
                    if ($alreadyUsed) {
                        return $alreadyUsed;
                    }
                }

                // Strict Payment Verification: All online orders must have verified Stripe payment
                $paymentMethod = $validated['payment_method'] ?? 'card';
                $stripeSecret = config('services.stripe.secret');

                if (! app()->environment('testing')) {
                    if (! empty($stripeSecret) || app()->environment('production')) {
                        if (empty($stripePaymentId)) {
                            throw ValidationException::withMessages([
                                'stripe_payment_id' => 'Payment reference is required to complete this order.',
                            ]);
                        }

                        if (! app()->environment('testing')) {
                            // Disallow any fake or simulated tokens in production
                            if (
                                ! str_starts_with($stripePaymentId, 'pi_') ||
                                str_starts_with($stripePaymentId, 'pi_demo_') ||
                                str_starts_with($stripePaymentId, 'pi_apple_pay_') ||
                                str_starts_with($stripePaymentId, 'pi_google_wallet_') ||
                                str_starts_with($stripePaymentId, 'pi_test_')
                            ) {
                                throw ValidationException::withMessages([
                                    'stripe_payment_id' => 'Invalid or simulated payment reference is not permitted.',
                                ]);
                            }

                            try {
                                Stripe::setApiKey($stripeSecret);
                                $intent = PaymentIntent::retrieve($stripePaymentId);

                                if ($intent->status !== 'succeeded') {
                                    throw ValidationException::withMessages([
                                        'stripe_payment_id' => "Payment is not completed. Current Stripe status: {$intent->status}.",
                                    ]);
                                }

                                $expectedCents = (int) round($total * 100);
                                $receivedCents = (int) ($intent->amount_received ?? 0);
                                if ($receivedCents !== $expectedCents) {
                                    $receivedDollars = number_format($receivedCents / 100, 2);
                                    $totalDollars = number_format($total, 2);
                                    throw ValidationException::withMessages([
                                        'stripe_payment_id' => "Paid amount (\${$receivedDollars}) does not match order total (\${$totalDollars}).",
                                    ]);
                                }

                                $storeCurrency = strtolower(config('services.stripe.currency', 'cad'));
                                if (strtolower($intent->currency) !== $storeCurrency) {
                                    throw ValidationException::withMessages([
                                        'stripe_payment_id' => "Payment currency ({$intent->currency}) does not match store currency ({$storeCurrency}).",
                                    ]);
                                }

                                // Verify customer/session ownership binding if set
                                $intentSessionId = $intent->metadata->session_id ?? null;
                                if ($intentSessionId && $intentSessionId !== $request->session()->getId()) {
                                    throw ValidationException::withMessages([
                                        'stripe_payment_id' => 'Payment intent does not match your active checkout session.',
                                    ]);
                                }
                            } catch (ApiErrorException $e) {
                                Log::error('Stripe payment verification failed: '.$e->getMessage());
                                throw ValidationException::withMessages([
                                    'stripe_payment_id' => 'Unable to verify payment with Stripe: '.$e->getMessage(),
                                ]);
                            }
                        }
                    }
                }

                $order = Order::create([
                    'order_number' => $orderNumber,
                    'user_id' => $user?->id,
                    'customer_name' => $customerName,
                    'customer_email' => $customerEmail,
                    'customer_phone' => $customerPhone,
                    'subtotal' => $subtotal,
                    'discount' => $discount,
                    'delivery_fee' => $deliveryFee,
                    'total' => $total,
                    'points_earned' => $pointsEarned,
                    'payment_method' => $validated['payment_method'] ?? 'card',
                    'stripe_payment_id' => $stripePaymentId,
                    'fulfillment_type' => $fulfillmentType,
                    'pickup_slot' => $pickupSlot,
                    'pickup_location' => $validated['pickup_location'] ?? $defaultLocation,
                    'delivery_address' => $deliveryAddress,
                    'status' => 'confirmed',
                    'notes' => $validated['notes'] ?? null,
                    'idempotency_key' => $idempotencyKey,
                ]);

                foreach ($resolvedItems as $item) {
                    $catalogItem = $item['catalog_item'];
                    $quantity = (int) $item['quantity'];
                    $productId = $catalogItem instanceof Product ? $catalogItem->id : null;

                    $order->items()->create([
                        'product_id' => $productId,
                        'name' => $item['name'],
                        'size' => $item['size'] ?? ($item['weight'] ?? null),
                        'unit_price' => $item['price'],
                        'quantity' => $quantity,
                        'total_price' => round($item['price'] * $quantity, 2),
                        'is_subscribed' => ! empty($item['is_subscribed']),
                        'image' => $item['image'] ?? null,
                    ]);
                }

                return $order;
            });
        } catch (\Throwable $e) {
            // First check if concurrent creation succeeded (QueryException / unique key collision)
            if (! empty($stripePaymentId)) {
                $existingOrder = Order::where('stripe_payment_id', $stripePaymentId)->first();
                if ($existingOrder) {
                    $existingOrder->load(['items', 'user']);
                    $request->session()->push('placed_order_numbers', $existingOrder->order_number);
                    if ($request->wantsJson()) {
                        return response()->json([
                            'success' => true,
                            'order' => $existingOrder,
                        ], 200);
                    }

                    return redirect()->route('account')->with('success', "Order {$existingOrder->order_number} confirmed!");
                }
            }

            // CRITICAL REFUND PATH: If order creation failed and customer was already charged via Stripe:
            // Issue immediate refund so the customer is NEVER charged without an order!
            if (! empty($stripePaymentId) && ! app()->environment('testing')) {
                $stripeSecret = config('services.stripe.secret');
                if (! empty($stripeSecret)) {
                    try {
                        Stripe::setApiKey($stripeSecret);
                        Refund::create([
                            'payment_intent' => $stripePaymentId,
                            'reason' => 'fraudulent',
                            'metadata' => [
                                'reason' => 'Automated server-side refund: order creation failed in database',
                                'error' => substr($e->getMessage(), 0, 500),
                            ],
                        ]);
                        Log::warning("Automated refund successfully issued for {$stripePaymentId} because order creation failed: {$e->getMessage()}");
                    } catch (\Throwable $refundEx) {
                        Log::error("CRITICAL: Failed to issue automated refund for payment {$stripePaymentId}: {$refundEx->getMessage()}");
                    }
                }
            }

            throw $e;
        }

        $order->load(['items', 'user']);

        // Remember order in session for guest tracking authorization
        $request->session()->push('placed_order_numbers', $order->order_number);

        // Dispatch real-time WebSocket broadcast event safely
        try {
            OrderPlaced::dispatch($order);
        } catch (\Throwable $e) {
            Log::warning('OrderPlaced WebSocket dispatch failed: '.$e->getMessage());
        }

        if ($request->wantsJson()) {
            return response()->json([
                'success' => true,
                'order' => $order,
            ], 201);
        }

        return redirect()->route('account')->with('success', "Order {$order->order_number} confirmed!");
    }

    /**
     * Pre-payment validation endpoint: validates stock, prices, store open status,
     * slot availability, and order rules BEFORE the customer's card is charged.
     */
    public function validateOrder(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'items' => ['required', 'array', 'min:1'],
            'items.*.id' => ['required'],
            'items.*.quantity' => ['required', 'integer', 'min:1'],
            'items.*.price' => ['required', 'numeric', 'min:0'],
            'items.*.is_subscribed' => ['nullable', 'boolean'],
            'items.*.type' => ['nullable', 'string', 'in:product,recipe-kit'],
            'fulfillment_type' => ['required', 'string', 'in:Store Pickup,Home Delivery'],
            'delivery_address' => ['nullable', 'string', 'max:500'],
            'pickup_slot' => ['nullable', 'string', 'max:255'],
            'pickup_timing_mode' => ['nullable', 'string', 'in:asap,scheduled'],
            'pickup_timing_type' => ['nullable', 'string', 'in:slot,custom'],
            'pickup_date' => ['nullable', 'string', 'max:50'],
            'pickup_time' => ['nullable', 'string', 'max:50'],
        ]);

        $storeInfo = StoreSetting::current();
        $result = $this->validateOrderRequirements($validated, $storeInfo);

        return response()->json([
            'valid' => true,
            'subtotal' => $result['subtotal'],
            'delivery_fee' => $result['delivery_fee'],
            'total' => $result['total'],
            'pickup_slot' => $result['pickup_slot'],
        ]);
    }

    /**
     * Core validation for cart items, inventory, pricing, slot capacity, and store limits.
     *
     * @param  array<string, mixed>  $validated
     * @return array<string, mixed>
     */
    protected function validateOrderRequirements(array $validated, StoreSetting $storeInfo): array
    {
        $subtotal = 0;
        $resolvedItems = [];
        $requiredProductQuantities = [];

        foreach ($validated['items'] as $index => $item) {
            $model = ($item['type'] ?? 'product') === 'recipe-kit' ? RecipeKit::class : Product::class;
            $catalogItem = $model::query()
                ->where(is_numeric($item['id']) ? 'id' : 'slug', $item['id'])->first();

            if (! $catalogItem && ! isset($item['type'])) {
                $catalogItem = RecipeKit::query()
                    ->where(is_numeric($item['id']) ? 'id' : 'slug', $item['id'])->first();
            }

            if (! $catalogItem || ($catalogItem instanceof RecipeKit && ! $catalogItem->is_active)) {
                throw ValidationException::withMessages(["items.{$index}.id" => 'This item is no longer available. Please update your cart.']);
            }

            $isSubscribed = ! empty($item['is_subscribed']);
            if ($isSubscribed && ! ($catalogItem instanceof Product && $catalogItem->has_subscription)) {
                throw ValidationException::withMessages(["items.{$index}.is_subscribed" => 'This item is not eligible for subscription pricing.']);
            }

            $unitPrice = round($catalogItem->price * ($isSubscribed ? 0.95 : 1), 2);
            $quantity = (int) $item['quantity'];

            if ($catalogItem instanceof Product) {
                $requiredProductQuantities[$catalogItem->id] = ($requiredProductQuantities[$catalogItem->id] ?? 0) + $quantity;
            } else {
                $catalogItem->load('products');
                foreach ($catalogItem->products as $kitProduct) {
                    $needed = ($kitProduct->pivot->quantity ?? 1) * $quantity;
                    $requiredProductQuantities[$kitProduct->id] = ($requiredProductQuantities[$kitProduct->id] ?? 0) + $needed;
                }
            }

            $item['price'] = $unitPrice;
            $item['name'] = $catalogItem->name;
            $item['image'] = $catalogItem->image;
            $item['size'] = $catalogItem instanceof Product ? $catalogItem->size_main : null;
            $item['catalog_item'] = $catalogItem;
            $resolvedItems[] = $item;
            $subtotal += round($unitPrice * $quantity, 2);
        }
        $subtotal = round($subtotal, 2);

        // Pre-check stock sufficiency against existing inventory
        $products = Product::query()
            ->whereIn('id', array_keys($requiredProductQuantities))
            ->get()
            ->keyBy('id');

        foreach ($requiredProductQuantities as $productId => $totalQty) {
            $product = $products->get($productId);
            if (! $product) {
                throw ValidationException::withMessages(['items' => ['One or more products could not be located for inventory check.']]);
            }
            $currentStock = (int) ($product->stock ?? 0);
            if ($currentStock < $totalQty) {
                throw ValidationException::withMessages([
                    'items' => ["Insufficient stock for '{$product->name}'. Total requested: {$totalQty}, but only {$currentStock} available."],
                    'error_code' => 'out_of_stock',
                ]);
            }
        }

        // Check Minimum Order Amount
        if (($storeInfo->min_order_amount ?? 0) > 0 && $subtotal < $storeInfo->min_order_amount) {
            $minFormatted = number_format($storeInfo->min_order_amount, 2);
            throw ValidationException::withMessages([
                'total' => "Minimum order amount is \${$minFormatted}. Please add more items to your cart.",
                'error_code' => 'min_order_not_met',
            ]);
        }

        $fulfillmentType = $validated['fulfillment_type'] ?? 'Store Pickup';
        $pickupSlot = $this->pickupSlot($validated, $storeInfo, $fulfillmentType);
        $deliveryFee = 0.00;
        if ($fulfillmentType === 'Home Delivery') {
            if ($subtotal < ($storeInfo->free_delivery_threshold ?? 50.00)) {
                $deliveryFee = (float) ($storeInfo->delivery_fee ?? 4.99);
            }
        }

        $total = max(0, round($subtotal + $deliveryFee, 2));

        return [
            'subtotal' => $subtotal,
            'delivery_fee' => $deliveryFee,
            'total' => $total,
            'fulfillment_type' => $fulfillmentType,
            'pickup_slot' => $pickupSlot,
            'resolvedItems' => $resolvedItems,
            'requiredProductQuantities' => $requiredProductQuantities,
        ];
    }

    /**
     * Build a fulfillment label from validated choices and current store settings.
     *
     * @param  array<string, mixed>  $data
     */
    private function pickupSlot(array $data, StoreSetting $storeInfo, string $fulfillmentType): string
    {
        if ($fulfillmentType === 'Home Delivery') {
            if (! $storeInfo->is_delivery_active) {
                throw ValidationException::withMessages([
                    'fulfillment_type' => 'Home delivery is currently disabled.',
                    'error_code' => 'delivery_disabled',
                ]);
            }
            if (! $storeInfo->is_delivery_available_today) {
                $days = implode(', ', array_map('ucfirst', (array) ($storeInfo->delivery_days ?? [])));
                throw ValidationException::withMessages([
                    'fulfillment_type' => "Home delivery is only available on {$days}.",
                    'error_code' => 'delivery_off_day',
                ]);
            }

            return 'Home Delivery · '.$storeInfo->delivery_estimated_time;
        }

        if (! $storeInfo->is_pickup_active) {
            throw ValidationException::withMessages([
                'pickup_slot' => 'Store pickup is currently paused.',
                'error_code' => 'pickup_paused',
            ]);
        }

        $targetDay = ($data['pickup_timing_mode'] ?? null) === 'scheduled' ? ($data['pickup_date'] ?? 'today') : 'today';
        if (! $storeInfo->isPickupAvailableOn($targetDay)) {
            $days = implode(', ', array_map('ucfirst', (array) ($storeInfo->pickup_days ?? [])));
            throw ValidationException::withMessages([
                'pickup_slot' => "Store pickup is not available on this day. Open pickup days: {$days}.",
                'error_code' => 'store_closed',
            ]);
        }

        if (isset($data['pickup_timing_mode']) && $data['pickup_timing_mode'] === 'asap') {
            $tz = config('app.timezone');
            $now = now($tz);
            $currentTime = $now->format('H:i');
            $start = $storeInfo->pickup_slot_start_time ?? '09:00';
            $end = $storeInfo->pickup_slot_end_time ?? '21:00';
            $prepMinutes = (int) ($storeInfo->effective_prep_time_minutes ?? 15);
            $readyTime = $now->copy()->addMinutes($prepMinutes)->format('H:i');

            if ($currentTime < $start || $currentTime > $end || $readyTime > $end) {
                $formattedHours = date('g:i A', strtotime($start)).' – '.date('g:i A', strtotime($end));
                throw ValidationException::withMessages([
                    'pickup_timing_mode' => "Store is currently closed for ASAP pickup (pickup hours: {$formattedHours}). Please select a scheduled time for tomorrow.",
                    'error_code' => 'slot_expired',
                ]);
            }

            return "ASAP (Ready in ~{$storeInfo->effective_prep_time_minutes} mins)";
        }

        if (($data['pickup_timing_mode'] ?? null) === 'scheduled') {
            $tz = config('app.timezone');
            $now = now($tz);
            $date = $data['pickup_date'];
            if (! in_array($date, [$now->toDateString(), $now->copy()->addDay()->toDateString()], true)) {
                throw ValidationException::withMessages([
                    'pickup_date' => 'Choose today or tomorrow for pickup.',
                    'error_code' => 'date_invalid',
                ]);
            }

            $time = $data['pickup_time'] ?? '';
            $label = '';
            if ($data['pickup_timing_type'] === 'slot') {
                $slot = collect($storeInfo->available_pickup_slots)->firstWhere('label', $data['pickup_slot'] ?? '');
                if (! $slot || ! preg_match('/^(\d{1,2}):(\d{2}) (AM|PM)/', $slot['label'], $parts)) {
                    throw ValidationException::withMessages([
                        'pickup_slot' => 'Choose an available pickup window.',
                        'error_code' => 'slot_inactive',
                    ]);
                }
                $hour = ((int) $parts[1] % 12) + ($parts[3] === 'PM' ? 12 : 0);
                $time = sprintf('%02d:%02d', $hour, (int) $parts[2]);
                $label = $slot['label'];

                // Check slot capacity limit
                $maxCapacity = (int) ($storeInfo->max_orders_per_slot ?? 0);
                if ($maxCapacity > 0) {
                    $bookedCount = Order::query()
                        ->where('pickup_slot', 'LIKE', "{$date} · {$slot['label']}%")
                        ->whereNotIn('status', ['cancelled'])
                        ->count();

                    if ($bookedCount >= $maxCapacity) {
                        throw ValidationException::withMessages([
                            'pickup_slot' => "This pickup window ({$slot['label']}) has reached its capacity limit. Please select another time window.",
                            'error_code' => 'slot_full',
                        ]);
                    }
                }
            }

            if ($time === '') {
                throw ValidationException::withMessages([
                    'pickup_time' => 'Choose a pickup time.',
                    'error_code' => 'slot_missing',
                ]);
            }

            $pickupAt = $now->copy()->setDateFrom($date)->setTimeFromTimeString($time.':00');
            $start = $storeInfo->pickup_slot_start_time ?? '09:00';
            $end = $storeInfo->pickup_slot_end_time ?? '21:00';
            if ($time < $start || $time > $end || $pickupAt->lt($now->copy()->addMinutes($storeInfo->effective_prep_time_minutes))) {
                throw ValidationException::withMessages([
                    'pickup_time' => 'Choose a future pickup time within store hours, allowing time for preparation.',
                    'error_code' => 'slot_expired',
                ]);
            }

            return $date.' · '.($label ?: $pickupAt->format('g:i A').' (Custom Time)');
        }

        if (! empty($data['pickup_slot'])) {
            return $data['pickup_slot'];
        }

        return "ASAP (Ready in ~{$storeInfo->effective_prep_time_minutes} mins)";
    }

    /**
     * Display the specified order for customer live tracking.
     */
    public function show(Request $request, string $orderNumber): Response
    {
        $hashNumber = str_starts_with($orderNumber, '#') ? $orderNumber : '#'.$orderNumber;
        $order = Order::with('items')
            ->where('order_number', $orderNumber)
            ->orWhere('order_number', $hashNumber)
            ->firstOrFail();

        $isAdmin = Auth::guard('admin')->check();
        $isOwner = Auth::check() && $order->user_id && Auth::id() === $order->user_id;
        $isSessionOwner = in_array($order->order_number, (array) $request->session()->get('placed_order_numbers', []), true);

        if (! $isAdmin && ! $isOwner && ! $isSessionOwner) {
            abort(403, 'You are not authorized to view this order.');
        }

        return Inertia::render('OrderTracking', [
            'order' => [
                'id' => $order->id,
                'order_number' => $order->order_number,
                'customer_name' => $order->customer_name,
                'subtotal' => (float) $order->subtotal,
                'discount' => (float) $order->discount,
                'total' => (float) $order->total,
                'points_earned' => $order->points_earned,
                'payment_method' => $order->payment_method,
                'stripe_payment_id' => $order->stripe_payment_id,
                'fulfillment_type' => $order->fulfillment_type,
                'pickup_slot' => $order->pickup_slot,
                'pickup_location' => $order->pickup_location,
                'delivery_address' => $order->delivery_address,
                'status' => $order->status,
                'notes' => $order->notes,
                'created_at' => $order->created_at?->toISOString(),
                'created_at_human' => $order->created_at?->diffForHumans(),
                'items' => $order->items->map(function ($item) {
                    return [
                        'id' => $item->id,
                        'name' => $item->name,
                        'size' => $item->size,
                        'unit_price' => (float) $item->unit_price,
                        'quantity' => $item->quantity,
                        'total_price' => (float) $item->total_price,
                        'image' => $item->image,
                    ];
                }),
            ],
        ]);
    }

    /**
     * Compute authoritative server-side totals from catalog items and fulfillment type.
     */
    public function computeOrderTotals(array $items, string $fulfillmentType = 'Store Pickup'): array
    {
        $subtotal = 0.00;
        foreach ($items as $item) {
            $model = ($item['type'] ?? 'product') === 'recipe-kit' ? RecipeKit::class : Product::class;
            $catalogItem = $model::query()
                ->where(is_numeric($item['id']) ? 'id' : 'slug', $item['id'])->first();

            if (! $catalogItem && ! isset($item['type'])) {
                $catalogItem = RecipeKit::query()
                    ->where(is_numeric($item['id']) ? 'id' : 'slug', $item['id'])->first();
            }

            if (! $catalogItem) {
                continue;
            }

            $isSubscribed = ! empty($item['is_subscribed']);
            $unitPrice = round($catalogItem->price * ($isSubscribed ? 0.95 : 1), 2);
            $quantity = max(1, (int) ($item['quantity'] ?? 1));
            $subtotal += round($unitPrice * $quantity, 2);
        }

        $subtotal = round($subtotal, 2);
        $storeInfo = StoreSetting::current();
        $deliveryFee = 0.00;
        if ($fulfillmentType === 'Home Delivery') {
            if ($subtotal < ($storeInfo->free_delivery_threshold ?? 50.00)) {
                $deliveryFee = (float) ($storeInfo->delivery_fee ?? 4.99);
            }
        }

        $total = max(0, round($subtotal + $deliveryFee, 2));

        return [
            'subtotal' => $subtotal,
            'delivery_fee' => $deliveryFee,
            'total' => $total,
        ];
    }

    /**
     * Create a Stripe PaymentIntent for checkout.
     */
    public function createPaymentIntent(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'amount' => ['nullable', 'numeric', 'min:0.50'],
            'currency' => ['nullable', 'string', 'size:3'],
            'items' => ['nullable', 'array'],
            'fulfillment_type' => ['nullable', 'string', 'in:Store Pickup,Home Delivery'],
            'delivery_address' => ['nullable', 'string', 'max:500'],
            'pickup_slot' => ['nullable', 'string', 'max:255'],
            'pickup_location' => ['nullable', 'string', 'max:255'],
            'customer_name' => ['nullable', 'string', 'max:255'],
            'customer_email' => ['nullable', 'string', 'max:255'],
            'customer_phone' => ['nullable', 'string', 'max:50'],
            'payment_intent_id' => ['nullable', 'string'],
            'idempotency_key' => ['nullable', 'string'],
        ]);

        $stripeSecret = config('services.stripe.secret');
        if (empty($stripeSecret)) {
            return response()->json([
                'error' => 'Stripe secret key is not configured. Please set STRIPE_SECRET in your .env file.',
                'configured' => false,
            ], 503);
        }

        // Strict Ownership Check: only allow updating an intent that belongs to this active session
        $requestedIntentId = $validated['payment_intent_id'] ?? null;
        $activeSessionIntentId = $request->session()->get('active_payment_intent_id');

        if (! empty($requestedIntentId)) {
            if (empty($activeSessionIntentId) || $requestedIntentId !== $activeSessionIntentId) {
                return response()->json([
                    'error' => 'Payment intent does not belong to your active checkout session.',
                ], 403);
            }
        }

        // Calculate authoritative amount on the server if items provided, preventing client-side price tampering
        $amountInDollars = $validated['amount'] ?? null;
        if (! empty($validated['items'])) {
            $computed = $this->computeOrderTotals($validated['items'], $validated['fulfillment_type'] ?? 'Store Pickup');
            if ($computed['total'] >= 0.50) {
                $amountInDollars = $computed['total'];
            }
        }

        if (! $amountInDollars || $amountInDollars < 0.50) {
            return response()->json([
                'error' => 'Order total must be at least $0.50 to create a payment.',
            ], 422);
        }

        try {
            Stripe::setApiKey($stripeSecret);

            $amountInCents = (int) round($amountInDollars * 100);
            $currency = strtolower($validated['currency'] ?? config('services.stripe.currency', 'cad'));

            $metadata = [
                'session_id' => $request->session()->getId(),
                'user_id' => (string) (Auth::id() ?? 'guest'),
                'fulfillment_type' => $validated['fulfillment_type'] ?? 'Store Pickup',
            ];
            if (! empty($validated['delivery_address'])) {
                $metadata['delivery_address'] = $validated['delivery_address'];
            }
            if (! empty($validated['pickup_slot'])) {
                $metadata['pickup_slot'] = $validated['pickup_slot'];
            }
            if (! empty($validated['pickup_location'])) {
                $metadata['pickup_location'] = $validated['pickup_location'];
            }
            if (! empty($validated['customer_name'])) {
                $metadata['customer_name'] = $validated['customer_name'];
            }
            if (! empty($validated['customer_email'])) {
                $metadata['customer_email'] = $validated['customer_email'];
            }
            if (! empty($validated['customer_phone'])) {
                $metadata['customer_phone'] = $validated['customer_phone'];
            }
            if (! empty($validated['idempotency_key'])) {
                $metadata['idempotency_key'] = $validated['idempotency_key'];
            }
            if (! empty($validated['items'])) {
                $metadata['items_json'] = json_encode(array_slice($validated['items'], 0, 30));
            }

            // If intent already exists in this session, update amount and metadata rather than creating duplicates
            $targetIntentId = $activeSessionIntentId;
            if (! empty($targetIntentId)) {
                try {
                    $intent = PaymentIntent::update($targetIntentId, [
                        'amount' => $amountInCents,
                        'currency' => $currency,
                        'metadata' => $metadata,
                    ]);

                    $request->session()->put('active_payment_intent_id', $intent->id);

                    return response()->json([
                        'clientSecret' => $intent->client_secret,
                        'paymentIntentId' => $intent->id,
                        'configured' => true,
                    ]);
                } catch (\Exception $e) {
                    Log::warning('PaymentIntent update failed, falling back to new intent: '.$e->getMessage());
                }
            }

            $options = [];
            if (! empty($validated['idempotency_key'])) {
                $options['idempotency_key'] = $validated['idempotency_key'];
            }

            $intent = PaymentIntent::create([
                'amount' => $amountInCents,
                'currency' => $currency,
                'metadata' => $metadata,
                'automatic_payment_methods' => [
                    'enabled' => true,
                    'allow_redirects' => 'never',
                ],
            ], $options);

            $request->session()->put('active_payment_intent_id', $intent->id);

            return response()->json([
                'clientSecret' => $intent->client_secret,
                'paymentIntentId' => $intent->id,
                'configured' => true,
            ]);
        } catch (\Exception $e) {
            Log::error('Stripe PaymentIntent creation failed: '.$e->getMessage());

            return response()->json([
                'error' => $e->getMessage(),
                'configured' => true,
            ], 422);
        }
    }
}
