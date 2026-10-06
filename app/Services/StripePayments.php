<?php

namespace App\Services;

use App\Exceptions\InsufficientStockException;
use App\Models\Checkout;
use App\Models\Order;
use Illuminate\Support\Facades\Log;
use Stripe\PaymentIntent;
use Stripe\Refund;
use Stripe\StripeClient;

/**
 * Single place for every Stripe call made by the checkout, webhook, admin and cleanup flows.
 */
class StripePayments
{
    /**
     * Outcomes of releaseCheckout().
     */
    public const RELEASED = 'released';

    public const PAID = 'paid';

    public const DEFERRED = 'deferred';

    /**
     * Determine whether online payments are configured.
     */
    public function isEnabled(): bool
    {
        return ! empty(config('services.stripe.secret'));
    }

    /**
     * The store currency, lower-cased as Stripe returns it.
     */
    public function currency(): string
    {
        return strtolower(config('services.stripe.currency', 'cad'));
    }

    /**
     * The checkout total expressed in the smallest currency unit.
     */
    public function amountInCents(Checkout $checkout): int
    {
        return (int) round((float) $checkout->total * 100);
    }

    /**
     * Create the PaymentIntent that pays for a checkout.
     */
    public function createIntentFor(Checkout $checkout): PaymentIntent
    {
        $payload = [
            'amount' => $this->amountInCents($checkout),
            'currency' => $this->currency(),
            'metadata' => [
                'checkout_id' => (string) $checkout->id,
                'user_id' => (string) $checkout->user_id,
            ],
            'automatic_payment_methods' => [
                'enabled' => true,
                'allow_redirects' => 'never',
            ],
        ];

        if (filter_var($checkout->customer_email, FILTER_VALIDATE_EMAIL)) {
            $payload['receipt_email'] = strtolower(trim($checkout->customer_email));
        }

        return $this->client()->paymentIntents->create($payload, [
            'idempotency_key' => "checkout-{$checkout->id}-intent",
        ]);
    }

    public function retrieveIntent(string $paymentIntentId): PaymentIntent
    {
        return $this->client()->paymentIntents->retrieve($paymentIntentId);
    }

    public function cancelIntent(string $paymentIntentId): PaymentIntent
    {
        return $this->client()->paymentIntents->cancel($paymentIntentId);
    }

    /**
     * Refund a payment in full. The idempotency key makes repeated calls for the same payment safe.
     */
    public function refundPayment(string $paymentIntentId): Refund
    {
        return $this->client()->refunds->create([
            'payment_intent' => $paymentIntentId,
            'reason' => 'requested_by_customer',
        ], [
            'idempotency_key' => "payment-{$paymentIntentId}-refund",
        ]);
    }

    /**
     * Determine whether a PaymentIntent is a completed, exact payment for the given checkout.
     */
    public function intentPaysFor(object $intent, Checkout $checkout): bool
    {
        return ($intent->status ?? null) === 'succeeded'
            && ! empty($checkout->stripe_payment_id)
            && ($intent->id ?? null) === $checkout->stripe_payment_id
            && (int) ($intent->amount_received ?? 0) === $this->amountInCents($checkout)
            && strtolower($intent->currency ?? '') === $this->currency()
            && (string) static::metadataValue($intent, 'checkout_id') === (string) $checkout->id;
    }

    /**
     * Read a metadata value from a Stripe object, a decoded stdClass, or an array payload.
     */
    public static function metadataValue(object $intent, string $key): mixed
    {
        $metadata = $intent->metadata ?? null;

        if (is_array($metadata)) {
            return $metadata[$key] ?? null;
        }

        return is_object($metadata) ? ($metadata->{$key} ?? null) : null;
    }

    /**
     * Turn a checkout into an order if the PaymentIntent is a verified, exact payment for it.
     */
    public function completeCheckout(Checkout $checkout, object $intent): ?Order
    {
        if (! $this->intentPaysFor($intent, $checkout)) {
            return null;
        }

        return $checkout->convertToOrder($intent->id);
    }

    /**
     * Release an unpaid checkout: stop its PaymentIntent on Stripe, then return the stock and delete it.
     *
     * Stripe never lets a PaymentIntent be both cancelled and paid, so cancelling first means a
     * customer can never be charged for a checkout that was deleted. If the cancel is refused
     * because the payment already went through, the checkout becomes an order instead. If the
     * payment is still processing, or Stripe cannot be reached, the checkout is kept for a later run.
     *
     * @return string One of self::RELEASED, self::PAID or self::DEFERRED.
     */
    public function releaseCheckout(Checkout $checkout): string
    {
        if ($checkout->stripe_payment_id && $this->isEnabled()) {
            try {
                $this->cancelIntent($checkout->stripe_payment_id);
            } catch (\Throwable $cancelError) {
                try {
                    $intent = $this->retrieveIntent($checkout->stripe_payment_id);
                } catch (\Throwable $retrieveError) {
                    Log::error("Could not check payment for checkout {$checkout->id}: {$retrieveError->getMessage()}");

                    return self::DEFERRED;
                }

                try {
                    if ($this->completeCheckout($checkout, $intent)) {
                        return self::PAID;
                    }
                } catch (InsufficientStockException $e) {
                    Log::critical("Release checkout {$checkout->id}: payment succeeded but stock was insufficient: {$e->getMessage()}. Refunding.");
                    $this->refundPayment($checkout->stripe_payment_id);
                    $checkout->delete();

                    return self::RELEASED;
                }

                if ($intent->status !== 'canceled') {
                    Log::warning("Checkout {$checkout->id} kept: its payment is '{$intent->status}'.");

                    return self::DEFERRED;
                }
            }
        }

        $checkout->release();

        return self::RELEASED;
    }

    /**
     * Release every checkout whose customer has been inactive longer than its lifetime.
     *
     * @return int The number of checkouts released.
     */
    public function releaseExpiredCheckouts(): int
    {
        $releasedCount = 0;

        foreach (Checkout::expired()->get() as $checkout) {
            try {
                if ($this->releaseCheckout($checkout) === self::RELEASED) {
                    $releasedCount++;
                }
            } catch (\Throwable $e) {
                Log::error("Failed to release expired checkout {$checkout->id}: {$e->getMessage()}");
            }
        }

        return $releasedCount;
    }

    protected function client(): StripeClient
    {
        return new StripeClient((string) config('services.stripe.secret'));
    }
}
