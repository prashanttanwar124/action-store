<?php

namespace App\Services;

use App\Models\Order;
use RuntimeException;
use Stripe\Refund;
use Throwable;

class CheckoutCompletionResult
{
    public const COMPLETED = 'completed';

    public const REFUNDED = 'refunded';

    public const REFUND_FAILED = 'refund_failed';

    public const NOT_PAID = 'not_paid';

    public const ALREADY_HANDLED = 'already_handled';

    public function __construct(
        public readonly string $status,
        public readonly ?Order $order = null,
        public readonly ?RuntimeException $failure = null,
        public readonly ?Refund $refund = null,
        public readonly ?Throwable $refundError = null
    ) {}

    public static function completed(Order $order): self
    {
        return new self(self::COMPLETED, order: $order);
    }

    /**
     * The checkout could not be fulfilled (out of stock, slot full, or a mismatched payment) and was refunded.
     */
    public static function refunded(RuntimeException $failure, ?Refund $refund = null): self
    {
        return new self(self::REFUNDED, failure: $failure, refund: $refund);
    }

    public static function refundFailed(RuntimeException $failure, Throwable $error): self
    {
        return new self(self::REFUND_FAILED, failure: $failure, refundError: $error);
    }

    public static function notPaid(): self
    {
        return new self(self::NOT_PAID);
    }

    public static function alreadyHandled(): self
    {
        return new self(self::ALREADY_HANDLED);
    }

    public function isCompleted(): bool
    {
        return $this->status === self::COMPLETED;
    }

    public function isRefunded(): bool
    {
        return $this->status === self::REFUNDED;
    }

    public function isRefundFailed(): bool
    {
        return $this->status === self::REFUND_FAILED;
    }

    public function isNotPaid(): bool
    {
        return $this->status === self::NOT_PAID;
    }

    public function isAlreadyHandled(): bool
    {
        return $this->status === self::ALREADY_HANDLED;
    }
}
