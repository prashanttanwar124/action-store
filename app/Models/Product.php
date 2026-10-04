<?php

namespace App\Models;

use Database\Factories\ProductFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Validation\ValidationException;

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
        'stock',
        'stock_badge',
        'photo_label',
        'image',
        'images',
        'size_main',
        'size_sub',
        'freshness_line',
        'description',
        'supplier_id',
        'buy_again',
        'has_subscription',
        'frequently_bought_together',
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
            'stock' => 'integer',
            'images' => 'array',
            'buy_again' => 'boolean',
            'has_subscription' => 'boolean',
            'frequently_bought_together' => 'array',
        ];
    }

    /**
     * @return BelongsTo<Category, $this>
     */
    public function categoryRelation(): BelongsTo
    {
        return $this->belongsTo(Category::class, 'category', 'slug');
    }

    /**
     * @return BelongsTo<Supplier, $this>
     */
    public function supplier(): BelongsTo
    {
        return $this->belongsTo(Supplier::class);
    }

    /**
     * Recipe kits that include this product as an ingredient.
     */
    public function recipeKits()
    {
        return $this->belongsToMany(RecipeKit::class, 'recipe_kit_products')
            ->withPivot(['quantity', 'unit_notes', 'is_optional', 'sort_order'])
            ->withTimestamps();
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

    /**
     * Compute dynamic stock badge automatically based on stock quantity.
     */
    public function getStockBadgeAttribute(?string $value): string
    {
        $stock = (int) ($this->stock ?? 50);

        if ($stock <= 0) {
            return 'Out of stock';
        }

        if ($stock <= 25) {
            return "In stock · {$stock} left";
        }

        if (! empty($value) && ! preg_match('/\b(in stock|out of stock|\d+\s*left)\b/i', $value)) {
            return $value;
        }

        return 'In stock';
    }

    /**
     * Decrement inventory stock count and update stock badge accordingly.
     *
     * @throws ValidationException
     */
    public function decrementStock(int $quantity = 1): void
    {
        $currentStock = (int) ($this->stock ?? 0);
        if ($currentStock < $quantity) {
            throw ValidationException::withMessages([
                'items' => ["Insufficient stock for '{$this->name}'. Only {$currentStock} available in stock."],
            ]);
        }

        $this->stock = $currentStock - $quantity;
        $this->syncStockBadge();
        $this->save();
    }

    /**
     * Keep the stock badge string in sync when stock is edited.
     */
    public function syncStockBadge(): void
    {
        $stock = (int) ($this->stock ?? 0);
        if ($stock <= 0) {
            $this->stock_badge = 'Out of stock';
        } elseif ($stock <= 25) {
            $this->stock_badge = "In stock · {$stock} left";
        } else {
            $this->stock_badge = 'In stock';
        }
    }
}
