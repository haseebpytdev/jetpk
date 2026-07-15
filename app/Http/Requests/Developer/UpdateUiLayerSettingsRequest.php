<?php

namespace App\Http\Requests\Developer;

use App\Support\Ui\UiLayerRegistry;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;

class UpdateUiLayerSettingsRequest extends FormRequest
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
            'layers' => ['required', 'array'],
            'layers.*' => ['nullable', 'boolean'],
        ];
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator): void {
            $layers = $this->input('layers', []);
            if (! is_array($layers)) {
                return;
            }

            foreach (array_keys($layers) as $key) {
                if (UiLayerRegistry::find((string) $key) === null) {
                    $validator->errors()->add('layers', "Unknown UI layer key: {$key}");
                }
            }
        });
    }

    /**
     * @return array<string, bool>
     */
    public function normalizedChanges(): array
    {
        /** @var array<string, mixed> $layers */
        $layers = $this->input('layers', []);
        $changes = [];

        foreach ($layers as $key => $value) {
            $changes[(string) $key] = filter_var($value, FILTER_VALIDATE_BOOL);
        }

        return $changes;
    }
}
