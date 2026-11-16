<?php

namespace App\Http\Controllers\Pos;

use App\Http\Controllers\Controller;
use App\Models\Admin;
use App\Models\Order;
use App\Models\Product;
use App\Services\PosOrderService;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class PosReturnController extends Controller
{
    /**
     * Search POS and store orders by receipt/order number, phone, customer name, or email.
     */
    public function index(Request $request): JsonResponse|Response
    {
        if (! $request->wantsJson()) {
            return Inertia::render('Pos/Orders', PosOrderController::registerPageProps($request, 'orders'));
        }

        $search = trim((string) $request->input('q', ''));
        $channel = (string) $request->input('channel', 'all');

        $query = Order::with(['items', 'admin:id,name,email', 'user:id,name,email,phone,reward_points'])
            ->latest();

        if (in_array($channel, ['pos', 'online'], true)) {
            $query->where('channel', $channel);
        }

        if ($search !== '') {
            self::applyOrderSearch($query, $search);
        }

        $orders = $query
            ->limit(30)
            ->get()
            ->map(static fn (Order $order): array => PosOrderService::formatOrder($order))
            ->values()
            ->all();

        return response()->json([
            'orders' => $orders,
        ]);
    }

    /**
     * Process a partial or full POS return with per-item Return-to-Stock vs Waste disposition.
     */
    public function refund(Request $request, Order $order, PosOrderService $service): JsonResponse|RedirectResponse
    {
        $validated = $request->validate([
            'items' => ['required', 'array', 'min:1'],
            'items.*.order_item_id' => ['required', 'integer', 'exists:order_items,id'],
            'items.*.quantity' => ['required', 'integer', 'min:1', 'max:9999'],
            'items.*.restock' => ['required', 'boolean'],
            'refund_method' => ['required', 'string', 'in:cash,pos_terminal'],
            'reason' => ['nullable', 'string', 'max:160'],
        ]);

        /** @var Admin $cashier */
        $cashier = $request->user('admin');

        $result = $service->refundOrderItems($cashier, $order, $validated);

        $productStocks = $result['restocked_product_ids'] !== []
            ? Product::whereIn('id', $result['restocked_product_ids'])
                ->pluck('stock', 'id')
                ->map(fn ($stock): int => (int) $stock)
                ->all()
            : [];

        if ($request->wantsJson()) {
            return response()->json([
                'success' => true,
                'refund_amount' => $result['refund_amount'],
                'order' => PosOrderService::formatOrder($result['order']),
                'shift' => $result['shift'] ? PosShiftController::formatShift($result['shift']) : null,
                'customer' => $result['order']->user ? PosCustomerController::formatCustomer($result['order']->user) : null,
                'product_stocks' => $productStocks,
            ]);
        }

        return back()->with('success', sprintf('Refunded $%.2f on order %s.', $result['refund_amount'], $order->order_number));
    }

    /**
     * Apply receipt number, phone, customer name, or email search constraints to an Order query.
     *
     * @param  Builder<Order>  $query
     */
    public static function applyOrderSearch(Builder $query, string $search): void
    {
        $digitsOnly = preg_replace('/\D+/', '', $search) ?? '';

        $query->where(static function (Builder $q) use ($search, $digitsOnly): void {
            $q->where('order_number', 'like', "%{$search}%")
                ->orWhere('customer_phone', 'like', "%{$search}%")
                ->orWhere('customer_name', 'like', "%{$search}%")
                ->orWhere('customer_email', 'like', "%{$search}%");

            if (strlen($digitsOnly) >= 4 && $digitsOnly !== $search) {
                $q->orWhereRaw(
                    "REPLACE(REPLACE(REPLACE(REPLACE(REPLACE(COALESCE(customer_phone, ''), '-', ''), ' ', ''), '(', ''), ')', ''), '+', '') LIKE ?",
                    ["%{$digitsOnly}%"],
                );
            }
        });
    }
}
