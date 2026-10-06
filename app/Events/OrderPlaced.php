<?php

namespace App\Events;

use App\Models\Order;
use Illuminate\Broadcasting\Channel;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class OrderPlaced implements ShouldBroadcastNow
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public function __construct(public Order $order)
    {
        $this->order->load(['items', 'user']);
    }

    /**
     * Get the channels the event should broadcast on.
     *
     * @return array<int, Channel>
     */
    public function broadcastOn(): array
    {
        return [
            new PrivateChannel('admin.orders'),
        ];
    }

    /**
     * The event's broadcast name.
     */
    public function broadcastAs(): string
    {
        return 'order.placed';
    }

    /**
     * Get the data to broadcast.
     *
     * @return array<string, mixed>
     */
    public function broadcastWith(): array
    {
        return [
            'order' => [
                'id' => $this->order->id,
                'order_number' => $this->order->order_number,
                'customer_name' => $this->order->customer_name,
                'customer_email' => $this->order->customer_email,
                'customer_phone' => $this->order->customer_phone,
                'subtotal' => (float) $this->order->subtotal,
                'discount' => (float) $this->order->discount,
                'total' => (float) $this->order->total,
                'points_earned' => $this->order->points_earned,
                'payment_method' => $this->order->payment_method,
                'fulfillment_type' => $this->order->fulfillment_type,
                'pickup_slot' => $this->order->pickup_slot,
                'pickup_location' => $this->order->pickup_location,
                'delivery_address' => $this->order->delivery_address,
                'delivery_fee' => (float) $this->order->delivery_fee,
                'status' => $this->order->status,
                'notes' => $this->order->notes,
                'created_at' => $this->order->created_at?->toISOString(),
                'created_at_human' => 'Just now',
                'items_count' => $this->order->items->count(),
                'items' => $this->order->items->map(function ($item) {
                    return [
                        'id' => $item->id,
                        'name' => $item->name,
                        'size' => $item->size,
                        'unit_price' => (float) $item->unit_price,
                        'quantity' => $item->quantity,
                        'total_price' => (float) $item->total_price,
                        'image' => $item->image,
                    ];
                })->all(),
            ],
        ];
    }
}
