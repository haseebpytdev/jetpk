<?php

namespace App\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateManualLocalGroupInventoryRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'title' => ['sometimes', 'required', 'string', 'max:200'],
            'sector' => ['sometimes', 'required', 'string', 'max:32', 'regex:/^[A-Za-z]{3}-[A-Za-z]{3}$/'],
            'airline_name' => ['nullable', 'string', 'max:120'],
            'departure_date' => ['sometimes', 'required', 'date'],
            'return_date' => ['nullable', 'date', 'after_or_equal:departure_date'],
            'total_seats' => ['sometimes', 'required', 'integer', 'min:0', 'max:20'],
            'price' => ['sometimes', 'required', 'numeric', 'min:1', 'max:9999999'],
            'baggage' => ['nullable', 'string', 'max:120'],
            'refund_change_notes' => ['nullable', 'string', 'max:2000'],
            'audience' => ['sometimes', Rule::in(['b2c', 'b2b', 'boundary'])],
            'is_active' => ['sometimes', 'boolean'],
        ];
    }
}
