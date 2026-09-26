<?php

namespace Database\Factories;

use App\Models\Product;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Product>
 */
class ProductFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $name = fake()->words(3, true);

        return [
            'name' => ucwords($name),
            'slug' => fake()->unique()->slug(),
            'category' => fake()->randomElement(['grocery', 'spices', 'dairy', 'sweets', 'vegetables', 'recipe-kits']),
            'category_title' => 'Pantry & Groceries',
            'price' => fake()->randomFloat(2, 2, 50),
            'original_price' => fake()->optional()->randomFloat(2, 5, 60),
            'stock_badge' => 'In Stock',
            'size_main' => fake()->randomElement(['500 g', '1 kg', '10 lb', '200 g']),
            'image' => '/images/products/atta.jpg',
            'images' => ['/images/products/atta.jpg'],
            'description' => fake()->paragraph(),
        ];
    }
}
