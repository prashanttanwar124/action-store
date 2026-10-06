<?php

namespace App\Exceptions;

use RuntimeException;

/**
 * A checkout's payment succeeded, but for a different amount or currency than the checkout.
 */
class PaymentMismatchException extends RuntimeException
{
    public function __construct(string $message = 'The payment does not match the checkout amount or currency.')
    {
        parent::__construct($message);
    }
}
