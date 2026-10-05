<?php

namespace App\Http\Controllers;

use App\Models\Order;
use Illuminate\Support\Facades\Auth;
use Inertia\Inertia;
use Inertia\Response;

class AccountController extends Controller
{
    /**
     * Display the customer account dashboard.
     */
    public function index(): Response
    {
        return Inertia::render('Account', [
            'dbOrders' => $this->getUserOrders(),
        ]);
    }

    /**
     * Display the customer account with reorder section active.
     */
    public function reorder(): Response
    {
        return Inertia::render('Account', [
            'activeSection' => 'reorder',
            'dbOrders' => $this->getUserOrders(),
        ]);
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function getUserOrders(): array
    {
        if (! Auth::check()) {
            return [];
        }

        return Order::with('items')
            ->where('user_id', Auth::id())
            ->where('status', '!=', 'pending_payment')
            ->latest()
            ->get()
            ->map(fn (Order $order) => [
                'id' => $order->order_number,
                'status' => $order->status,
                'date' => $order->created_at->diffForHumans(),
                'type' => $order->fulfillment_type,
                'total' => (float) $order->total,
                'itemCount' => $order->items->sum('quantity'),
                'summary' => $order->items->pluck('name')->join(', '),
                'items' => $order->items->map(fn ($item) => [
                    'id' => $item->product_id ?? $item->id,
                    'name' => $item->name,
                    'price' => (float) $item->unit_price,
                    'quantity' => $item->quantity,
                    'size' => $item->size,
                    'image' => $item->image,
                ])->all(),
            ])
            ->values()
            ->all();
    }
}
