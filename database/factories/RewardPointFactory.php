<?php

namespace Database\Factories;

use App\Models\RewardPoint;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<RewardPoint>
 */
class RewardPointFactory extends Factory
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
            'order_id' => null,
            'points' => 100,
            'description' => 'Welcome bonus',
        ];
    }
}
