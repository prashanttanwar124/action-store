<?php

namespace App\Http\Requests\Admin;

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
            'prep_time_minutes' => ['nullable', 'integer', 'min:5', 'max:240'],
            'busy_mode_extra_minutes' => ['nullable', 'integer', 'min:0', 'max:180'],
            'busy_mode_reason' => ['nullable', 'string', 'max:100'],
            'pickup_time' => ['nullable', 'string', 'max:100'],
            'pickup_slot_window_label' => ['nullable', 'string', 'max:100'],
            'pickup_slot_start_time' => ['nullable', 'string', 'max:10'],
            'pickup_slot_end_time' => ['nullable', 'string', 'max:10'],
            'pickup_slot_duration_minutes' => ['nullable', 'integer', 'min:15', 'max:240'],
            'max_orders_per_slot' => ['nullable', 'integer', 'min:1', 'max:500'],
            'min_order_amount' => ['nullable', 'numeric', 'min:0'],
            'pickup_days' => ['nullable', 'array'],
            'pickup_days.*' => ['string', 'in:monday,tuesday,wednesday,thursday,friday,saturday,sunday'],
            'pickup_slots' => ['nullable', 'array'],
            'curbside_instructions' => ['nullable', 'string', 'max:1000'],
            'announcement' => ['nullable', 'string', 'max:255'],
            'maps_url' => ['nullable', 'string', 'max:500', 'regex:/^(https?:\/\/|\/)/i'],
            'is_pickup_active' => ['boolean'],
            'is_delivery_active' => ['boolean'],
            'delivery_days' => ['nullable', 'array'],
            'delivery_days.*' => ['string', 'in:monday,tuesday,wednesday,thursday,friday,saturday,sunday'],
            'delivery_fee' => ['nullable', 'numeric', 'min:0'],
            'free_delivery_threshold' => ['nullable', 'numeric', 'min:0'],
            'delivery_estimated_time' => ['nullable', 'string', 'max:100'],
        ];
    }
}
