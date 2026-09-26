<?php

namespace App\Http\Requests\Admin;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class StoreRecipeKitRequest extends FormRequest
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
        return [
            'name' => ['required', 'string', 'max:255'],
            'slug' => ['nullable', 'string', 'max:255', 'alpha_dash', 'unique:recipe_kits,slug'],
            'subtitle_tag' => ['nullable', 'string', 'max:255'],
            'description' => ['nullable', 'string'],
            'servings' => ['nullable', 'string', 'max:50'],
            'cooking_time' => ['nullable', 'string', 'max:50'],
            'difficulty' => ['nullable', 'string', 'max:50'],
            'price' => ['required', 'numeric', 'min:0', 'max:99999.99'],
            'original_price' => ['nullable', 'numeric', 'min:0', 'max:99999.99'],
            'image_files' => ['nullable', 'array'],
            'image_files.*' => ['file', 'image', 'mimes:jpeg,png,jpg,webp,gif', 'max:10240'],
            'image_urls' => ['nullable', 'array'],
            'image_urls.*' => ['string', 'max:500'],
            'products' => ['nullable', 'array'],
            'products.*.product_id' => ['required_with:products', 'exists:products,id'],
            'products.*.quantity' => ['nullable', 'integer', 'min:1', 'max:99'],
            'products.*.unit_notes' => ['nullable', 'string', 'max:100'],
            'products.*.is_optional' => ['boolean'],
            'recipe_steps' => ['nullable', 'array'],
            'is_active' => ['boolean'],
        ];
    }
}
