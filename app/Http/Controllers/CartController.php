<?php

namespace App\Http\Controllers;

use App\Models\Order;
use App\Models\Product;
use App\Models\RecipeKit;
use App\Models\StoreSetting;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
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
     * Check live stock and pricing only for the specific product/kit IDs present in the customer's cart.
     */
    public function stockCheck(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'ids' => ['required', 'array', 'max:50'],
            'ids.*' => ['required', 'string', 'max:120'],
        ]);

        $numericIds = [];
        $slugs = [];
        $kitNumericIds = [];
        $kitSlugs = [];

        foreach ($validated['ids'] as $rawId) {
            if (str_starts_with($rawId, 'kit-')) {
                $cleanKit = substr($rawId, 4);
                if (is_numeric($cleanKit)) {
                    $kitNumericIds[] = (int) $cleanKit;
                } else {
                    $kitSlugs[] = $cleanKit;
                }
            } elseif (is_numeric($rawId)) {
                $numericIds[] = (int) $rawId;
                $kitNumericIds[] = (int) $rawId;
            } else {
                $slugs[] = $rawId;
                $kitSlugs[] = $rawId;
            }
        }

        $products = collect();
        if (! empty($numericIds) || ! empty($slugs)) {
            $products = Product::query()
                ->select(['id', 'slug', 'name', 'price', 'original_price', 'size_main', 'image', 'stock', 'stock_badge', 'category'])
                ->where(function ($query) use ($numericIds, $slugs) {
                    if (! empty($numericIds)) {
                        $query->whereIn('id', array_unique($numericIds));
                    }
                    if (! empty($slugs)) {
                        $query->orWhereIn('slug', array_unique($slugs));
                    }
                })
                ->limit(50)
                ->get();
        }

        $recipeKits = collect();
        if (! empty($kitNumericIds) || ! empty($kitSlugs)) {
            $recipeKits = RecipeKit::query()
                ->with(['products:id,slug,name,price,size_main,image,stock,stock_badge'])
                ->where('is_active', true)
                ->where(function ($query) use ($kitNumericIds, $kitSlugs) {
                    if (! empty($kitNumericIds)) {
                        $query->whereIn('id', array_unique($kitNumericIds));
                    }
                    if (! empty($kitSlugs)) {
                        $query->orWhereIn('slug', array_unique($kitSlugs));
                    }
                })
                ->limit(25)
                ->get();
        }

        return response()->json([
            'products' => $products,
            'recipeKits' => $recipeKits,
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
