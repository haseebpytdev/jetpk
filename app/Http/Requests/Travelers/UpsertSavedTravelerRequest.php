<?php

namespace App\Http\Requests\Travelers;

use App\Support\Geo\CountryList;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class UpsertSavedTravelerRequest extends FormRequest
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
            'first_name' => ['required', 'string', 'max:120'],
            'last_name' => ['required', 'string', 'max:120'],
            'title' => ['required', 'string', 'max:16', Rule::in(['Mr', 'Mrs', 'Ms', 'Miss', 'Dr', 'Mx'])],
            'gender' => ['required', 'string', 'max:32', Rule::in(['male', 'female', 'other'])],
            'date_of_birth' => ['required', 'date', 'before:today'],
            'nationality' => ['required', 'string', 'size:2', Rule::in(CountryList::alpha2Codes())],
            'document_type' => ['required', 'string', Rule::in(['passport', 'national_id'])],
            'document_number' => ['nullable', 'string', 'max:64'],
            'document_expiry' => ['nullable', 'date', 'after:today'],
            'issuing_country' => ['nullable', 'string', 'size:2', Rule::in(CountryList::alpha2Codes())],
            'phone' => ['nullable', 'string', 'max:64'],
            'email' => ['nullable', 'email', 'max:255'],
            'is_default' => ['sometimes', 'boolean'],
        ];
    }

    public function withValidator($validator): void
    {
        $validator->after(function (Validator $validator): void {
            $docType = (string) $this->input('document_type', '');

            if ($docType === 'passport') {
                foreach ([
                    'document_number' => __('Passport number'),
                    'document_expiry' => __('Passport expiry date'),
                    'issuing_country' => __('Passport issuing country'),
                ] as $field => $label) {
                    if (trim((string) $this->input($field, '')) === '') {
                        $validator->errors()->add($field, __(':label is required.', ['label' => $label]));
                    }
                }
            } elseif ($docType === 'national_id') {
                if (trim((string) $this->input('document_number', '')) === '') {
                    $validator->errors()->add('document_number', __('National ID number is required.'));
                }
            }
        });
    }

    /**
     * @return array<string, mixed>
     */
    public function travelerPayload(): array
    {
        $data = $this->validated();
        $data['is_default'] = $this->boolean('is_default');

        if ($this->isMethod('patch') || $this->isMethod('put')) {
            if (! array_key_exists('document_number', $data) || trim((string) ($data['document_number'] ?? '')) === '') {
                unset($data['document_number']);
            }
        }

        return $data;
    }
}
