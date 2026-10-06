<?php

namespace Database\Factories;

use App\Models\Checkout;
use App\Models\Product;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<Checkout>
 */
class CheckoutFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'user_id' => User::factory(),
            'fingerprint' => hash('sha256', Str::random(32)),
            'stripe_payment_id' => 'pi_'.Str::random(16),
            'customer_name' => fake()->name(),
            'customer_email' => fake()->safeEmail(),
            'customer_phone' => fake()->phoneNumber(),
            'subtotal' => 0,
            'delivery_fee' => 0,
            'total' => 0,
            'payment_method' => 'card',
            'fulfillment_type' => 'Store Pickup',
            'pickup_slot' => 'ASAP (Ready in ~15 mins)',
            'pickup_location' => 'Masala Mart — Edison',
            'items' => [],
            'reserved_stock' => [],
            'expires_at' => now()->addMinutes(Checkout::LIFETIME_MINUTES),
        ];
    }

    /**
     * A checkout holding the given quantity of a product (its stock is assumed already reserved).
     */
    public function holding(Product $product, int $quantity): static
    {
        $lineTotal = round((float) $product->price * $quantity, 2);

        return $this->state(fn () => [
            'subtotal' => $lineTotal,
            'total' => $lineTotal,
            'items' => [[
                'product_id' => $product->id,
                'name' => $product->name,
                'size' => $product->size_main,
                'unit_price' => (float) $product->price,
                'quantity' => $quantity,
                'total_price' => $lineTotal,
                'is_subscribed' => false,
                'image' => $product->image,
            ]],
            'reserved_stock' => [$product->id => $quantity],
        ]);
    }

    /**
     * A checkout whose customer has been inactive past its lifetime.
     */
    public function expired(): static
    {
        return $this->state(fn () => ['expires_at' => now()->subMinute()]);
    }
}
