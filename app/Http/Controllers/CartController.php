<?php

namespace App\Http\Controllers;

use App\Models\Order;
use App\Models\Product;
use App\Models\StoreSetting;
use Inertia\Inertia;
use Inertia\Response;

class CartController extends Controller
{
    /**
     * Display the shopping cart.
     */
    public function index(): Response
    {
        $impulseItems = Product::query()
            ->whereIn('slug', ['coriander', 'saunf-mints', 'green-chillies', 'masala-noodles'])
            ->get();

        return Inertia::render('Cart', [
            'impulseItems' => $impulseItems,
        ]);
    }

    /**
     * Display the checkout page.
     */
    public function checkout(): Response
    {
        $tz = config('app.timezone');
        $today = now($tz)->toDateString();
        $tomorrow = now($tz)->addDay()->toDateString();
        $storeInfo = StoreSetting::current();
        $maxCapacity = (int) ($storeInfo->max_orders_per_slot ?? 0);

        $bookedSlots = [];
        if ($maxCapacity > 0) {
            $forTodayOrTomorrow = function ($q) use ($today, $tomorrow) {
                $q->where('pickup_slot', 'LIKE', "{$today}%")
                    ->orWhere('pickup_slot', 'LIKE', "{$tomorrow}%");
            };

            // Placed orders holding a slot
            $slotStrings = Order::query()
                ->where($forTodayOrTomorrow)
                ->where('status', '!=', 'cancelled')
                ->pluck('pickup_slot');

            foreach ($slotStrings as $slotString) {
                $parts = explode(' · ', $slotString, 2);
                if (count($parts) === 2) {
                    $d = trim($parts[0]);
                    $l = trim($parts[1]);
                    $bookedSlots[$d][$l] = ($bookedSlots[$d][$l] ?? 0) + 1;
                }
            }
        }

        return Inertia::render('Checkout', [
            'storeTimezone' => $tz,
            'bookedSlots' => $bookedSlots,
            'maxOrdersPerSlot' => $maxCapacity,
            'minOrderAmount' => (float) ($storeInfo->min_order_amount ?? 0),
            'pickupDays' => $storeInfo->pickup_days ?? ['monday', 'tuesday', 'wednesday', 'thursday', 'friday', 'saturday', 'sunday'],
            'stripeKey' => config('services.stripe.key') ?? '',
            'stripeConfigured' => ! empty(config('services.stripe.key')),
        ]);
    }
}
