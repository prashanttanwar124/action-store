<?php

namespace App\Http\Controllers\Admin;

use App\Events\OrderStatusUpdated;
use App\Http\Controllers\Controller;
use App\Models\Order;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Inertia\Inertia;
use Inertia\Response;
use Stripe\Refund;
use Stripe\Stripe;

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
        } else {
            $query->where('status', '!=', 'pending_payment');
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
        $todayOrders = Order::whereDate('created_at', $today)->where('status', '!=', 'pending_payment');

        $stats = [
            'total_today' => (clone $todayOrders)->count(),
            'confirmed' => Order::where('status', 'confirmed')->count(),
            'packing' => Order::where('status', 'packing')->count(),
            'ready_for_pickup' => Order::where('status', 'ready_for_pickup')->count(),
            'completed_today' => (clone $todayOrders)->where('status', 'completed')->count(),
            'revenue_today' => (float) (clone $todayOrders)->whereNotIn('status', ['cancelled', 'pending_payment'])->sum('total'),
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
    public function updateStatus(Request $request, Order $order): JsonResponse|RedirectResponse
    {
        $validated = $request->validate([
            'status' => ['required', 'string', 'in:confirmed,packing,ready_for_pickup,completed,cancelled'],
        ]);

        $previousStatus = $order->status;
        $newStatus = $validated['status'];

        $order->update([
            'status' => $newStatus,
        ]);

        // Restock inventory and auto-refund if transitioned to cancelled
        if ($newStatus === 'cancelled' && $previousStatus !== 'cancelled') {
            // 1. Restock items
            $order->load('items.product');
            foreach ($order->items as $item) {
                if ($item->product_id && $item->product) {
                    $item->product->increment('stock', (int) $item->quantity);
                }
            }

            // 2. Issue Stripe refund if paid online
            if (! empty($order->stripe_payment_id) && ! app()->environment('testing')) {
                $stripeSecret = config('services.stripe.secret');
                if (! empty($stripeSecret)) {
                    try {
                        Stripe::setApiKey($stripeSecret);
                        Refund::create([
                            'payment_intent' => $order->stripe_payment_id,
                            'reason' => 'requested_by_customer',
                            'metadata' => [
                                'admin_cancellation' => 'true',
                                'order_number' => $order->order_number,
                            ],
                        ]);
                        Log::info("Admin cancellation: Refund issued for order {$order->order_number} (payment: {$order->stripe_payment_id})");
                    } catch (\Throwable $refundErr) {
                        Log::error("Admin cancellation: Failed to refund order {$order->order_number}: {$refundErr->getMessage()}");
                    }
                }
            }
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
}
