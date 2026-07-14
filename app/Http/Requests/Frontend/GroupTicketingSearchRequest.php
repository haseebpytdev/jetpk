<?php

namespace App\Http\Requests\Frontend;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class GroupTicketingSearchRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'sector' => ['nullable', 'string', 'max:100'],
            'dept_date' => ['nullable', 'date'],
            'date_from' => ['nullable', 'date'],
            'date_to' => ['nullable', 'date', 'after_or_equal:date_from'],
            'category' => ['nullable', 'string', 'max:80'],
            'type' => ['nullable', 'string', 'max:80'],
            'airline' => ['nullable', 'string', 'max:120'],
            'airline_id' => ['nullable', 'integer', 'min:1'],
            'flexible' => ['nullable', 'boolean'],
            'page' => ['nullable', 'integer', 'min:1'],
            'sort' => ['nullable', 'string', Rule::in(['departure', 'price', 'seats', 'airline'])],
            'min_seats' => ['nullable', 'integer', 'min:1', 'max:50'],
            'price_min' => ['nullable', 'numeric', 'min:0'],
            'price_max' => ['nullable', 'numeric', 'min:0'],
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function filters(): array
    {
        $filters = [];
        foreach ([
            'sector', 'dept_date', 'date_from', 'date_to', 'category', 'type',
            'airline', 'airline_id', 'flexible', 'page', 'sort', 'min_seats', 'price_min', 'price_max',
        ] as $key) {
            $value = $this->input($key);
            if ($value !== null && $value !== '') {
                $filters[$key] = $value;
            }
        }

        return $filters;
    }
}
