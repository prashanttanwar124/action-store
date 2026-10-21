<?php

namespace App\Events;

use App\Models\Order;
use Illuminate\Broadcasting\Channel;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class OrderItemsPackedUpdated implements ShouldBroadcastNow
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public function __construct(public Order $order)
    {
        $this->order->loadMissing('items');
    }

    /**
     * Get the channels the event should broadcast on.
     *
     * @return array<int, Channel>
     */
    public function broadcastOn(): array
    {
        $channelKey = ltrim($this->order->order_number, '#');

        return [
            new PrivateChannel('admin.orders'),
            new PrivateChannel('orders.'.$channelKey),
        ];
    }

    /**
     * The event's broadcast name.
     */
    public function broadcastAs(): string
    {
        return 'order.items.packed';
    }

    /**
     * Get the data to broadcast.
     *
     * @return array<string, mixed>
     */
    public function broadcastWith(): array
    {
        return [
            'order_id' => $this->order->id,
            'order_number' => $this->order->order_number,
            'status' => $this->order->status,
            'refunded_total' => (float) ($this->order->refunded_total ?? 0),
            'notes' => $this->order->notes,
            'packed_item_ids' => $this->order->items
                ->where('is_packed', true)
                ->pluck('id')
                ->values()
                ->all(),
            'items' => $this->order->items->map(fn ($item) => [
                'id' => $item->id,
                'is_packed' => (bool) $item->is_packed,
                'refunded_quantity' => (int) ($item->refunded_quantity ?? 0),
                'refunded_amount' => (float) ($item->refunded_amount ?? 0),
                'refund_reason' => $item->refund_reason,
            ])->values()->all(),
            'updated_at' => now()->toISOString(),
        ];
    }
}
