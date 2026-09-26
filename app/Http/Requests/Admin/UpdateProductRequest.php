<?php

namespace App\Http\Requests\Admin;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateProductRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return $this->user('admin') !== null;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        $productId = $this->route('product')?->id ?? $this->route('product');

        return [
            'name' => ['required', 'string', 'max:255'],
            'slug' => ['nullable', 'string', 'max:255', 'alpha_dash', Rule::unique('products', 'slug')->ignore($productId)],
            'category' => ['required', 'string', 'max:100'],
            'category_title' => ['nullable', 'string', 'max:100'],
            'subtitle_tag' => ['nullable', 'string', 'max:100'],
            'price' => ['required', 'numeric', 'min:0', 'max:99999.99'],
            'original_price' => ['nullable', 'numeric', 'min:0', 'max:99999.99'],
            'unit_price' => ['nullable', 'string', 'max:50'],
            'stock_badge' => ['nullable', 'string', 'max:50'],
            'photo_label' => ['nullable', 'string', 'max:50'],
            'size_main' => ['nullable', 'string', 'max:50'],
            'size_sub' => ['nullable', 'string', 'max:100'],
            'freshness_line' => ['nullable', 'string', 'max:255'],
            'description' => ['nullable', 'string'],
            'servings' => ['nullable', 'string', 'max:50'],
            'cooking_time' => ['nullable', 'string', 'max:50'],
            'is_recipe_kit' => ['boolean'],
            'buy_again' => ['boolean'],
            'has_subscription' => ['boolean'],
            'image_files' => ['nullable', 'array'],
            'image_files.*' => ['file', 'image', 'mimes:jpeg,png,jpg,webp,gif', 'max:10240'],
            'existing_images' => ['nullable', 'array'],
            'existing_images.*' => ['string'],
            'image_urls' => ['nullable', 'array'],
            'image_urls.*' => ['string', 'max:500'],
        ];
    }
}
