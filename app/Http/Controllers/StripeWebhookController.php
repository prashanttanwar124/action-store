<?php

namespace App\Http\Controllers;

use App\Events\OrderPlaced;
use App\Models\Order;
use App\Models\Product;
use App\Models\RecipeKit;
use App\Models\StoreSetting;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Stripe\Exception\SignatureVerificationException;
use Stripe\Webhook;

class StripeWebhookController extends Controller
{
    /**
     * Handle incoming Stripe webhook events.
     */
    public function handle(Request $request): JsonResponse
    {
        $payload = $request->getContent();
        $sigHeader = $request->header('Stripe-Signature');
        $webhookSecret = config('services.stripe.webhook_secret');

        $event = null;

        if (! empty($webhookSecret) && ! empty($sigHeader)) {
            try {
                $event = Webhook::constructEvent($payload, $sigHeader, $webhookSecret);
            } catch (SignatureVerificationException $e) {
                Log::warning('Stripe webhook signature verification failed: '.$e->getMessage());

                return response()->json(['error' => 'Invalid signature'], 400);
            } catch (\UnexpectedValueException $e) {
                Log::warning('Stripe webhook payload invalid: '.$e->getMessage());

                return response()->json(['error' => 'Invalid payload'], 400);
            }
        } else {
            // When no webhook secret is configured (e.g. local dev / testing), decode JSON payload
            $data = json_decode($payload, true);
            if (! is_array($data) || ! isset($data['type'])) {
                return response()->json(['error' => 'Invalid payload'], 400);
            }
            $event = (object) [
                'type' => $data['type'] ?? '',
                'data' => (object) ['object' => (object) ($data['data']['object'] ?? [])],
            ];
        }

        if ($event->type === 'payment_intent.succeeded') {
            $paymentIntent = $event->data->object;
            $this->handlePaymentIntentSucceeded($paymentIntent);
        }

        return response()->json(['received' => true]);
    }

    /**
     * Reconcile or automatically recover a successful Stripe payment into an order.
     */
    protected function handlePaymentIntentSucceeded(object $intent): void
    {
        $stripePaymentId = $intent->id ?? null;
        if (empty($stripePaymentId)) {
            return;
        }

        // Check if an order already exists for this payment intent
        $existingOrder = Order::where('stripe_payment_id', $stripePaymentId)->first();
        if ($existingOrder) {
            Log::info("Stripe webhook: Order {$existingOrder->order_number} already exists for {$stripePaymentId}.");

            return;
        }

        // Recover missing order from metadata if client disconnected before /checkout returned
        $metadata = (array) ($intent->metadata ?? []);
        $itemsJson = $metadata['items_json'] ?? null;
        $items = $itemsJson ? json_decode($itemsJson, true) : [];

        if (empty($items)) {
            Log::warning("Stripe webhook: Received payment {$stripePaymentId} without cart metadata. Manual reconciliation needed.");

            return;
        }

        try {
            DB::transaction(function () use ($stripePaymentId, $metadata, $items, $intent) {
                // Ensure no race condition created it in another thread
                if (Order::where('stripe_payment_id', $stripePaymentId)->exists()) {
                    return;
                }

                $orderNumber = Order::generateOrderNumber();

                $subtotal = 0.00;
                $resolvedItems = [];
                foreach ($items as $item) {
                    $model = ($item['type'] ?? 'product') === 'recipe-kit' ? RecipeKit::class : Product::class;
                    $catalogItem = $model::query()
                        ->where(is_numeric($item['id']) ? 'id' : 'slug', $item['id'])->first();

                    if ($catalogItem) {
                        $isSubscribed = ! empty($item['is_subscribed']);
                        $unitPrice = round($catalogItem->price * ($isSubscribed ? 0.95 : 1), 2);
                        $quantity = max(1, (int) ($item['quantity'] ?? 1));
                        $subtotal += round($unitPrice * $quantity, 2);

                        $resolvedItems[] = [
                            'product_id' => $catalogItem instanceof Product ? $catalogItem->id : null,
                            'name' => $catalogItem->name,
                            'size' => $catalogItem instanceof Product ? $catalogItem->size_main : null,
                            'unit_price' => $unitPrice,
                            'quantity' => $quantity,
                            'total_price' => round($unitPrice * $quantity, 2),
                            'is_subscribed' => $isSubscribed,
                            'image' => $catalogItem->image,
                        ];

                        if ($catalogItem instanceof Product) {
                            $catalogItem->decrementStock($quantity);
                        }
                    }
                }

                $storeInfo = StoreSetting::current();
                $fulfillmentType = $metadata['fulfillment_type'] ?? 'Store Pickup';
                $deliveryFee = 0.00;
                if ($fulfillmentType === 'Home Delivery') {
                    if ($subtotal < ($storeInfo->free_delivery_threshold ?? 50.00)) {
                        $deliveryFee = (float) ($storeInfo->delivery_fee ?? 4.99);
                    }
                }

                $amountReceived = isset($intent->amount_received) ? round($intent->amount_received / 100, 2) : ($subtotal + $deliveryFee);
                $total = round($subtotal + $deliveryFee, 2);

                $order = Order::create([
                    'order_number' => $orderNumber,
                    'user_id' => ! empty($metadata['user_id']) && is_numeric($metadata['user_id']) ? (int) $metadata['user_id'] : null,
                    'customer_name' => $metadata['customer_name'] ?? 'Online Customer',
                    'customer_email' => $metadata['customer_email'] ?? 'customer@example.com',
                    'customer_phone' => $metadata['customer_phone'] ?? null,
                    'subtotal' => $subtotal,
                    'discount' => 0.00,
                    'delivery_fee' => $deliveryFee,
                    'total' => $amountReceived,
                    'points_earned' => (int) floor($subtotal),
                    'payment_method' => $metadata['payment_method'] ?? 'card',
                    'stripe_payment_id' => $stripePaymentId,
                    'fulfillment_type' => $fulfillmentType,
                    'pickup_slot' => $metadata['pickup_slot'] ?? 'As Soon As Possible',
                    'pickup_location' => $metadata['pickup_location'] ?? ($storeInfo->address.' · '.$storeInfo->name),
                    'delivery_address' => $metadata['delivery_address'] ?? null,
                    'status' => 'confirmed',
                    'notes' => 'Recovered automatically via Stripe Webhook',
                    'idempotency_key' => $metadata['idempotency_key'] ?? null,
                ]);

                foreach ($resolvedItems as $orderItem) {
                    $order->items()->create($orderItem);
                }

                try {
                    OrderPlaced::dispatch($order);
                } catch (\Throwable $e) {
                    Log::warning('Stripe webhook OrderPlaced broadcast failed: '.$e->getMessage());
                }

                Log::info("Stripe webhook: Successfully recovered and created order {$orderNumber} for payment {$stripePaymentId}.");
            });
        } catch (\Throwable $e) {
            Log::error("Stripe webhook recovery failed for {$stripePaymentId}: ".$e->getMessage());
        }
    }
}
