<?php

namespace Tests\Unit;

use App\Models\Checkout;
use PHPUnit\Framework\TestCase;

class CheckoutFingerprintTest extends TestCase
{
    public function test_fingerprint_generates_valid_sha256_hash(): void
    {
        $payload = [
            'items' => [
                ['id' => '10', 'type' => 'product', 'quantity' => 2],
            ],
            'payment_method' => 'card',
            'fulfillment_type' => 'Store Pickup',
            'pickup_timing_mode' => 'asap',
        ];

        $fingerprint = Checkout::fingerprintFor($payload);

        $this->assertIsString($fingerprint);
        $this->assertMatchesRegularExpression('/^[a-f0-9]{64}$/', $fingerprint);
    }

    public function test_fingerprint_is_invariant_to_item_array_order(): void
    {
        $payload1 = [
            'items' => [
                ['id' => '1', 'type' => 'product', 'quantity' => 1],
                ['id' => '2', 'type' => 'product', 'quantity' => 3],
            ],
            'payment_method' => 'card',
            'fulfillment_type' => 'Store Pickup',
        ];

        $payload2 = [
            'items' => [
                ['id' => '2', 'type' => 'product', 'quantity' => 3],
                ['id' => '1', 'type' => 'product', 'quantity' => 1],
            ],
            'payment_method' => 'card',
            'fulfillment_type' => 'Store Pickup',
        ];

        $this->assertSame(
            Checkout::fingerprintFor($payload1),
            Checkout::fingerprintFor($payload2),
            'Fingerprint should be identical regardless of item insertion order.'
        );
    }

    public function test_fingerprint_changes_when_quantity_changes(): void
    {
        $base = [
            'items' => [
                ['id' => '5', 'type' => 'product', 'quantity' => 1],
            ],
            'payment_method' => 'card',
        ];

        $modified = [
            'items' => [
                ['id' => '5', 'type' => 'product', 'quantity' => 2],
            ],
            'payment_method' => 'card',
        ];

        $this->assertNotSame(
            Checkout::fingerprintFor($base),
            Checkout::fingerprintFor($modified)
        );
    }

    public function test_fingerprint_differentiates_product_and_recipe_kit(): void
    {
        $productPayload = [
            'items' => [
                ['id' => '15', 'type' => 'product', 'quantity' => 1],
            ],
        ];

        $kitPayload = [
            'items' => [
                ['id' => '15', 'type' => 'recipe-kit', 'quantity' => 1],
            ],
        ];

        $this->assertNotSame(
            Checkout::fingerprintFor($productPayload),
            Checkout::fingerprintFor($kitPayload)
        );
    }

    public function test_fingerprint_changes_when_fulfillment_details_change(): void
    {
        $pickup = [
            'items' => [['id' => '1', 'quantity' => 1]],
            'fulfillment_type' => 'Store Pickup',
            'pickup_timing_mode' => 'asap',
        ];

        $delivery = [
            'items' => [['id' => '1', 'quantity' => 1]],
            'fulfillment_type' => 'Home Delivery',
            'delivery_address' => '123 Main St',
        ];

        $this->assertNotSame(
            Checkout::fingerprintFor($pickup),
            Checkout::fingerprintFor($delivery)
        );
    }
}
