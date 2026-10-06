<?php

namespace App\Exceptions;

use RuntimeException;

class InsufficientStockException extends RuntimeException
{
    public function __construct(
        string $message = 'One or more items in your cart are no longer in stock.',
        public readonly ?int $productId = null
    ) {
        parent::__construct($message);
    }
}
