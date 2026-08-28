<?php

namespace App\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreManualLocalGroupInventoryRequest extends FormRequest
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
            'title' => ['required', 'string', 'max:200'],
            'sector' => ['required', 'string', 'max:32', 'regex:/^[A-Za-z]{3}-[A-Za-z]{3}$/'],
            'airline_name' => ['nullable', 'string', 'max:120'],
            'package_type' => ['nullable', 'string', 'max:80'],
            'departure_date' => ['required', 'date', 'after:today'],
            'return_date' => ['nullable', 'date', 'after_or_equal:departure_date'],
            'total_seats' => ['required', 'integer', 'min:1', 'max:20'],
            'price' => ['required', 'numeric', 'min:1', 'max:9999999'],
            'currency' => ['nullable', 'string', 'size:3'],
            'baggage' => ['nullable', 'string', 'max:120'],
            'refund_change_notes' => ['nullable', 'string', 'max:2000'],
            'audience' => ['required', Rule::in(['b2c', 'b2b', 'boundary'])],
            'is_active' => ['sometimes', 'boolean'],
        ];
    }
}
