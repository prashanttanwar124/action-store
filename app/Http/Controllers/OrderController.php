<?php

namespace App\Http\Controllers;

use App\Exceptions\SlotCapacityExceededException;
use App\Models\Checkout;
use App\Models\Order;
use App\Models\Product;
use App\Models\RecipeKit;
use App\Models\StoreSetting;
use App\Models\User;
use App\Services\CheckoutCompletionResult;
use App\Services\StripePayments;
use Illuminate\Contracts\Cache\LockTimeoutException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;

class OrderController extends Controller
{
    /**
     * Start (or resume) the customer's checkout and return what the browser needs to pay for it.
     *
     * An order is only created once the payment succeeds. Until then the checkout reserves the
     * stock. A repeated "Pay" click for the same cart (e.g. after a declined card) resumes the
     * same checkout; a changed cart releases the old checkout and starts a new one.
     */
    public function store(Request $request, StripePayments $payments): JsonResponse|RedirectResponse
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
            'payment_method' => ['nullable', 'string', 'in:card,stripe,apple-pay,google-pay'],
            'fulfillment_type' => ['nullable', 'in:Store Pickup,Home Delivery'],
            'pickup_timing_mode' => ['sometimes', 'in:asap,scheduled'],
            'pickup_timing_type' => ['required_if:pickup_timing_mode,scheduled', 'in:slot,custom'],
            'pickup_date' => ['required_if:pickup_timing_mode,scheduled', 'date_format:Y-m-d'],
            'pickup_time' => ['nullable', 'date_format:H:i'],
            'pickup_slot' => ['nullable', 'string', 'max:100'],
            'pickup_location' => ['nullable', 'string', 'max:255'],
            'delivery_address' => ['required_if:fulfillment_type,Home Delivery', 'nullable', 'string', 'max:500'],
            'customer_phone' => ['nullable', 'string', 'max:50'],
            'notes' => ['nullable', 'string', 'max:1000'],
        ]);

        $user = Auth::user();
        if (! $user) {
            abort(401, 'Please sign in or create an account to complete your order.');
        }

        if (! $payments->isEnabled() && ! app()->environment('local', 'testing')) {
            throw ValidationException::withMessages([
                'payment' => 'Online payment is currently unavailable. Please try again later.',
            ]);
        }

        // One checkout request per customer at a time, so a double click cannot create duplicate checkouts
        try {
            return Cache::lock("checkout:user:{$user->id}", 30)
                ->block(10, fn () => $this->startCheckout($request, $validated, $user, $payments));
        } catch (LockTimeoutException) {
            throw ValidationException::withMessages([
                'payment' => 'Your previous checkout request is still being processed. Please wait a moment and try again.',
            ]);
        }
    }

    /**
     * Confirm a checkout after the browser reports a successful payment, and return the new order.
     *
     * The browser's word is never trusted: the checkout's PaymentIntent is fetched from Stripe and
     * must be a completed, exact payment. If the webhook got there first, its order is returned.
     */
    public function complete(Request $request, StripePayments $payments): JsonResponse|RedirectResponse
    {
        $validated = $request->validate([
            'checkout_id' => ['required', 'integer'],
            'payment_intent_id' => ['nullable', 'string', 'max:255'],
        ]);

        $userId = Auth::id();

        if (! empty($validated['payment_intent_id'])) {
            $order = Order::where('stripe_payment_id', $validated['payment_intent_id'])
                ->where('user_id', $userId)
                ->first();

            if ($order) {
                return $this->orderResponse($request, $order, 200);
            }
        }

        $checkout = Checkout::where('id', $validated['checkout_id'])
            ->where('user_id', $userId)
            ->firstOrFail();

        if (empty($checkout->stripe_payment_id) || ! $payments->isEnabled()) {
            throw ValidationException::withMessages([
                'payment' => 'This checkout has no payment to confirm.',
            ]);
        }

        try {
            $intent = $payments->retrieveIntent($checkout->stripe_payment_id);
        } catch (\Throwable $e) {
            Log::error("Could not verify payment for checkout {$checkout->id}: {$e->getMessage()}");

            throw ValidationException::withMessages([
                'payment' => 'We could not verify your payment yet. Please check your orders in a moment.',
            ]);
        }

        $result = $payments->completeOrRefund($checkout, $intent);

        if ($response = $this->handleCompletionResult($request, $result)) {
            return $response;
        }

        throw ValidationException::withMessages([
            'payment' => "Payment has not completed (status: {$intent->status}).",
        ]);
    }

    /**
     * Process the outcome of a checkout completion attempt, returning an order response
     * or throwing appropriate validation exceptions with user-friendly refund messaging.
     *
     * @throws ValidationException
     */
    private function handleCompletionResult(Request $request, CheckoutCompletionResult $result): ?JsonResponse
    {
        if ($result->isCompleted()) {
            return $this->orderResponse($request, $result->order, 200);
        }

        if ($result->isAlreadyHandled()) {
            throw ValidationException::withMessages([
                'payment' => 'This checkout has already been processed.',
                'error_code' => 'already_processed',
            ]);
        }

        if ($result->isRefunded()) {
            $isSlotError = $result->stockException instanceof SlotCapacityExceededException;
            $message = $isSlotError
                ? 'The pickup window you selected reached its capacity limit before payment was completed. Your payment has been automatically refunded in full.'
                : 'One or more items in your cart went out of stock before payment was completed. Your payment has been automatically refunded in full.';
            $errorCode = $isSlotError ? 'slot_full_refunded' : 'out_of_stock_refunded';

            throw ValidationException::withMessages([
                'payment' => $message,
                'error_code' => $errorCode,
            ]);
        }

        if ($result->isRefundFailed()) {
            $isSlotError = $result->stockException instanceof SlotCapacityExceededException;
            $reason = $isSlotError ? 'the pickup window reached capacity' : 'one or more items went out of stock';

            throw ValidationException::withMessages([
                'payment' => "Payment was received, but {$reason} before completion. We were unable to process an automated refund immediately, but our system will retry automatically. Please contact support if you do not see your refund within 24 hours.",
                'error_code' => 'refund_failed',
            ]);
        }

        return null;
    }

    /**
     * Resume the customer's checkout for the same cart, or replace it with a new one.
     *
     * @param  array<string, mixed>  $validated
     */
    private function startCheckout(Request $request, array $validated, User $user, StripePayments $payments): JsonResponse|RedirectResponse
    {
        $fingerprint = Checkout::fingerprintFor($validated);
        $existing = Checkout::where('user_id', $user->id)->first();

        if ($existing && $existing->fingerprint === $fingerprint) {
            $response = $this->resumeCheckout($request, $existing, $payments);
            if ($response) {
                return $response;
            }
        } elseif ($existing) {
            $outcome = $payments->releaseCheckout($existing);

            if ($outcome === StripePayments::PAID) {
                return $this->orderResponse($request, Order::where('stripe_payment_id', $existing->stripe_payment_id)->firstOrFail(), 200);
            }

            if ($outcome === StripePayments::DEFERRED) {
                throw ValidationException::withMessages([
                    'payment' => 'Your previous payment is still being processed. Please wait a moment before changing your order.',
                ]);
            }
        }

        $checkout = $this->createCheckout($validated, $user, $fingerprint);

        // Without Stripe (local development and tests only) the checkout becomes an order straight away
        if (! $payments->isEnabled()) {
            return $this->orderResponse($request, $checkout->convertToOrder(), 201);
        }

        try {
            $intent = $payments->createIntentFor($checkout);
            $checkout->update(['stripe_payment_id' => $intent->id]);
        } catch (\Throwable $e) {
            Log::error("Stripe PaymentIntent creation failed for checkout {$checkout->id}: {$e->getMessage()}");
            $checkout->release();

            throw ValidationException::withMessages([
                'payment' => 'We could not start the payment. Please try again.',
            ]);
        }

        return $this->checkoutResponse($checkout, $intent->client_secret, 201);
    }

    /**
     * Pick up an existing checkout for the same cart.
     *
     * Returns null when the checkout could not be resumed and was released, so a new one should be started.
     */
    private function resumeCheckout(Request $request, Checkout $checkout, StripePayments $payments): JsonResponse|RedirectResponse|null
    {
        if (empty($checkout->stripe_payment_id) || ! $payments->isEnabled()) {
            $checkout->release();

            return null;
        }

        try {
            $intent = $payments->retrieveIntent($checkout->stripe_payment_id);
        } catch (\Throwable $e) {
            Log::error("Could not load payment for checkout {$checkout->id}: {$e->getMessage()}");

            throw ValidationException::withMessages([
                'payment' => 'We could not load your payment. Please try again.',
            ]);
        }

        // Paid already (e.g. the confirmation request was lost): finish the order now or handle refund
        $result = $payments->completeOrRefund($checkout, $intent);
        if ($response = $this->handleCompletionResult($request, $result)) {
            return $response;
        }

        if (in_array($intent->status, ['requires_payment_method', 'requires_confirmation', 'requires_action'], true)) {
            $checkout->extendLifetime();

            return $this->checkoutResponse($checkout, $intent->client_secret, 200);
        }

        if ($intent->status === 'canceled') {
            $checkout->release();

            return null;
        }

        Log::warning("Checkout {$checkout->id} could not be resumed: payment is '{$intent->status}'.");

        throw ValidationException::withMessages([
            'payment' => 'Your payment is still being processed. Please check your orders in a moment.',
        ]);
    }

    /**
     * Validate the cart against the catalog and store rules, then reserve its stock in a new checkout.
     *
     * @param  array<string, mixed>  $validated
     */
    private function createCheckout(array $validated, User $user, string $fingerprint): Checkout
    {
        $storeInfo = StoreSetting::current();
        $result = $this->validateOrderRequirements($validated, $storeInfo);

        if (isset($validated['expected_total'])) {
            $expectedTotal = round((float) $validated['expected_total'], 2);
            if (abs($result['total'] - $expectedTotal) > 0.05) {
                throw ValidationException::withMessages([
                    'total' => "Order total has changed. Expected \${$expectedTotal}, but current total is \${$result['total']}. Please review and confirm your order.",
                    'error_code' => 'price_changed',
                ]);
            }
        }

        $requiredProductQuantities = $result['requiredProductQuantities'];

        $products = Product::query()
            ->whereIn('id', array_keys($requiredProductQuantities))
            ->get()
            ->keyBy('id');

        foreach ($requiredProductQuantities as $productId => $totalQty) {
            $product = $products->get($productId);
            if (! $product) {
                throw ValidationException::withMessages(['items' => ['One or more products could not be located.']]);
            }
            $currentStock = (int) ($product->stock ?? 0);
            if ($currentStock < $totalQty) {
                throw ValidationException::withMessages([
                    'items' => ["Insufficient stock for '{$product->name}'. Total requested: {$totalQty}, but only {$currentStock} available."],
                    'error_code' => 'out_of_stock',
                ]);
            }
        }

        $fulfillmentType = $result['fulfillment_type'];
        $deliveryAddress = $validated['delivery_address'] ?? null;
        $defaultLocation = $fulfillmentType === 'Home Delivery'
            ? ($deliveryAddress ?: 'Delivery Address')
            : ($storeInfo->address.' · '.$storeInfo->name);

        return Checkout::create([
            'user_id' => $user->id,
            'fingerprint' => $fingerprint,
            'customer_name' => $user->name,
            'customer_email' => $user->email,
            'customer_phone' => $validated['customer_phone'] ?? $user->phone ?? null,
            'subtotal' => $result['subtotal'],
            'delivery_fee' => $result['delivery_fee'],
            'total' => $result['total'],
            'payment_method' => $validated['payment_method'] ?? 'card',
            'fulfillment_type' => $fulfillmentType,
            'pickup_slot' => $result['pickup_slot'],
            'capacity_slot' => $result['capacity_slot'] ?? null,
            'pickup_location' => $validated['pickup_location'] ?? $defaultLocation,
            'delivery_address' => $deliveryAddress,
            'notes' => $validated['notes'] ?? null,
            'items' => array_map(fn (array $item) => [
                'product_id' => $item['catalog_item'] instanceof Product ? $item['catalog_item']->id : null,
                'name' => $item['name'],
                'size' => $item['size'] ?? ($item['weight'] ?? null),
                'unit_price' => $item['price'],
                'quantity' => (int) $item['quantity'],
                'total_price' => round($item['price'] * (int) $item['quantity'], 2),
                'is_subscribed' => ! empty($item['is_subscribed']),
                'image' => $item['image'] ?? null,
            ], $result['resolvedItems']),
            'reserved_stock' => $requiredProductQuantities,
            'expires_at' => now()->addMinutes(Checkout::LIFETIME_MINUTES),
        ]);
    }

    /**
     * The browser still has to collect payment for this checkout.
     */
    private function checkoutResponse(Checkout $checkout, string $clientSecret, int $status): JsonResponse
    {
        return response()->json([
            'success' => true,
            'requires_payment' => true,
            'checkout_id' => $checkout->id,
            'clientSecret' => $clientSecret,
            'total' => (float) $checkout->total,
        ], $status);
    }

    /**
     * The order is placed (paid, or no online payment needed).
     */
    private function orderResponse(Request $request, Order $order, int $status): JsonResponse|RedirectResponse
    {
        $request->session()->push('placed_order_numbers', $order->order_number);
        $order->load(['items', 'user']);

        if (! $request->wantsJson()) {
            return redirect()->route('account')->with('success', "Order {$order->order_number} confirmed!");
        }

        return response()->json([
            'success' => true,
            'requires_payment' => false,
            'order_id' => $order->id,
            'order_number' => $order->order_number,
            'order' => $order,
        ], $status);
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

        // Validate stock
        $products = Product::whereIn('id', array_keys($requiredProductQuantities))->get()->keyBy('id');
        foreach ($requiredProductQuantities as $productId => $totalQty) {
            $product = $products->get($productId);
            if (! $product || (int) ($product->stock ?? 0) < $totalQty) {
                $name = $product ? $product->name : "Item #{$productId}";
                $avail = $product ? (int) $product->stock : 0;
                throw ValidationException::withMessages([
                    'items' => ["Insufficient stock for '{$name}'. Total requested: {$totalQty}, but only {$avail} available."],
                    'error_code' => 'out_of_stock',
                ]);
            }
        }

        // Validate minimum order amount
        $minRequired = (float) ($storeInfo->min_order_amount ?? 0);
        if ($minRequired > 0 && $subtotal < $minRequired) {
            $minFormatted = number_format($minRequired, 2);
            throw ValidationException::withMessages([
                'total' => "Minimum order amount is \${$minFormatted}. Please add more items to your cart.",
                'error_code' => 'min_order_not_met',
            ]);
        }

        $fulfillmentType = $validated['fulfillment_type'] ?? 'Store Pickup';
        $deliveryFee = 0.00;
        if ($fulfillmentType === 'Home Delivery') {
            $threshold = (float) ($storeInfo->free_delivery_threshold ?? 50.00);
            if ($subtotal < $threshold) {
                $deliveryFee = (float) ($storeInfo->delivery_fee ?? 4.99);
            }
        }

        [$pickupSlot, $capacitySlot] = $this->resolvePickupSlot($validated, $storeInfo, $fulfillmentType);
        $total = max(0, round($subtotal + $deliveryFee, 2));

        return [
            'subtotal' => $subtotal,
            'delivery_fee' => $deliveryFee,
            'total' => $total,
            'pickup_slot' => $pickupSlot,
            'capacity_slot' => $capacitySlot,
            'fulfillment_type' => $fulfillmentType,
            'resolvedItems' => $resolvedItems,
            'requiredProductQuantities' => $requiredProductQuantities,
        ];
    }

    /**
     * Build fulfillment and capacity slot labels from validated choices and current store settings.
     *
     * @param  array<string, mixed>  $data
     * @return array{0: string, 1: ?string}
     */
    private function resolvePickupSlot(array $data, StoreSetting $storeInfo, string $fulfillmentType): array
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

            return ['Home Delivery · '.$storeInfo->delivery_estimated_time, null];
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

            return ["ASAP (Ready in ~{$storeInfo->effective_prep_time_minutes} mins)", null];
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
            $capacitySlot = null;
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
                $capacitySlot = "{$date} · {$slot['label']}";

                // Check slot capacity limit: placed orders
                $maxCapacity = (int) ($storeInfo->max_orders_per_slot ?? 0);
                if ($maxCapacity > 0) {
                    $slotPattern = "{$date} · {$slot['label']}%";
                    $bookedCount = Order::query()
                        ->where('pickup_slot', 'LIKE', $slotPattern)
                        ->where('status', '!=', 'cancelled')
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

            $displaySlot = $date.' · '.($label ?: $pickupAt->format('g:i A').' (Custom Time)');

            return [$displaySlot, $capacitySlot];
        }

        if (! empty($data['pickup_slot'])) {
            return [$data['pickup_slot'], null];
        }

        return ["ASAP (Ready in ~{$storeInfo->effective_prep_time_minutes} mins)", null];
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
}
