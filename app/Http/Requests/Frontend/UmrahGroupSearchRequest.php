<?php

namespace App\Http\Requests\Frontend;

use Illuminate\Foundation\Http\FormRequest;

class UmrahGroupSearchRequest extends FormRequest
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
            'airline_id' => ['nullable', 'integer', 'min:1'],
            'type' => ['nullable', 'string', 'max:50'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function filters(): array
    {
        $validated = $this->validated();

        return array_filter([
            'sector' => isset($validated['sector']) ? trim((string) $validated['sector']) : null,
            'dept_date' => isset($validated['dept_date']) ? trim((string) $validated['dept_date']) : null,
            'airline_id' => isset($validated['airline_id']) ? (string) $validated['airline_id'] : null,
            'type' => isset($validated['type']) ? trim((string) $validated['type']) : null,
        ], static fn (?string $value): bool => $value !== null && $value !== '');
    }
}
