<?php

namespace App\Http\Requests\Admin;

use App\Models\HomepageFeaturedFare;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreHomepageFeaturedFareRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    protected function prepareForValidation(): void
    {
        $payload = [];
        foreach (['origin_code', 'destination_code'] as $field) {
            if ($this->filled($field)) {
                $payload[$field] = strtoupper(trim((string) $this->input($field)));
            }
        }
        if ($payload !== []) {
            $this->merge($payload);
        }
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'title' => ['nullable', 'string', 'max:120'],
            'origin_code' => ['required', 'string', 'size:3', 'regex:/^[A-Z]{3}$/'],
            'destination_code' => ['required', 'string', 'size:3', 'regex:/^[A-Z]{3}$/', 'different:origin_code'],
            'date_offset_days' => ['required', 'integer', Rule::in(HomepageFeaturedFare::ALLOWED_DATE_OFFSETS)],
            'cabin' => ['nullable', 'string', 'max:32', Rule::in(['economy'])],
            'adults' => ['nullable', 'integer', 'min:1', 'max:9'],
            'is_enabled' => ['nullable', 'boolean'],
            'sort_order' => ['nullable', 'integer', 'min:0', 'max:9999'],
        ];
    }
}
