<?php

namespace App\Http\Controllers;

use App\Models\Order;
use App\Models\RecipeKit;
use Illuminate\Support\Facades\Auth;
use Inertia\Inertia;
use Inertia\Response;

class AccountController extends Controller
{
    private const ORDERS_PER_PAGE = 10;

    /**
     * Display the customer account dashboard.
     */
    public function index(): Response
    {
        return Inertia::render('Account', $this->getUserOrdersPayload());
    }

    /**
     * Display the customer account with reorder section active.
     */
    public function reorder(): Response
    {
        return Inertia::render('Account', [
            'activeSection' => 'reorder',
            ...$this->getUserOrdersPayload(),
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

        $kitNames = $orders
            ->flatMap(fn (Order $order) => $order->items)
            ->filter(fn ($item) => empty($item->product_id))
            ->pluck('name')
            ->filter()
            ->unique()
            ->values();

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
                'items' => $order->items->map(function ($item) use ($recipeKitsByName) {
                    $isKit = empty($item->product_id);
                    $kit = $isKit ? $recipeKitsByName->get($item->name) : null;

                    $kitId = $kit?->id ?? $item->id;
                    $id = $isKit
                        ? "kit-{$kitId}"
                        : ($item->product_id ?? $item->id);

                    return [
                        'id' => $id,
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
}
