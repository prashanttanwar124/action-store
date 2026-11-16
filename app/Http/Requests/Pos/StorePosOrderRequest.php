<?php

namespace App\Http\Requests\Pos;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class StorePosOrderRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return (bool) ($this->user('admin')?->can('operate pos') ?? false);
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'idempotency_key' => ['nullable', 'string', 'max:64'],
            'expected_total' => ['nullable', 'numeric', 'min:0', 'max:999999.99'],
            'items' => ['required', 'array', 'min:1', 'max:200'],
            'items.*.product_id' => ['nullable', 'integer', 'exists:products,id'],
            'items.*.name' => ['required_without:items.*.product_id', 'nullable', 'string', 'max:255'],
            'items.*.unit_price' => ['required_without:items.*.product_id', 'nullable', 'numeric', 'min:0.01', 'max:99999.99'],
            'items.*.quantity' => ['required', 'integer', 'min:1', 'max:9999'],
            'items.*.weight' => ['nullable', 'numeric', 'min:0.001', 'max:9999.999'],
            'items.*.is_taxable' => ['nullable', 'boolean'],
            'customer_id' => ['nullable', 'integer', 'exists:users,id'],
            'customer_name' => ['nullable', 'string', 'max:255'],
            'customer_phone' => ['nullable', 'string', 'max:50'],
            'discount_type' => ['nullable', 'string', 'in:none,fixed,percent'],
            'discount_value' => ['nullable', 'numeric', 'min:0', 'max:99999.99'],
            'points_to_redeem' => ['nullable', 'integer', 'min:0', 'max:1000000'],
            'payment_method' => ['required', 'string', 'in:cash,pos_terminal,split'],
            'cash_amount' => ['nullable', 'numeric', 'min:0', 'max:999999.99'],
            'terminal_amount' => ['nullable', 'numeric', 'min:0', 'max:999999.99'],
            'cash_tendered' => ['nullable', 'numeric', 'min:0', 'max:999999.99'],
            'payment_reference' => ['nullable', 'string', 'max:120'],
            'notes' => ['nullable', 'string', 'max:500'],
        ];
    }

    /**
     * Custom validation messages for counter register clarity.
     *
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'items.required' => 'Add at least one item to the register cart before charging.',
            'items.min' => 'Add at least one item to the register cart before charging.',
            'items.*.name.required_without' => 'Custom open-price items require a description.',
            'items.*.unit_price.required_without' => 'Custom open-price items require a unit price.',
        ];
    }
}
