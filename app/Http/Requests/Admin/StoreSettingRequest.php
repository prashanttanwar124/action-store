<?php

namespace App\Http\Requests\Admin;

use App\Models\StoreSetting;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class StoreSettingRequest extends FormRequest
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
            'tagline' => ['nullable', 'string', 'max:255'],
            'hindi_tagline' => ['nullable', 'string', 'max:50'],
            'address' => ['required', 'string', 'max:255'],
            'city' => ['required', 'string', 'max:100'],
            'state' => ['required', 'string', 'max:50'],
            'zip' => ['required', 'string', 'max:20'],
            'phone' => ['required', 'string', 'max:50'],
            'email' => ['required', 'email', 'max:255'],
            'opening_hours' => ['required', 'string', 'max:100'],
            ...StoreSetting::pickupValidationRules(),
            'announcement' => ['nullable', 'string', 'max:255'],
            'maps_url' => ['nullable', 'string', 'max:500', 'regex:/^(https?:\/\/|\/)/i'],
            'is_delivery_active' => ['boolean'],
            'delivery_days' => ['nullable', 'array'],
            'delivery_days.*' => ['string', 'in:monday,tuesday,wednesday,thursday,friday,saturday,sunday'],
            'delivery_fee' => ['nullable', 'numeric', 'min:0'],
            'free_delivery_threshold' => ['nullable', 'numeric', 'min:0'],
            'delivery_estimated_time' => ['nullable', 'string', 'max:100'],
        ];
    }
}
