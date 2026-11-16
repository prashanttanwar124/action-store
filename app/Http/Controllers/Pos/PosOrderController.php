<?php

namespace App\Http\Controllers\Pos;

use App\Http\Controllers\Controller;
use App\Http\Requests\Pos\StorePosOrderRequest;
use App\Models\Admin;
use App\Models\Order;
use App\Models\PosShift;
use App\Models\Product;
use App\Services\PosOrderService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class PosOrderController extends Controller
{
    /**
     * Render the in-store POS Register interface with category tiles, shift, and staff switcher data.
     */
    public function index(Request $request): Response
    {
        return Inertia::render('Pos/Register', self::registerPageProps($request));
    }

    /**
     * Finalize a counter POS sale and return receipt, shift, loyalty, and stock deltas.
     */
    public function store(StorePosOrderRequest $request, PosOrderService $service): JsonResponse|RedirectResponse
    {
        /** @var Admin $cashier */
        $cashier = $request->user('admin');

        $order = $service->createOrder($cashier, $request->validated());
        $statusCode = $order->wasRecentlyCreated ? 201 : 200;

        $productIds = $order->items
            ->pluck('product_id')
            ->filter()
            ->unique()
            ->values()
            ->all();

        $productStocks = $productIds !== []
            ? Product::whereIn('id', $productIds)
                ->pluck('stock', 'id')
                ->map(fn ($stock): int => (int) $stock)
                ->all()
            : [];

        if ($request->wantsJson()) {
            return response()->json([
                'success' => true,
                'idempotent_replay' => ! $order->wasRecentlyCreated,
                'order' => PosOrderService::formatOrder($order),
                'shift' => $order->posShift ? PosShiftController::formatShift($order->posShift) : null,
                'customer' => $order->user ? PosCustomerController::formatCustomer($order->user) : null,
                'product_stocks' => $productStocks,
            ], $statusCode);
        }

        return back()->with('success', "Order {$order->order_number} completed.");
    }

    /**
     * Assemble shared POS Register / Orders page props without preloading all catalog rows.
     *
     * @return array<string, mixed>
     */
    public static function registerPageProps(Request $request, string $defaultTab = 'register'): array
    {
        $catalogOverview = PosCatalogController::buildCatalogOverview();

        $openShift = PosShift::currentOpen();

        $cashiers = Admin::with('roles')
            ->orderBy('name')
            ->get()
            ->filter(static fn (Admin $staff): bool => $staff->can('operate pos'))
            ->map(static fn (Admin $staff): array => [
                'id' => $staff->id,
                'name' => $staff->name,
                'email' => $staff->email,
                'has_pos_pin' => $staff->hasPosPin(),
                'roles' => $staff->getRoleNames()->values()->all(),
            ])
            ->values()
            ->all();

        $searchQuery = trim((string) $request->input('q', ''));
        $ordersQuery = Order::with(['items', 'admin:id,name,email', 'user:id,name,email,phone,reward_points'])
            ->latest();

        if ($searchQuery !== '') {
            PosReturnController::applyOrderSearch($ordersQuery, $searchQuery);
        }

        $recentOrders = $ordersQuery
            ->limit(25)
            ->get()
            ->map(static fn (Order $order): array => PosOrderService::formatOrder($order))
            ->values()
            ->all();

        return [
            'categories' => $catalogOverview['categories'],
            'catalogSummary' => $catalogOverview['summary'],
            'currentShift' => $openShift ? PosShiftController::formatShift($openShift) : null,
            'cashiers' => $cashiers,
            'loyaltyConfig' => config('pos.loyalty', [
                'points_per_block' => 500,
                'discount_per_block' => 10.00,
            ]),
            'drawerConfig' => config('pos.drawer', [
                'safe_drop_alert_threshold' => 1000.00,
            ]),
            'recentOrders' => $recentOrders,
            'initialTab' => $request->input('tab') === 'orders' ? 'orders' : $defaultTab,
            'initialOrderSearch' => $searchQuery,
        ];
    }
}
