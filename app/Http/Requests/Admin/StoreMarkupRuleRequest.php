<?php

namespace App\Http\Requests\Admin;

use App\Enums\MarkupRuleStatus;
use App\Enums\MarkupRuleType;
use App\Enums\MarkupValueType;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreMarkupRuleRequest extends FormRequest
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
            'name' => ['required', 'string', 'max:180'],
            'rule_type' => ['required', Rule::enum(MarkupRuleType::class)],
            'value' => ['required', 'numeric', 'min:0'],
            'value_type' => ['required', Rule::enum(MarkupValueType::class)],
            'applies_to' => ['nullable'],
            'supplier_key' => ['nullable', 'string', 'max:80'],
            'airline_code' => ['nullable', 'string', 'max:8'],
            'origin' => ['nullable', 'string', 'max:8'],
            'destination' => ['nullable', 'string', 'max:8'],
            'route_direction' => ['nullable', 'string', 'in:one_way,both'],
            'agent_id' => ['nullable', 'string', 'max:40'],
            'cabin' => ['nullable', 'string', 'max:40'],
            'fare_family' => ['nullable', 'string', 'max:80'],
            'priority' => ['nullable', 'integer', 'min:1', 'max:9999'],
            'status' => ['required', Rule::enum(MarkupRuleStatus::class)],
            'starts_at' => ['nullable', 'date'],
            'ends_at' => ['nullable', 'date', 'after_or_equal:starts_at'],
            'meta_notes' => ['nullable', 'string', 'max:2000'],
        ];
    }

    public function withValidator($validator): void
    {
        $validator->after(function ($validator): void {
            $valueType = $this->input('value_type');
            $value = (float) $this->input('value', 0);

            if ($valueType === MarkupValueType::Percentage->value && $value > 100) {
                $validator->errors()->add('value', 'Percentage markup cannot exceed 100.');
            }
        });
    }
}
