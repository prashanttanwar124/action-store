<?php

namespace App\Http\Controllers\Admin;

use App\Events\OrderStatusUpdated;
use App\Http\Controllers\Controller;
use App\Models\Order;
use App\Services\StripePayments;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
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

        $query = Order::with('items')->latest();

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

        $orders = $query->paginate(12)->withQueryString()->through(function ($order) {
            return [
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
                'items_count' => $order->items->count(),
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
            ];
        });

        // Compute live KDS summary metrics for today
        $today = Carbon::today();
        $todayOrders = Order::whereDate('created_at', $today);

        $stats = [
            'total_today' => (clone $todayOrders)->count(),
            'confirmed' => Order::where('status', 'confirmed')->count(),
            'packing' => Order::where('status', 'packing')->count(),
            'ready_for_pickup' => Order::where('status', 'ready_for_pickup')->count(),
            'completed_today' => (clone $todayOrders)->where('status', 'completed')->count(),
            'revenue_today' => (float) (clone $todayOrders)->where('status', '!=', 'cancelled')->sum('total'),
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

        if ($order->status === 'cancelled' && $newStatus !== 'cancelled') {
            throw ValidationException::withMessages([
                'status' => 'A cancelled order cannot be reopened: its stock was returned and any payment refunded.',
            ]);
        }

        if ($newStatus === 'cancelled') {
            $this->cancelOrder($order, $payments);
        } else {
            $order->update(['status' => $newStatus]);
        }

        // Broadcast real-time status update to both Admin KDS and Customer tracking screens
        OrderStatusUpdated::dispatch($order);

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
     * Cancel an order, refunding its payment first so a failed refund leaves the order and its stock untouched.
     *
     * @throws ValidationException When the refund failed; the order is then left unchanged.
     */
    private function cancelOrder(Order $order, StripePayments $payments): void
    {
        if ($order->status === 'cancelled') {
            return;
        }

        if ($order->stripe_payment_id && $payments->isEnabled()) {
            try {
                $payments->refundPayment($order->stripe_payment_id);
                Log::info("Admin cancellation: Refund issued for order {$order->order_number} (payment: {$order->stripe_payment_id})");
            } catch (\Throwable $e) {
                Log::error("Admin cancellation: Failed to refund order {$order->order_number}: {$e->getMessage()}");

                throw ValidationException::withMessages([
                    'status' => "The refund for order {$order->order_number} failed, so the order was not cancelled. Please try again or refund it from the Stripe dashboard.",
                ]);
            }
        }

        $order->cancelAndRestock('Cancelled by admin');
    }
}
