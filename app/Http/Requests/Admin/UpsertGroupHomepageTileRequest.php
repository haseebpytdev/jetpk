<?php

namespace App\Http\Requests\Admin;

use App\Enums\GroupHomepageTileTargetType;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpsertGroupHomepageTileRequest extends FormRequest
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
            'target_type' => ['required', Rule::in([
                GroupHomepageTileTargetType::All->value,
                GroupHomepageTileTargetType::Category->value,
            ])],
            'target_value' => [
                'nullable',
                'string',
                'max:120',
                Rule::requiredIf(fn (): bool => $this->string('target_type')->toString() === GroupHomepageTileTargetType::Category->value),
            ],
            'title' => ['nullable', 'string', 'max:120'],
            'image' => ['nullable', 'image', 'mimes:jpg,jpeg,png,webp', 'max:2048'],
            'is_active' => ['sometimes', 'boolean'],
            'sort_order' => ['nullable', 'integer', 'min:0', 'max:9999'],
        ];
    }
}
