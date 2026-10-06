<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Orders are now created only after payment. Remove the payment attempts the previous
     * flow stored as orders: open `pending_payment` rows (their stock is returned first) and
     * attempts that were released without payment (their stock was already returned).
     */
    public function up(): void
    {
        DB::transaction(function () {
            $pendingOrderIds = DB::table('orders')->where('status', 'pending_payment')->pluck('id');

            $reservedQuantities = DB::table('order_items')
                ->whereIn('order_id', $pendingOrderIds)
                ->whereNotNull('product_id')
                ->selectRaw('product_id, SUM(quantity) as quantity')
                ->groupBy('product_id')
                ->pluck('quantity', 'product_id');

            foreach ($reservedQuantities as $productId => $quantity) {
                DB::table('products')->where('id', $productId)->increment('stock', (int) $quantity);
            }

            $releasedUnpaidOrderIds = DB::table('orders')
                ->where('status', 'cancelled')
                ->where(function ($query) {
                    $query->where('notes', 'like', '%[Cancelled: Payment declined or cancelled by customer]%')
                        ->orWhere('notes', 'like', '%[Cancelled: Payment failed or cancelled on Stripe]%')
                        ->orWhere('notes', 'like', '%[Cancelled: Payment timeout exceeded%')
                        ->orWhere('notes', 'like', '%[Cancelled: Stripe PaymentIntent initialization failed]%');
                })
                ->pluck('id');

            $orderIds = $pendingOrderIds->merge($releasedUnpaidOrderIds);

            DB::table('order_items')->whereIn('order_id', $orderIds)->delete();
            DB::table('orders')->whereIn('id', $orderIds)->delete();
        });
    }

    /**
     * The removed rows were unpaid attempts; there is nothing to restore.
     */
    public function down(): void
    {
        //
    }
};
