<?php

namespace App\Services;

use App\Exceptions\InsufficientStockException;
use App\Models\Order;
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
        public readonly ?InsufficientStockException $stockException = null,
        public readonly ?Refund $refund = null,
        public readonly ?Throwable $refundError = null
    ) {}

    public static function completed(Order $order): self
    {
        return new self(self::COMPLETED, order: $order);
    }

    public static function refunded(InsufficientStockException $e, ?Refund $refund = null): self
    {
        return new self(self::REFUNDED, stockException: $e, refund: $refund);
    }

    public static function refundFailed(InsufficientStockException $e, Throwable $error): self
    {
        return new self(self::REFUND_FAILED, stockException: $e, refundError: $error);
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
