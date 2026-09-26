<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Slider extends Model
{
    use HasFactory;

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'title',
        'tag',
        'cta_text',
        'link_url',
        'image',
        'images',
        'photo_label',
        'bg_color',
        'sort_order',
        'is_active',
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
            'images' => 'array',
            'sort_order' => 'integer',
            'is_active' => 'boolean',
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

        return ['/images/products/sweets.jpg'];
    }
}
