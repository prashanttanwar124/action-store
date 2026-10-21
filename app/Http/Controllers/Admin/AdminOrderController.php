<?php

namespace App\Http\Controllers\Admin;

use App\Events\OrderItemsPackedUpdated;
use App\Events\OrderStatusUpdated;
use App\Http\Controllers\Controller;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Product;
use App\Services\StripePayments;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;

class AdminOrderController extends Controller
{
    /**
     * Display a listing of orders with real-time stats and filters.
     */
    public function index(Request $request): Response
    {
        $status = $request->input('status', 'all');
        $search = $request->input('search');

        $query = Order::with('items')
            ->orderByRaw("CASE status
                WHEN 'confirmed' THEN 1
                WHEN 'packing' THEN 2
                WHEN 'ready_for_pickup' THEN 3
                WHEN 'completed' THEN 4
                WHEN 'cancelled' THEN 5
                ELSE 6 END ASC")
            ->latest();

        if ($status && $status !== 'all') {
            $query->where('status', $status);
        }

        if ($search) {
            $query->where(function ($q) use ($search) {
                $q->where('order_number', 'like', "%{$search}%")
                    ->orWhere('customer_name', 'like', "%{$search}%")
                    ->orWhere('customer_phone', 'like', "%{$search}%")
                    ->orWhere('customer_email', 'like', "%{$search}%");
            });
        }

        $orders = $query->paginate(48)->withQueryString()->through(function ($order) {
            return [
                'id' => $order->id,
                'order_number' => $order->order_number,
                'customer_name' => $order->customer_name,
                'customer_email' => $order->customer_email,
                'customer_phone' => $order->customer_phone,
                'subtotal' => (float) $order->subtotal,
                'discount' => (float) $order->discount,
                'total' => (float) $order->total,
                'refunded_total' => (float) ($order->refunded_total ?? 0),
                'points_earned' => $order->points_earned,
                'payment_method' => $order->payment_method,
                'stripe_payment_id' => $order->stripe_payment_id,
                'fulfillment_type' => $order->fulfillment_type,
                'pickup_slot' => $order->pickup_slot,
                'pickup_location' => $order->pickup_location,
                'delivery_address' => $order->delivery_address,
                'delivery_fee' => (float) $order->delivery_fee,
                'status' => $order->status,
                'notes' => $order->notes,
                'created_at' => $order->created_at?->toISOString(),
                'created_at_human' => $order->created_at?->diffForHumans(),
                'items_count' => $order->items->count(),
                'items' => $order->items->map(function ($item) {
                    return [
                        'id' => $item->id,
                        'product_id' => $item->product_id,
                        'name' => $item->name,
                        'size' => $item->size,
                        'unit_price' => (float) $item->unit_price,
                        'quantity' => (int) $item->quantity,
                        'refunded_quantity' => (int) ($item->refunded_quantity ?? 0),
                        'total_price' => (float) $item->total_price,
                        'refunded_amount' => (float) ($item->refunded_amount ?? 0),
                        'refund_reason' => $item->refund_reason,
                        'is_packed' => (bool) $item->is_packed,
                        'image' => $item->image,
                    ];
                }),
            ];
        });

        // Compute live KDS summary metrics in a single aggregate SQL query
        $todayStart = Carbon::today()->startOfDay();
        $todayEnd = Carbon::today()->endOfDay();

        $agg = Order::query()
            ->selectRaw("
                COUNT(*) as total_all,
                SUM(CASE WHEN created_at BETWEEN ? AND ? THEN 1 ELSE 0 END) as total_today,
                SUM(CASE WHEN status = 'confirmed' THEN 1 ELSE 0 END) as confirmed,
                SUM(CASE WHEN status = 'packing' THEN 1 ELSE 0 END) as packing,
                SUM(CASE WHEN status = 'ready_for_pickup' THEN 1 ELSE 0 END) as ready_for_pickup,
                SUM(CASE WHEN status = 'completed' THEN 1 ELSE 0 END) as completed,
                SUM(CASE WHEN created_at BETWEEN ? AND ? AND status = 'completed' THEN 1 ELSE 0 END) as completed_today,
                SUM(CASE WHEN status = 'cancelled' THEN 1 ELSE 0 END) as cancelled,
                SUM(CASE WHEN created_at BETWEEN ? AND ? AND status = 'cancelled' THEN 1 ELSE 0 END) as cancelled_today,
                COALESCE(SUM(CASE WHEN created_at BETWEEN ? AND ? AND status != 'cancelled' THEN total - COALESCE(refunded_total, 0) ELSE 0 END), 0) as revenue_today
            ", [
                $todayStart, $todayEnd,
                $todayStart, $todayEnd,
                $todayStart, $todayEnd,
                $todayStart, $todayEnd,
            ])
            ->first();

        $stats = [
            'total_all' => (int) ($agg->total_all ?? 0),
            'total_today' => (int) ($agg->total_today ?? 0),
            'confirmed' => (int) ($agg->confirmed ?? 0),
            'packing' => (int) ($agg->packing ?? 0),
            'ready_for_pickup' => (int) ($agg->ready_for_pickup ?? 0),
            'completed' => (int) ($agg->completed ?? 0),
            'completed_today' => (int) ($agg->completed_today ?? 0),
            'cancelled' => (int) ($agg->cancelled ?? 0),
            'cancelled_today' => (int) ($agg->cancelled_today ?? 0),
            'revenue_today' => (float) ($agg->revenue_today ?? 0),
        ];

        return Inertia::render('Admin/Orders/Index', [
            'orders' => $orders,
            'stats' => $stats,
            'filters' => [
                'status' => $status,
                'search' => $search,
            ],
        ]);
    }

    /**
     * Update the status of an order and broadcast via Laravel Reverb.
     */
    public function updateStatus(Request $request, Order $order, StripePayments $payments): JsonResponse|RedirectResponse
    {
        $validated = $request->validate([
            'status' => ['required', 'string', 'in:confirmed,packing,ready_for_pickup,completed,cancelled'],
        ]);

        $newStatus = $validated['status'];

        if ($newStatus === 'cancelled') {
            $this->cancelOrder($order, $payments);
        } else {
            DB::transaction(function () use ($order, $newStatus) {
                $lockedOrder = Order::whereKey($order->id)->lockForUpdate()->firstOrFail();

                if ($lockedOrder->status === 'completed' && $newStatus !== 'completed') {
                    throw ValidationException::withMessages([
                        'status' => 'Completed orders cannot be modified or reopened.',
                    ]);
                }

                if ($lockedOrder->status === 'cancelled' && $newStatus !== 'cancelled') {
                    throw ValidationException::withMessages([
                        'status' => 'A cancelled order cannot be reopened: its stock was returned and any payment refunded.',
                    ]);
                }

                $lockedOrder->update(['status' => $newStatus]);
            });

            $order->refresh();
        }

        // Broadcast real-time status update to both Admin KDS and Customer tracking screens
        try {
            OrderStatusUpdated::dispatch($order);
        } catch (\Throwable $e) {
            Log::warning("OrderStatusUpdated broadcast failed for {$order->order_number}: {$e->getMessage()}");
        }

        if ($request->wantsJson()) {
            return response()->json([
                'success' => true,
                'order' => $order,
                'message' => "Order {$order->order_number} status updated to ".str_replace('_', ' ', $validated['status']),
            ]);
        }

        return back()->with('success', "Order {$order->order_number} status updated to ".str_replace('_', ' ', $validated['status']));
    }

    /**
     * Persist single or bulk item packing state in the database and broadcast to all KDS screens.
     */
    public function updateItemsPacked(Request $request, Order $order): JsonResponse
    {
        $validated = $request->validate([
            'item_id' => ['nullable', 'integer'],
            'is_packed' => ['required', 'boolean'],
        ]);

        if (! empty($validated['item_id'])) {
            $order->items()
                ->whereKey($validated['item_id'])
                ->update(['is_packed' => $validated['is_packed']]);
        } else {
            $order->items()
                ->whereRaw('COALESCE(refunded_quantity, 0) < quantity')
                ->update(['is_packed' => $validated['is_packed']]);
        }

        $order->load('items');

        try {
            OrderItemsPackedUpdated::dispatch($order);
        } catch (\Throwable $e) {
            Log::warning("OrderItemsPackedUpdated broadcast failed for {$order->order_number}: {$e->getMessage()}");
        }

        return response()->json([
            'success' => true,
            'order_id' => $order->id,
            'packed_item_ids' => $order->items
                ->where('is_packed', true)
                ->pluck('id')
                ->values()
                ->all(),
        ]);
    }

    /**
     * Mark a single order item (or partial quantity) as Out of Stock during packing and issue a partial Stripe refund.
     */
    public function refundOrderItem(Request $request, Order $order, OrderItem $item, StripePayments $payments): JsonResponse|RedirectResponse
    {
        if ((int) $item->order_id !== (int) $order->id) {
            abort(404);
        }

        if (in_array($order->status, ['cancelled', 'completed'], true)) {
            throw ValidationException::withMessages([
                'item' => 'Items can only be refunded for active orders before completion or cancellation.',
            ]);
        }

        $alreadyRefundedQty = (int) ($item->refunded_quantity ?? 0);
        $remainingQty = max(0, (int) $item->quantity - $alreadyRefundedQty);

        if ($remainingQty <= 0) {
            throw ValidationException::withMessages([
                'item' => "'{$item->name}' has already been fully refunded.",
            ]);
        }

        $validated = $request->validate([
            'quantity' => ['required', 'integer', 'min:1', "max:{$remainingQty}"],
            'zero_product_stock' => ['nullable', 'boolean'],
            'reason' => ['nullable', 'string', 'max:120'],
        ]);

        $qtyToRefund = (int) $validated['quantity'];
        $zeroProductStock = (bool) ($validated['zero_product_stock'] ?? true);
        $reason = trim((string) ($validated['reason'] ?? 'Out of stock')) ?: 'Out of stock';

        $order->loadMissing('items');
        $baseOrderRefundedTotal = round((float) ($order->refunded_total ?? 0), 2);
        $remainingOrderBalance = max(0.0, round((float) $order->total - $baseOrderRefundedTotal, 2));
        $itemRefundAmount = min($remainingOrderBalance, round((float) $item->unit_price * $qtyToRefund, 2));

        $totalActiveUnitsBefore = $order->items->sum(
            fn ($i) => max(0, (int) $i->quantity - (int) ($i->refunded_quantity ?? 0))
        );
        $willCancelOrder = ($totalActiveUnitsBefore - $qtyToRefund) <= 0;
        $refundAmount = $willCancelOrder ? $remainingOrderBalance : $itemRefundAmount;
        $refundCents = (int) round($refundAmount * 100);
        $newRefundedQty = $alreadyRefundedQty + $qtyToRefund;

        if ($order->stripe_payment_id && $payments->isEnabled() && $refundCents > 0) {
            try {
                $payments->refundPartialPayment(
                    $order->stripe_payment_id,
                    $refundCents,
                    "order-{$order->id}-item-{$item->id}-refund-qty-{$newRefundedQty}",
                    [
                        'order_id' => (string) $order->id,
                        'order_number' => (string) $order->order_number,
                        'order_item_id' => (string) $item->id,
                        'item_name' => (string) $item->name,
                        'refunded_qty' => (string) $qtyToRefund,
                        'reason' => $reason,
                    ]
                );
                Log::info("Admin item OOS refund: Refunded \${$refundAmount} ({$qtyToRefund}x {$item->name}) on order {$order->order_number}");
            } catch (\Throwable $e) {
                Log::error("Admin item OOS refund failed for order {$order->order_number} item {$item->id}: {$e->getMessage()}");

                throw ValidationException::withMessages([
                    'item' => "Stripe partial refund failed for '{$item->name}': {$e->getMessage()}",
                ]);
            }
        }

        $previousStatus = $order->status;

        DB::transaction(function () use ($order, $item, $qtyToRefund, $newRefundedQty, $itemRefundAmount, $refundAmount, $baseOrderRefundedTotal, $reason, $zeroProductStock) {
            $lockedOrder = Order::whereKey($order->id)->lockForUpdate()->firstOrFail();
            $lockedItem = OrderItem::whereKey($item->id)->lockForUpdate()->firstOrFail();

            $newItemRefundedAmount = round((float) ($lockedItem->refunded_amount ?? 0) + $itemRefundAmount, 2);
            $fullyRefundedItem = $newRefundedQty >= (int) $lockedItem->quantity;

            $lockedItem->update([
                'refunded_quantity' => $newRefundedQty,
                'refunded_amount' => $newItemRefundedAmount,
                'refund_reason' => $reason,
                'is_packed' => $fullyRefundedItem ? false : $lockedItem->is_packed,
            ]);

            if ($zeroProductStock && $lockedItem->product_id) {
                Product::whereKey($lockedItem->product_id)->update(['stock' => 0]);
            }

            $reservedStock = is_array($lockedOrder->reserved_stock) ? $lockedOrder->reserved_stock : [];
            if ($lockedItem->product_id && isset($reservedStock[$lockedItem->product_id])) {
                $reservedStock[$lockedItem->product_id] = max(0, (int) $reservedStock[$lockedItem->product_id] - $qtyToRefund);
            }

            $remainingActiveUnits = $lockedOrder->items()
                ->get()
                ->sum(fn ($i) => max(0, (int) $i->quantity - (int) ($i->refunded_quantity ?? 0)));

            $nextStatus = $remainingActiveUnits <= 0 ? 'cancelled' : $lockedOrder->status;
            $expectedOrderRefundedTotal = $remainingActiveUnits <= 0
                ? (float) $lockedOrder->total
                : round($baseOrderRefundedTotal + $refundAmount, 2);

            $newOrderRefundedTotal = min(
                (float) $lockedOrder->total,
                max(round((float) ($lockedOrder->refunded_total ?? 0), 2), $expectedOrderRefundedTotal)
            );

            $formattedRefund = number_format($refundAmount, 2);
            $noteEntry = "[Refunded {$qtyToRefund}× {$lockedItem->name} (\${$formattedRefund}) — {$reason}]";
            $updatedNotes = trim(($lockedOrder->notes ?? '').' '.$noteEntry);

            $lockedOrder->update([
                'refunded_total' => $newOrderRefundedTotal,
                'reserved_stock' => $reservedStock,
                'notes' => $updatedNotes,
                'status' => $nextStatus,
            ]);
        });

        $order->refresh()->load('items');

        try {
            OrderItemsPackedUpdated::dispatch($order);
            if ($order->status !== $previousStatus) {
                OrderStatusUpdated::dispatch($order);
            }
        } catch (\Throwable $e) {
            Log::warning("Broadcast failed after item refund for {$order->order_number}: {$e->getMessage()}");
        }

        $formattedAmount = number_format($refundAmount, 2);
        $message = "Refunded {$qtyToRefund}× {$item->name} (\${$formattedAmount})".($zeroProductStock ? ' and marked product Out of Stock.' : '.');

        if ($request->wantsJson()) {
            return response()->json([
                'success' => true,
                'message' => $message,
                'order' => [
                    'id' => $order->id,
                    'status' => $order->status,
                    'refunded_total' => (float) $order->refunded_total,
                    'notes' => $order->notes,
                    'items' => $order->items->map(fn ($i) => [
                        'id' => $i->id,
                        'product_id' => $i->product_id,
                        'name' => $i->name,
                        'size' => $i->size,
                        'unit_price' => (float) $i->unit_price,
                        'quantity' => (int) $i->quantity,
                        'refunded_quantity' => (int) ($i->refunded_quantity ?? 0),
                        'total_price' => (float) $i->total_price,
                        'refunded_amount' => (float) ($i->refunded_amount ?? 0),
                        'refund_reason' => $i->refund_reason,
                        'is_packed' => (bool) $i->is_packed,
                        'image' => $i->image,
                    ])->values()->all(),
                ],
            ]);
        }

        return back()->with('success', $message);
    }

    /**
     * Cancel an order, refunding its payment first so a failed refund leaves the order and its stock untouched.
     *
     * @throws ValidationException When the refund failed; the order is then left unchanged.
     */
    private function cancelOrder(Order $order, StripePayments $payments): void
    {
        $order->refresh();

        if ($order->status === 'completed') {
            throw ValidationException::withMessages([
                'status' => 'Completed orders cannot be cancelled or refunded.',
            ]);
        }

        if ($order->status === 'cancelled') {
            return;
        }

        $alreadyRefunded = round((float) ($order->refunded_total ?? 0), 2);
        $remainingBalance = max(0.0, round((float) $order->total - $alreadyRefunded, 2));
        $remainingCents = (int) round($remainingBalance * 100);

        if ($order->stripe_payment_id && $payments->isEnabled() && $remainingCents > 0) {
            try {
                if ($alreadyRefunded > 0) {
                    $payments->refundPartialPayment(
                        $order->stripe_payment_id,
                        $remainingCents,
                        "order-{$order->id}-cancel-remaining-refund",
                        ['order_id' => (string) $order->id, 'reason' => 'Order cancelled after partial refund']
                    );
                } else {
                    $payments->refundPayment($order->stripe_payment_id);
                }
                Log::info("Admin cancellation: Refund issued for order {$order->order_number} (payment: {$order->stripe_payment_id})");
            } catch (\Throwable $e) {
                Log::error("Admin cancellation: Failed to refund order {$order->order_number}: {$e->getMessage()}");

                throw ValidationException::withMessages([
                    'status' => "The refund for order {$order->order_number} failed, so the order was not cancelled. Please try again or refund it from the Stripe dashboard.",
                ]);
            }
        }

        DB::transaction(function () use ($order) {
            $order->cancelAndRestock('Cancelled by admin', (float) $order->total);
        });
    }
}
