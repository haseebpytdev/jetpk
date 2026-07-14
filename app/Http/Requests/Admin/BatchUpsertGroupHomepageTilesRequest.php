<?php

namespace App\Http\Requests\Admin;

use App\Enums\GroupHomepageTileTargetType;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class BatchUpsertGroupHomepageTilesRequest extends FormRequest
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
            'tiles' => ['required', 'array', 'min:1'],
            'tiles.*.target_type' => ['required', Rule::in([
                GroupHomepageTileTargetType::All->value,
                GroupHomepageTileTargetType::Category->value,
            ])],
            'tiles.*.target_value' => ['nullable', 'string', 'max:120'],
            'tiles.*.title' => ['nullable', 'string', 'max:120'],
            'tiles.*.image' => ['nullable', 'image', 'mimes:jpg,jpeg,png,webp', 'max:2048'],
            'tiles.*.is_active' => ['sometimes'],
            'tiles.*.sort_order' => ['nullable', 'integer', 'min:0', 'max:9999'],
        ];
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator): void {
            $tiles = $this->input('tiles');

            if (! is_array($tiles)) {
                return;
            }

            foreach ($tiles as $formKey => $tile) {
                if (! is_array($tile)) {
                    continue;
                }

                $targetType = (string) ($tile['target_type'] ?? '');

                if ($targetType === GroupHomepageTileTargetType::Category->value) {
                    $targetValue = trim((string) ($tile['target_value'] ?? ''));
                    if ($targetValue === '' && is_string($formKey) && str_starts_with($formKey, 'category:')) {
                        $targetValue = substr($formKey, strlen('category:'));
                    }

                    if ($targetValue === '') {
                        $validator->errors()->add(
                            "tiles.{$formKey}.target_value",
                            'Category slug is required for category tiles.'
                        );
                    }
                }
            }
        });
    }
}
