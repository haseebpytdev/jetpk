<?php

namespace App\Http\Requests\Admin;

use App\Enums\PromoCodeAppliesTo;
use App\Enums\PromoCodeStatus;
use App\Enums\PromoCodeType;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StorePromoCodeRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    protected function prepareForValidation(): void
    {
        if ($this->filled('code')) {
            $this->merge(['code' => strtoupper($this->string('code')->toString())]);
        }
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        $agencyId = $this->resolveAgencyIdForUnique();

        return [
            'code' => [
                'required',
                'string',
                'max:64',
                'regex:/^[A-Z0-9_-]+$/',
                Rule::unique('promo_codes', 'code')->where(fn ($q) => $q->where('agency_id', $agencyId)),
            ],
            'name' => ['nullable', 'string', 'max:255'],
            'type' => ['required', Rule::enum(PromoCodeType::class)],
            'value' => ['required', 'numeric'],
            'currency' => ['nullable', 'string', 'size:3'],
            'min_amount' => ['nullable', 'numeric', 'min:0'],
            'max_discount' => ['nullable', 'numeric', 'min:0'],
            'starts_at' => ['nullable', 'date'],
            'ends_at' => ['nullable', 'date', 'after_or_equal:starts_at'],
            'usage_limit' => ['nullable', 'integer', 'min:1'],
            'per_user_limit' => ['nullable', 'integer', 'min:1'],
            'applies_to' => ['required', Rule::enum(PromoCodeAppliesTo::class)],
            'status' => ['required', Rule::enum(PromoCodeStatus::class)],
            'internal_testing_only' => ['nullable', 'boolean'],
        ];
    }

    public function withValidator($validator): void
    {
        $validator->after(function ($validator): void {
            $type = $this->input('type');
            $value = (float) $this->input('value', 0);
            $testingOnly = $this->boolean('internal_testing_only');

            if ($value <= 0) {
                $validator->errors()->add('value', 'Value must be greater than zero.');
            }

            if ($type === PromoCodeType::Percent->value) {
                $max = ($testingOnly && config('ota.promo.allow_zero_payable', false)) ? 100 : 99;
                if ($value > $max) {
                    $validator->errors()->add('value', $testingOnly
                        ? 'Percentage discount cannot exceed 100.'
                        : 'Percentage discount cannot exceed 99 unless internal testing only is enabled with zero-payable config.');
                }
                if ($value < 1) {
                    $validator->errors()->add('value', 'Percentage discount must be at least 1.');
                }
            }

            if ($type === PromoCodeType::Fixed->value && ! $this->filled('currency')) {
                $validator->errors()->add('currency', 'Currency is required for fixed amount promos.');
            }
        });
    }

    protected function resolveAgencyIdForUnique(): ?int
    {
        if ($this->user()?->isPlatformAdmin() && $this->filled('agency_id')) {
            return $this->integer('agency_id');
        }

        return $this->user()?->current_agency_id;
    }
}
