<?php

namespace Database\Factories;

use App\Models\Order;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Order>
 */
class OrderFactory extends Factory
{
    protected $model = Order::class;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $subtotal = fake()->randomFloat(2, 10, 150);

        return [
            'order_number' => '#MM-'.fake()->unique()->numberBetween(10000, 99999),
            'user_id' => User::factory(),
            'customer_name' => fake()->name(),
            'customer_email' => fake()->safeEmail(),
            'customer_phone' => fake()->phoneNumber(),
            'subtotal' => $subtotal,
            'discount' => 0.00,
            'total' => $subtotal,
            'points_earned' => (int) floor($subtotal),
            'payment_method' => fake()->randomElement(['card', 'apple-pay', 'google-pay']),
            'fulfillment_type' => 'Store Pickup',
            'pickup_slot' => 'Today 4–5 pm',
            'pickup_location' => 'Masala Mart — Edison',
            'status' => 'confirmed',
            'notes' => null,
        ];
    }
}
