<?php

namespace Database\Factories;

use App\Models\Admin;
use App\Models\PosShift;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<PosShift>
 */
class PosShiftFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $openingCash = 200.00;

        return [
            'admin_id' => fn () => Admin::first()?->id ?? Admin::create([
                'name' => fake()->name(),
                'email' => fake()->unique()->safeEmail(),
                'password' => 'password',
            ])->id,
            'status' => 'open',
            'opening_cash' => $openingCash,
            'cash_sales' => 0.00,
            'cash_deposits' => 0.00,
            'terminal_sales' => 0.00,
            'cash_refunds' => 0.00,
            'cash_withdrawals' => 0.00,
            'terminal_refunds' => 0.00,
            'expected_cash' => $openingCash,
            'orders_count' => 0,
            'opened_at' => now(),
        ];
    }
}
