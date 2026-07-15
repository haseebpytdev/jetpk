<?php

namespace App\Http\Requests\Support;

use App\Enums\SupportTicketCategory;
use App\Support\Security\TurnstileVerifier;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class StorePublicSupportTicketRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    protected function prepareForValidation(): void
    {
        $formType = $this->input('form_type', 'support');

        if ($formType === 'contact') {
            $this->merge([
                'subject' => 'General inquiry',
                'category' => SupportTicketCategory::Other->value,
            ]);
        }
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        $isSupportForm = ($this->input('form_type', 'support') === 'support');

        return array_merge([
            'form_type' => ['nullable', 'string', Rule::in(['support', 'contact'])],
            'name' => [Rule::requiredIf(fn (): bool => ! $this->user()), 'nullable', 'string', 'max:120'],
            'email' => ['required', 'email', 'max:255'],
            'subject' => [Rule::requiredIf($isSupportForm), 'string', 'max:200'],
            'category' => [Rule::requiredIf($isSupportForm), 'string', Rule::in(SupportTicketCategory::values())],
            'body' => ['required', 'string', 'max:5000'],
            'booking_reference' => ['nullable', 'string', 'max:64'],
            'website' => ['prohibited'],
        ], TurnstileVerifier::validationRules());
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return TurnstileVerifier::validationMessages();
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return [
            'body' => 'message',
            'booking_reference' => 'booking reference',
        ];
    }

    protected function failedValidation(Validator $validator): void
    {
        $bag = $this->input('form_type') === 'contact' ? 'contactMessage' : 'supportRequest';

        throw (new ValidationException($validator))->errorBag($bag);
    }
}
