<?php

namespace App\Http\Controllers;

use App\Models\Checkout;
use App\Models\Order;
use App\Services\StripePayments;
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
    public function handle(Request $request, StripePayments $payments): JsonResponse
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

        try {
            match ($event->type) {
                'payment_intent.succeeded' => $this->handlePaymentIntentSucceeded($event->data->object, $payments),
                'payment_intent.canceled' => $this->handlePaymentIntentCanceled($event->data->object),
                'charge.refunded' => $this->handleChargeRefunded($event->data->object),
                'charge.dispute.created' => $this->handleDisputeCreated($event->data->object),
                // A declined card (payment_intent.payment_failed) needs no action: the customer can retry the same checkout
                default => null,
            };
        } catch (\RuntimeException $e) {
            Log::error("Stripe webhook processing failed: {$e->getMessage()}");

            return response()->json(['error' => $e->getMessage()], 500);
        }

        return response()->json(['received' => true]);
    }

    /**
     * Turn the paid checkout into an order (when the browser has not done so already).
     */
    protected function handlePaymentIntentSucceeded(object $intent, StripePayments $payments): void
    {
        $paymentIntentId = $intent->id ?? null;
        if (! $paymentIntentId || Order::where('stripe_payment_id', $paymentIntentId)->exists()) {
            return;
        }

        $checkout = $this->findCheckoutForIntent($intent);
        if ($checkout) {
            $result = $payments->completeOrRefund($checkout, $intent);

            if ($result->isCompleted()) {
                Log::info("Stripe webhook: Created order {$result->order->order_number} for payment {$paymentIntentId}");

                return;
            }

            if ($result->isRefunded()) {
                Log::critical("Stripe webhook: Checkout {$checkout->id} payment {$paymentIntentId} succeeded, but items/slot unavailable: {$result->stockException?->getMessage()}. Auto-refunded customer.");

                return;
            }

            if ($result->isRefundFailed()) {
                Log::critical("Stripe webhook: Automatic refund failed for {$paymentIntentId}: {$result->refundError?->getMessage()}");

                throw new \RuntimeException("Automatic refund failed for {$paymentIntentId}: {$result->refundError?->getMessage()}");
            }

            Log::critical("Stripe webhook: Payment {$paymentIntentId} does not match checkout {$checkout->id} (amount, currency or checkout mismatch).");

            return;
        }

        // Not one of our checkout payments
        if (! StripePayments::metadataValue($intent, 'checkout_id')) {
            return;
        }

        // The checkout was released before this payment landed: give the money back
        Log::critical("Stripe webhook: Payment {$paymentIntentId} succeeded but its checkout no longer exists. Refunding.");
        if ($payments->isEnabled()) {
            try {
                $payments->refundPayment($paymentIntentId);
            } catch (\Throwable $e) {
                Log::critical("Stripe webhook: Refund of orphaned payment {$paymentIntentId} failed: {$e->getMessage()}");

                throw new \RuntimeException("Refund of orphaned payment {$paymentIntentId} failed: {$e->getMessage()}");
            }
        }
    }

    /**
     * Release the checkout once Stripe has cancelled its payment.
     */
    protected function handlePaymentIntentCanceled(object $intent): void
    {
        $checkout = $this->findCheckoutForIntent($intent);

        if ($checkout && $checkout->stripe_payment_id === ($intent->id ?? null) && $checkout->release()) {
            Log::info("Stripe webhook: Released checkout {$checkout->id} after its payment was cancelled.");
        }
    }

    /**
     * Cancel and restock the order only when its charge has been fully refunded.
     */
    protected function handleChargeRefunded(object $charge): void
    {
        $paymentIntentId = $charge->payment_intent ?? null;
        if (! $paymentIntentId) {
            return;
        }

        $order = Order::where('stripe_payment_id', $paymentIntentId)->first();
        if (! $order) {
            return;
        }

        if (empty($charge->refunded)) {
            $refunded = number_format(((int) ($charge->amount_refunded ?? 0)) / 100, 2);
            $order->update([
                'notes' => trim(($order->notes ?? '')." [PARTIAL REFUND ON STRIPE: \${$refunded}]"),
            ]);
            Log::info("Stripe webhook: Recorded partial refund of \${$refunded} for order {$order->order_number}.");

            return;
        }

        if ($order->cancelAndRestock('Refunded via Stripe')) {
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

    /**
     * Find the checkout a PaymentIntent belongs to, by its metadata checkout id or its stored intent id.
     */
    protected function findCheckoutForIntent(object $intent): ?Checkout
    {
        $checkoutId = StripePayments::metadataValue($intent, 'checkout_id');
        if ($checkoutId && $checkout = Checkout::find($checkoutId)) {
            return $checkout;
        }

        $paymentIntentId = $intent->id ?? null;

        return $paymentIntentId ? Checkout::where('stripe_payment_id', $paymentIntentId)->first() : null;
    }
}
