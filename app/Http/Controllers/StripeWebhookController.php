<?php

namespace App\Http\Controllers;

use App\Events\OrderPlaced;
use App\Models\Order;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
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

        // Strict signature check: if secret is configured, signature is mandatory
        if (! empty($webhookSecret)) {
            if (empty($sigHeader)) {
                Log::warning('Stripe webhook rejected: Missing Stripe-Signature header.');

                return response()->json(['error' => 'Missing Stripe-Signature header'], 400);
            }

            try {
                $event = Webhook::constructEvent($payload, $sigHeader, $webhookSecret);
            } catch (SignatureVerificationException $e) {
                Log::warning('Stripe webhook signature verification failed: '.$e->getMessage());

                return response()->json(['error' => 'Invalid signature'], 400);
            } catch (\UnexpectedValueException $e) {
                Log::warning('Stripe webhook payload invalid: '.$e->getMessage());

                return response()->json(['error' => 'Invalid payload'], 400);
            }
        } elseif (! app()->environment('testing', 'local')) {
            Log::error('Stripe webhook received outside testing/local without configured webhook secret.');

            return response()->json(['error' => 'Webhook secret is not configured'], 400);
        } else {
            // Local dev / test environment fallback when no secret is configured
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
        } elseif ($event->type === 'payment_intent.payment_failed' || $event->type === 'payment_intent.canceled') {
            $paymentIntent = $event->data->object;
            $this->handlePaymentIntentFailed($paymentIntent);
        } elseif ($event->type === 'charge.refunded') {
            $charge = $event->data->object;
            $this->handleChargeRefunded($charge);
        } elseif ($event->type === 'charge.dispute.created') {
            $dispute = $event->data->object;
            $this->handleDisputeCreated($dispute);
        }

        return response()->json(['received' => true]);
    }

    /**
     * Mark pending order as confirmed upon successful payment.
     */
    protected function handlePaymentIntentSucceeded(object $intent): void
    {
        $stripePaymentId = $intent->id ?? null;
        $orderId = $intent->metadata->order_id ?? null;

        if (is_array($intent->metadata ?? null)) {
            $orderId = $intent->metadata['order_id'] ?? null;
        }

        $order = null;
        if ($orderId) {
            $order = Order::find($orderId);
        }
        if (! $order && $stripePaymentId) {
            $order = Order::where('stripe_payment_id', $stripePaymentId)->first();
        }

        if ($order && $order->status === 'pending_payment') {
            $order->update([
                'status' => 'confirmed',
                'stripe_payment_id' => $stripePaymentId,
            ]);

            try {
                OrderPlaced::dispatch($order);
            } catch (\Throwable $e) {
                Log::warning('Stripe webhook OrderPlaced broadcast failed: '.$e->getMessage());
            }

            Log::info("Stripe webhook: Confirmed order {$order->order_number} for payment {$stripePaymentId}");
        }
    }

    /**
     * Cancel pending order and restock inventory when payment fails or is cancelled.
     */
    protected function handlePaymentIntentFailed(object $intent): void
    {
        $stripePaymentId = $intent->id ?? null;
        $orderId = $intent->metadata->order_id ?? null;

        if (is_array($intent->metadata ?? null)) {
            $orderId = $intent->metadata['order_id'] ?? null;
        }

        $order = null;
        if ($orderId) {
            $order = Order::find($orderId);
        }
        if (! $order && $stripePaymentId) {
            $order = Order::where('stripe_payment_id', $stripePaymentId)->first();
        }

        if ($order && $order->status === 'pending_payment') {
            $order->cancelAndRestock('Payment failed or cancelled on Stripe');
            Log::info("Stripe webhook: Cancelled pending order {$order->order_number} after payment failure.");
        }
    }

    /**
     * Cancel order and restock inventory when a charge is refunded via Stripe.
     */
    protected function handleChargeRefunded(object $charge): void
    {
        $paymentIntentId = $charge->payment_intent ?? null;
        if (! $paymentIntentId) {
            return;
        }

        $order = Order::where('stripe_payment_id', $paymentIntentId)->first();
        if ($order && $order->status !== 'cancelled') {
            $order->cancelAndRestock('Refunded via Stripe');
            Log::info("Stripe webhook: Cancelled and restocked order {$order->order_number} following Stripe refund.");
        }
    }

    /**
     * Flag order when a customer files a dispute.
     */
    protected function handleDisputeCreated(object $dispute): void
    {
        $paymentIntentId = $dispute->payment_intent ?? null;
        $reason = $dispute->reason ?? 'unknown';

        Log::critical("Stripe dispute created: payment_intent {$paymentIntentId}, reason: {$reason}");

        if ($paymentIntentId) {
            $order = Order::where('stripe_payment_id', $paymentIntentId)->first();
            if ($order) {
                $order->update([
                    'notes' => trim(($order->notes ?? '')." [DISPUTE FILED ON STRIPE: {$reason}]"),
                ]);
            }
        }
    }
}
