<?php

namespace App\Exceptions;

class SlotCapacityExceededException extends InsufficientStockException
{
    public function __construct(
        string $message = 'The selected pickup window is no longer available as capacity was reached.',
        public readonly ?string $slot = null
    ) {
        parent::__construct($message);
    }
}
