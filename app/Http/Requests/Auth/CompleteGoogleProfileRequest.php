<?php

namespace App\Http\Requests\Auth;

use Illuminate\Foundation\Http\FormRequest;

class CompleteGoogleProfileRequest extends FormRequest
{
    public function authorize(): bool
    {
        $user = $this->user();

        return $user !== null && $user->isCustomer();
    }

    protected function prepareForValidation(): void
    {
        $countryCode = StoreCustomerRegistrationRequest::normalizeCountryCode(
            (string) $this->input('mobile_country_code', '')
        );
        $rawMobile = trim((string) $this->input('mobile', ''));

        if ($countryCode === '') {
            $countryCode = '+92';
        }

        if ($countryCode === '+92' && preg_match('/^\d+$/', $rawMobile) && str_starts_with($rawMobile, '92') && strlen($rawMobile) > 10) {
            $rawMobile = substr($rawMobile, 2);
        }

        $this->merge([
            'first_name' => trim((string) $this->input('first_name', '')),
            'last_name' => trim((string) $this->input('last_name', '')),
            'mobile_country_code' => $countryCode,
            'mobile' => $rawMobile,
        ]);
    }

    public function rules(): array
    {
        return [
            'first_name' => ['required', 'string', 'max:100', 'regex:/^[A-Za-z ]+$/'],
            'last_name' => ['required', 'string', 'max:100', 'regex:/^[A-Za-z ]+$/'],
            'mobile_country_code' => ['required', 'string', 'regex:/^\+[0-9]{1,4}$/'],
            'mobile' => ['required', 'string', 'min:7', 'max:15', 'regex:/^[0-9]+$/'],
        ];
    }

    public function messages(): array
    {
        return [
            'first_name.regex' => StoreCustomerRegistrationRequest::NAME_LETTERS_MESSAGE,
            'last_name.regex' => StoreCustomerRegistrationRequest::NAME_LETTERS_MESSAGE,
            'mobile.regex' => StoreCustomerRegistrationRequest::MOBILE_DIGITS_MESSAGE,
        ];
    }

    /**
     * @return array{first_name: string, last_name: string, phone: string}
     */
    public function profilePayload(): array
    {
        $validated = $this->validated();

        return [
            'first_name' => $validated['first_name'],
            'last_name' => $validated['last_name'],
            'phone' => StoreCustomerRegistrationRequest::formatStoredPhone(
                $validated['mobile_country_code'],
                $validated['mobile'],
            ),
        ];
    }
}
