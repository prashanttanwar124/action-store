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
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;

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
            'payment_method' => ['nullable', 'string', 'max:50'],
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

        $user = Auth::user();
        $customerName = $user?->name ?? $validated['customer_name'] ?? 'Guest Customer';
        $customerEmail = $user?->email ?? $validated['customer_email'] ?? 'guest@example.com';
        $customerPhone = $validated['customer_phone'] ?? null;

        $order = DB::transaction(function () use ($validated, $user, $customerName, $customerEmail, $customerPhone) {
            $subtotal = 0;
            $resolvedItems = [];
            foreach ($validated['items'] as $index => $item) {
                $model = ($item['type'] ?? 'product') === 'recipe-kit' ? RecipeKit::class : Product::class;
                $catalogItem = $model::query()->lockForUpdate()
                    ->where(is_numeric($item['id']) ? 'id' : 'slug', $item['id'])->first();

                if (! $catalogItem && ! isset($item['type'])) {
                    $catalogItem = RecipeKit::query()->lockForUpdate()
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
                $item['price'] = $unitPrice;
                $item['name'] = $catalogItem->name;
                $item['image'] = $catalogItem->image;
                $item['size'] = $catalogItem instanceof Product ? $catalogItem->size_main : null;
                $item['catalog_item'] = $catalogItem;
                $resolvedItems[] = $item;
                $subtotal += round($unitPrice * $item['quantity'], 2);
            }
            $subtotal = round($subtotal, 2);

            $storeInfo = StoreSetting::current();
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
            $pointsEarned = (int) floor($subtotal);

            // Generate unique order number (e.g. #MM-48291)
            $orderNumber = '#MM-'.mt_rand(10000, 99999);
            while (Order::where('order_number', $orderNumber)->exists()) {
                $orderNumber = '#MM-'.mt_rand(10000, 99999);
            }

            $deliveryAddress = $validated['delivery_address'] ?? null;
            $defaultLocation = $fulfillmentType === 'Home Delivery'
                ? ($deliveryAddress ?: 'Delivery Address')
                : ($storeInfo->address.' · '.$storeInfo->name);

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
                'fulfillment_type' => $fulfillmentType,
                'pickup_slot' => $pickupSlot,
                'pickup_location' => $validated['pickup_location'] ?? $defaultLocation,
                'delivery_address' => $deliveryAddress,
                'status' => 'confirmed',
                'notes' => $validated['notes'] ?? null,
            ]);

            foreach ($resolvedItems as $item) {
                $catalogItem = $item['catalog_item'];
                $quantity = (int) $item['quantity'];
                $productId = $catalogItem instanceof Product ? $catalogItem->id : null;

                if ($catalogItem instanceof Product) {
                    $catalogItem->decrementStock($quantity);
                } else {
                    foreach ($catalogItem->products as $kitProduct) {
                        $kitProduct->decrementStock(($kitProduct->pivot->quantity ?? 1) * $quantity);
                    }
                }

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

        $order->load(['items', 'user']);

        // Dispatch real-time WebSocket broadcast event via Laravel Reverb
        OrderPlaced::dispatch($order);

        if ($request->wantsJson()) {
            return response()->json([
                'success' => true,
                'order' => $order,
            ], 201);
        }

        return redirect()->route('account')->with('success', "Order {$order->order_number} confirmed!");
    }

    /**
     * Build a fulfillment label from validated choices and current store settings.
     *
     * @param  array<string, mixed>  $data
     */
    private function pickupSlot(array $data, StoreSetting $storeInfo, string $fulfillmentType): string
    {
        if ($fulfillmentType === 'Home Delivery') {
            return 'Home Delivery · '.$storeInfo->delivery_estimated_time;
        }

        if (! $storeInfo->is_pickup_active) {
            throw ValidationException::withMessages(['pickup_slot' => 'Store pickup is currently paused.']);
        }

        if (($data['pickup_timing_mode'] ?? 'asap') === 'asap') {
            return "ASAP (Ready in ~{$storeInfo->effective_prep_time_minutes} mins)";
        }

        $now = now();
        $date = $data['pickup_date'];
        if (! in_array($date, [$now->toDateString(), $now->copy()->addDay()->toDateString()], true)) {
            throw ValidationException::withMessages(['pickup_date' => 'Choose today or tomorrow for pickup.']);
        }

        $time = $data['pickup_time'] ?? '';
        $label = '';
        if ($data['pickup_timing_type'] === 'slot') {
            $slot = collect($storeInfo->available_pickup_slots)->firstWhere('label', $data['pickup_slot'] ?? '');
            if (! $slot || ! preg_match('/^(\d{1,2}):(\d{2}) (AM|PM)/', $slot['label'], $parts)) {
                throw ValidationException::withMessages(['pickup_slot' => 'Choose an available pickup window.']);
            }
            $hour = ((int) $parts[1] % 12) + ($parts[3] === 'PM' ? 12 : 0);
            $time = sprintf('%02d:%02d', $hour, (int) $parts[2]);
            $label = $slot['label'];
        }

        if ($time === '') {
            throw ValidationException::withMessages(['pickup_time' => 'Choose a pickup time.']);
        }

        $pickupAt = $now->copy()->setDateFrom($date)->setTimeFromTimeString($time.':00');
        $start = $storeInfo->pickup_slot_start_time ?? '09:00';
        $end = $storeInfo->pickup_slot_end_time ?? '21:00';
        if ($time < $start || $time > $end || $pickupAt->lt($now->copy()->addMinutes($storeInfo->effective_prep_time_minutes))) {
            throw ValidationException::withMessages(['pickup_time' => 'Choose a future pickup time within store hours, allowing time for preparation.']);
        }

        return $date.' · '.($label ?: $pickupAt->format('g:i A').' (Custom Time)');
    }

    /**
     * Display the specified order for customer live tracking.
     */
    public function show(string $orderNumber): Response
    {
        $hashNumber = str_starts_with($orderNumber, '#') ? $orderNumber : '#'.$orderNumber;
        $order = Order::with('items')
            ->where('order_number', $orderNumber)
            ->orWhere('order_number', $hashNumber)
            ->firstOrFail();

        return Inertia::render('OrderTracking', [
            'order' => [
                'id' => $order->id,
                'order_number' => $order->order_number,
                'customer_name' => $order->customer_name,
                'customer_email' => $order->customer_email,
                'customer_phone' => $order->customer_phone,
                'subtotal' => (float) $order->subtotal,
                'discount' => (float) $order->discount,
                'total' => (float) $order->total,
                'points_earned' => $order->points_earned,
                'payment_method' => $order->payment_method,
                'fulfillment_type' => $order->fulfillment_type,
                'pickup_slot' => $order->pickup_slot,
                'pickup_location' => $order->pickup_location,
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
