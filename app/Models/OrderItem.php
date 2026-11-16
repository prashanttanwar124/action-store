<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class OrderItem extends Model
{
    use HasFactory;

    /**
     * @var list<string>
     */
    protected $fillable = [
        'order_id',
        'product_id',
        'recipe_kit_id',
        'name',
        'size',
        'unit_type',
        'unit_price',
        'quantity',
        'weight',
        'refunded_quantity',
        'restocked_quantity',
        'total_price',
        'is_taxable',
        'tax_amount',
        'refunded_amount',
        'refund_reason',
        'is_subscribed',
        'is_packed',
        'image',
    ];

    /**
     * @var array<string, string>
     */
    protected $casts = [
        'unit_price' => 'decimal:2',
        'total_price' => 'decimal:2',
        'tax_amount' => 'decimal:2',
        'refunded_amount' => 'decimal:2',
        'weight' => 'decimal:3',
        'quantity' => 'integer',
        'refunded_quantity' => 'integer',
        'restocked_quantity' => 'integer',
        'is_taxable' => 'boolean',
        'is_subscribed' => 'boolean',
        'is_packed' => 'boolean',
    ];

    /**
     * @return BelongsTo<Order, $this>
     */
    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class);
    }

    /**
     * @return BelongsTo<Product, $this>
     */
    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    /**
     * @return BelongsTo<RecipeKit, $this>
     */
    public function recipeKit(): BelongsTo
    {
        return $this->belongsTo(RecipeKit::class);
    }
}
