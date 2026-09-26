<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

class RecipeKit extends Model
{
    use HasFactory;

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'name',
        'slug',
        'subtitle_tag',
        'description',
        'servings',
        'cooking_time',
        'difficulty',
        'price',
        'original_price',
        'image',
        'images',
        'recipe_steps',
        'is_active',
    ];

    /**
     * The accessors to append to the model's array form.
     *
     * @var list<string>
     */
    protected $appends = [
        'images_list',
        'calculated_individual_total',
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
            'recipe_steps' => 'array',
            'is_active' => 'boolean',
        ];
    }

    /**
     * Linked products/ingredients for Group Buy.
     */
    public function products(): BelongsToMany
    {
        return $this->belongsToMany(Product::class, 'recipe_kit_products')
            ->withPivot(['quantity', 'unit_notes', 'is_optional', 'sort_order'])
            ->withTimestamps()
            ->orderBy('recipe_kit_products.sort_order');
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

        return ['/images/products/paneer_curry.jpg'];
    }

    /**
     * Calculate sum of individual linked products if bought separately.
     */
    public function getCalculatedIndividualTotalAttribute(): float
    {
        if (! $this->relationLoaded('products')) {
            return 0.0;
        }

        $total = 0.0;
        foreach ($this->products as $product) {
            $qty = $product->pivot->quantity ?? 1;
            $total += ($product->price * $qty);
        }

        return round($total, 2);
    }
}
