<?php

namespace App\Models;

use Database\Factories\ProductFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Product extends Model
{
    /** @use HasFactory<ProductFactory> */
    use HasFactory;

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'slug',
        'name',
        'subtitle_tag',
        'category',
        'category_title',
        'price',
        'original_price',
        'unit_price',
        'stock_badge',
        'photo_label',
        'image',
        'size_main',
        'size_sub',
        'freshness_line',
        'description',
        'servings',
        'cooking_time',
        'is_recipe_kit',
        'buy_again',
        'has_subscription',
        'frequently_bought_together',
        'recipe_ingredients',
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'price' => 'float',
            'original_price' => 'float',
            'is_recipe_kit' => 'boolean',
            'buy_again' => 'boolean',
            'has_subscription' => 'boolean',
            'frequently_bought_together' => 'array',
            'recipe_ingredients' => 'array',
        ];
    }
}
