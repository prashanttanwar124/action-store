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
        'images',
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
     * The accessors to append to the model's array form.
     *
     * @var list<string>
     */
    protected $appends = [
        'images_list',
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
            'images' => 'array',
            'is_recipe_kit' => 'boolean',
            'buy_again' => 'boolean',
            'has_subscription' => 'boolean',
            'frequently_bought_together' => 'array',
            'recipe_ingredients' => 'array',
        ];
    }

    /**
     * Get all images as a unified list.
     *
     * @return list<string>
     */
    public function getImagesListAttribute(): array
    {
        if (is_array($this->images) && ! empty($this->images)) {
            return array_values(array_filter($this->images));
        }

        if (! empty($this->image)) {
            return [$this->image];
        }

        return ['/images/products/atta.jpg'];
    }
}
