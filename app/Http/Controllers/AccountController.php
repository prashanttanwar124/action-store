<?php

namespace App\Http\Controllers;

use App\Models\Order;
use App\Models\Product;
use App\Models\RecipeKit;
use App\Models\RewardPoint;
use Illuminate\Support\Facades\Auth;
use Inertia\Inertia;
use Inertia\Response;

class AccountController extends Controller
{
    private const ORDERS_PER_PAGE = 2;

    /**
     * Display the customer account dashboard.
     */
    public function index(): Response
    {
        return Inertia::render('Account', [
            'activeSection' => 'account',
            ...$this->getUserOrdersPayload(),
            'activeOrders' => $this->getActiveOrders(),
            'rewardLedger' => $this->getRewardLedger(),
            'buyAgainItems' => array_slice($this->getBuyAgainItems(), 0, 4),
        ]);
    }

    /**
     * Display the customer account with reorder ("Buy It Again") section active.
     */
    public function reorder(): Response
    {
        $buyAgainItems = $this->getBuyAgainItems();

        return Inertia::render('Account', [
            'activeSection' => 'reorder',
            ...$this->getUserOrdersPayload(),
            'buyAgainItems' => $buyAgainItems,
            'popularEssentials' => empty($buyAgainItems) ? $this->getPopularEssentials() : [],
        ]);
    }

    /**
     * @return array{dbOrders: list<array<string, mixed>>, ordersPagination: array{current_page: int, last_page: int, per_page: int, total: int, has_more: bool, next_page_url: string|null}}
     */
    private function getUserOrdersPayload(): array
    {
        if (! Auth::check()) {
            return [
                'dbOrders' => [],
                'ordersPagination' => [
                    'current_page' => 1,
                    'last_page' => 1,
                    'per_page' => self::ORDERS_PER_PAGE,
                    'total' => 0,
                    'has_more' => false,
                    'next_page_url' => null,
                ],
            ];
        }

        $paginator = Order::with('items')
            ->where('user_id', Auth::id())
            ->latest()
            ->paginate(self::ORDERS_PER_PAGE)
            ->withQueryString();

        $orders = $paginator->getCollection();

        $kitItems = $orders
            ->flatMap(fn (Order $order) => $order->items)
            ->filter(fn ($item) => ! empty($item->recipe_kit_id) || empty($item->product_id));

        $kitIds = $kitItems
            ->pluck('recipe_kit_id')
            ->filter()
            ->unique()
            ->values();

        $kitNames = $kitItems
            ->filter(fn ($item) => empty($item->recipe_kit_id))
            ->pluck('name')
            ->filter()
            ->unique()
            ->values();

        $recipeKitsById = $kitIds->isEmpty()
            ? collect()
            : RecipeKit::whereIn('id', $kitIds)
                ->get(['id', 'name', 'slug'])
                ->keyBy('id');

        $recipeKitsByName = $kitNames->isEmpty()
            ? collect()
            : RecipeKit::whereIn('name', $kitNames)
                ->get(['id', 'name', 'slug'])
                ->keyBy('name');

        $dbOrders = $orders
            ->map(fn (Order $order) => [
                'id' => $order->order_number,
                'status' => $order->status,
                'date' => $order->created_at->diffForHumans(),
                'type' => $order->fulfillment_type,
                'total' => (float) $order->total,
                'refunded_total' => (float) ($order->refunded_total ?? 0),
                'itemCount' => $order->items->sum('quantity'),
                'summary' => $order->items->pluck('name')->join(', '),
                'items' => $order->items->map(function ($item) use ($recipeKitsById, $recipeKitsByName) {
                    $isKit = ! empty($item->recipe_kit_id) || empty($item->product_id);
                    $kit = null;
                    if ($isKit) {
                        if (! empty($item->recipe_kit_id)) {
                            $kit = $recipeKitsById->get((int) $item->recipe_kit_id);
                        } else {
                            $kit = $recipeKitsByName->get($item->name);
                        }
                    }

                    $resolvedKitId = $kit?->id ?? $item->recipe_kit_id ?? $item->id;
                    $id = $isKit
                        ? "kit-{$resolvedKitId}"
                        : ($item->product_id ?? $item->id);

                    return [
                        'id' => $id,
                        'recipe_kit_id' => ($kit?->id ?? $item->recipe_kit_id) ? (int) ($kit?->id ?? $item->recipe_kit_id) : null,
                        'slug' => $kit?->slug ?? null,
                        'type' => $isKit ? 'recipe-kit' : 'product',
                        'isRecipeKit' => $isKit,
                        'name' => $item->name,
                        'price' => (float) $item->unit_price,
                        'quantity' => (int) $item->quantity,
                        'refunded_quantity' => (int) ($item->refunded_quantity ?? 0),
                        'refunded_amount' => (float) ($item->refunded_amount ?? 0),
                        'refund_reason' => $item->refund_reason,
                        'size' => $item->size,
                        'image' => $item->image,
                    ];
                })->all(),
            ])
            ->values()
            ->all();

        return [
            'dbOrders' => $dbOrders,
            'ordersPagination' => [
                'current_page' => $paginator->currentPage(),
                'last_page' => $paginator->lastPage(),
                'per_page' => $paginator->perPage(),
                'total' => $paginator->total(),
                'has_more' => $paginator->hasMorePages(),
                'next_page_url' => $paginator->nextPageUrl(),
            ],
        ];
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function getUserOrders(): array
    {
        return $this->getUserOrdersPayload()['dbOrders'];
    }

    /**
     * Get currently active in-progress pickup orders for the authenticated customer.
     *
     * @return list<array<string, mixed>>
     */
    private function getActiveOrders(): array
    {
        if (! Auth::check()) {
            return [];
        }

        return Order::with('items')
            ->where('user_id', Auth::id())
            ->whereIn('status', ['confirmed', 'packing', 'ready_for_pickup'])
            ->latest()
            ->limit(5)
            ->get()
            ->map(fn (Order $order) => [
                'id' => $order->order_number,
                'status' => $order->status,
                'type' => $order->fulfillment_type,
                'pickup_slot' => $order->pickup_slot,
                'pickup_location' => $order->pickup_location,
                'date' => $order->created_at->diffForHumans(),
                'total' => (float) max(0, (float) $order->total - (float) ($order->refunded_total ?? 0)),
                'itemCount' => (int) $order->items->sum('quantity'),
                'summary' => $order->items->pluck('name')->join(', '),
            ])
            ->values()
            ->all();
    }

    /**
     * Get the recent reward points ledger entries for the authenticated customer.
     *
     * @return list<array<string, mixed>>
     */
    private function getRewardLedger(): array
    {
        if (! Auth::check()) {
            return [];
        }

        return RewardPoint::with('order:id,order_number')
            ->where('user_id', Auth::id())
            ->latest()
            ->limit(15)
            ->get()
            ->map(fn (RewardPoint $entry) => [
                'id' => $entry->id,
                'points' => (int) $entry->points,
                'description' => $entry->description ?: 'Points activity',
                'order_number' => $entry->order?->order_number,
                'date' => $entry->created_at?->diffForHumans() ?? 'Recently',
            ])
            ->values()
            ->all();
    }

    /**
     * Aggregate unique items from the customer's past non-cancelled orders, sorted by order frequency.
     *
     * @return list<array<string, mixed>>
     */
    private function getBuyAgainItems(): array
    {
        if (! Auth::check()) {
            return [];
        }

        $orders = Order::with('items')
            ->where('user_id', Auth::id())
            ->where('status', '!=', 'cancelled')
            ->latest()
            ->limit(30)
            ->get();

        if ($orders->isEmpty()) {
            return [];
        }

        $allItems = $orders->flatMap(fn (Order $order) => $order->items->map(fn ($item) => [
            'model' => $item,
            'ordered_at' => $order->created_at,
        ]));

        $productIds = $allItems
            ->map(fn (array $row) => $row['model']->product_id)
            ->filter()
            ->unique()
            ->values();

        $kitIds = $allItems
            ->map(fn (array $row) => $row['model']->recipe_kit_id)
            ->filter()
            ->unique()
            ->values();

        $kitNames = $allItems
            ->filter(fn (array $row) => empty($row['model']->product_id) && empty($row['model']->recipe_kit_id))
            ->map(fn (array $row) => $row['model']->name)
            ->filter()
            ->unique()
            ->values();

        $productsById = $productIds->isEmpty()
            ? collect()
            : Product::whereIn('id', $productIds)->get()->keyBy('id');

        $kitsById = $kitIds->isEmpty()
            ? collect()
            : RecipeKit::with('products')->whereIn('id', $kitIds)->get()->keyBy('id');

        $kitsByName = $kitNames->isEmpty()
            ? collect()
            : RecipeKit::with('products')->whereIn('name', $kitNames)->get()->keyBy('name');

        $aggregated = [];

        foreach ($allItems as $row) {
            $item = $row['model'];
            $orderedAt = $row['ordered_at'];
            $netQty = max(0, (int) $item->quantity - (int) ($item->refunded_quantity ?? 0));
            if ($netQty <= 0) {
                continue;
            }

            $isKit = ! empty($item->recipe_kit_id) || empty($item->product_id);

            if ($isKit) {
                $kit = ! empty($item->recipe_kit_id)
                    ? $kitsById->get((int) $item->recipe_kit_id)
                    : $kitsByName->get($item->name);

                $resolvedKitId = $kit?->id ?? $item->recipe_kit_id ?? $item->id;
                $key = "kit-{$resolvedKitId}";

                if (! isset($aggregated[$key])) {
                    $isActive = $kit ? (bool) $kit->is_active : false;
                    $aggregated[$key] = [
                        'id' => $key,
                        'recipe_kit_id' => $kit?->id ? (int) $kit->id : ($item->recipe_kit_id ? (int) $item->recipe_kit_id : null),
                        'slug' => $kit?->slug,
                        'type' => 'recipe-kit',
                        'isRecipeKit' => true,
                        'is_active' => $isActive,
                        'name' => $kit?->name ?? $item->name,
                        'category' => 'recipe-kit',
                        'category_title' => 'Recipe Kits',
                        'price' => (float) ($kit?->price ?? $item->unit_price),
                        'original_price' => (float) ($kit?->original_price ?? $kit?->price ?? $item->unit_price),
                        'size' => $kit?->servings ?? $item->size ?? 'Recipe Kit',
                        'image' => $kit?->image ?? $item->image ?? '/images/products/paneer_curry.jpg',
                        'stock' => $isActive ? 20 : 0,
                        'stock_badge' => $isActive ? 'In stock' : 'Out of stock',
                        'products' => $kit && $kit->relationLoaded('products') ? $kit->products->toArray() : [],
                        'times_ordered' => 0,
                        'total_quantity' => 0,
                        'last_ordered_human' => $orderedAt?->diffForHumans() ?? 'Recently',
                        'last_ordered_ts' => $orderedAt?->getTimestamp() ?? 0,
                    ];
                }
            } else {
                $product = $productsById->get((int) $item->product_id);
                $resolvedProdId = (int) ($product?->id ?? $item->product_id ?? $item->id);
                $key = "prod-{$resolvedProdId}";

                if (! isset($aggregated[$key])) {
                    $stock = $product ? (int) ($product->stock ?? 0) : 0;
                    $aggregated[$key] = [
                        'id' => $resolvedProdId,
                        'slug' => $product?->slug,
                        'type' => 'product',
                        'isRecipeKit' => false,
                        'name' => $product?->name ?? $item->name,
                        'category' => $product?->category ?? 'staples',
                        'category_title' => $product?->category_title ?? ucfirst((string) ($product?->category ?? 'Staples')),
                        'price' => (float) ($product?->price ?? $item->unit_price),
                        'original_price' => (float) ($product?->original_price ?? $product?->price ?? $item->unit_price),
                        'size' => $product?->size_main ?? $item->size ?? '',
                        'image' => $product?->image ?? $item->image ?? '/images/products/atta.jpg',
                        'stock' => $stock,
                        'stock_badge' => $product ? $product->stock_badge : 'Out of stock',
                        'times_ordered' => 0,
                        'total_quantity' => 0,
                        'last_ordered_human' => $orderedAt?->diffForHumans() ?? 'Recently',
                        'last_ordered_ts' => $orderedAt?->getTimestamp() ?? 0,
                    ];
                }
            }

            $aggregated[$key]['times_ordered'] += 1;
            $aggregated[$key]['total_quantity'] += $netQty;
        }

        return collect($aggregated)
            ->sort(function (array $a, array $b) {
                if ($a['times_ordered'] !== $b['times_ordered']) {
                    return $b['times_ordered'] <=> $a['times_ordered'];
                }

                return $b['last_ordered_ts'] <=> $a['last_ordered_ts'];
            })
            ->take(16)
            ->values()
            ->all();
    }

    /**
     * Get popular store essentials for guests or new customers with no past orders yet.
     *
     * @return list<array<string, mixed>>
     */
    private function getPopularEssentials(): array
    {
        return Product::where('stock', '>', 0)
            ->orderByDesc('buy_again')
            ->orderBy('id')
            ->limit(8)
            ->get()
            ->map(fn (Product $product) => [
                'id' => $product->id,
                'slug' => $product->slug,
                'type' => 'product',
                'isRecipeKit' => false,
                'name' => $product->name,
                'category' => $product->category ?? 'staples',
                'category_title' => $product->category_title ?? ucfirst((string) ($product->category ?? 'Staples')),
                'price' => (float) $product->price,
                'original_price' => (float) ($product->original_price ?? $product->price),
                'size' => $product->size_main ?? '',
                'image' => $product->image ?? '/images/products/atta.jpg',
                'stock' => (int) $product->stock,
                'stock_badge' => $product->stock_badge,
            ])
            ->values()
            ->all();
    }
}
