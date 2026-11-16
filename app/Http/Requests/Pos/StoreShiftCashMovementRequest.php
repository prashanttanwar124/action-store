<?php

namespace App\Http\Requests\Pos;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class StoreShiftCashMovementRequest extends FormRequest
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
            'type' => ['required', 'string', 'in:deposit,withdrawal'],
            'amount' => ['required', 'numeric', 'min:0.01', 'max:100000'],
            'reason' => ['required', 'string', 'min:2', 'max:120'],
            'notes' => ['nullable', 'string', 'max:500'],
        ];
    }

    /**
     * Custom validation messages for cash drawer movements.
     *
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'amount.min' => 'Enter a cash drawer adjustment amount greater than $0.00.',
            'reason.required' => 'Enter a brief reason (e.g. Safe Drop or Coins Top-Up).',
        ];
    }
}
