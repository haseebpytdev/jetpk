<?php

namespace App\Http\Requests\Frontend;

use App\Http\Requests\Auth\StoreCustomerRegistrationRequest;
use Illuminate\Foundation\Http\FormRequest;

class StoreAgentApplicationRequest extends FormRequest
{
    public const MOBILE_DIGITS_MESSAGE = 'Only numbers are allowed. Do not use spaces, dashes, brackets, or special characters.';

    public const NAME_LETTERS_MESSAGE = 'Only letters and spaces are allowed.';

    public const CITY_FORMAT_MESSAGE = 'City may only contain letters, spaces, and hyphens.';

    public const COMPANY_NAME_FORMAT_MESSAGE = 'Enter a valid agency name.';

    public const DUPLICATE_EMAIL_MESSAGE = 'This email is already registered. Please log in or use another email.';

    public function authorize(): bool
    {
        return true;
    }

    protected function prepareForValidation(): void
    {
        $this->merge(self::normalizeAjaxPayload($this->all()));
    }

    /**
     * @return list<string>
     */
    public static function ajaxValidationFields(): array
    {
        return [
            'company_name',
            'city',
            'business_type',
            'first_name',
            'email',
            'mobile_country_code',
            'mobile',
            'notes',
            'terms',
        ];
    }

    /**
     * @return list<string>
     */
    public static function fieldsToValidateFor(string $field): array
    {
        if (in_array($field, ['mobile_country_code', 'mobile'], true)) {
            return ['mobile_country_code', 'mobile'];
        }

        return [$field];
    }

    /**
     * @param  list<string>  $fields
     * @return array<string, mixed>
     */
    public static function rulesForFields(array $fields): array
    {
        return array_intersect_key(self::sharedRules(), array_flip($fields));
    }

    /**
     * @param  array<string, mixed>  $input
     * @return array<string, mixed>
     */
    public static function normalizeAjaxPayload(array $input): array
    {
        $countryCode = StoreCustomerRegistrationRequest::normalizeCountryCode((string) ($input['mobile_country_code'] ?? ''));
        $rawMobile = preg_replace('/\D+/', '', trim((string) ($input['mobile'] ?? '')));

        if ($countryCode === '') {
            $countryCode = '+92';
        }

        if ($countryCode === '+92' && preg_match('/^\d+$/', $rawMobile) && str_starts_with($rawMobile, '92') && strlen($rawMobile) > 10) {
            $rawMobile = substr($rawMobile, 2);
        }

        $terms = $input['terms'] ?? null;
        $termsAccepted = in_array($terms, [1, '1', true, 'true', 'on', 'yes'], true);

        return [
            'first_name' => trim((string) ($input['first_name'] ?? '')),
            'last_name' => trim((string) ($input['last_name'] ?? 'Applicant')) ?: 'Applicant',
            'email' => strtolower(trim((string) ($input['email'] ?? ''))),
            'mobile_country_code' => $countryCode,
            'mobile' => $rawMobile,
            'company_name' => trim((string) ($input['company_name'] ?? '')),
            'business_type' => trim((string) ($input['business_type'] ?? '')),
            'city' => trim((string) ($input['city'] ?? '')),
            'country' => trim((string) ($input['country'] ?? 'Pakistan')) ?: 'Pakistan',
            'office_address' => trim((string) ($input['office_address'] ?? 'To be shared during onboarding')) ?: 'To be shared during onboarding',
            'notes' => trim((string) ($input['notes'] ?? '')),
            'terms' => $termsAccepted ? '1' : null,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public static function sharedRules(): array
    {
        return [
            'company_name' => ['required', 'string', 'max:255', 'regex:/^[\pL\pN\s\-\.&\',()\/+#]+$/u'],
            'city' => ['required', 'string', 'max:120', 'regex:/^[A-Za-z\s\-]+$/'],
            'business_type' => ['required', 'string', 'max:100'],
            'first_name' => ['required', 'string', 'max:100', 'regex:/^[A-Za-z ]+$/'],
            'last_name' => ['nullable', 'string', 'max:100'],
            'email' => [
                'required',
                'string',
                'max:255',
                'email:rfc',
                function (string $attribute, mixed $value, \Closure $fail): void {
                    $email = strtolower(trim((string) $value));
                    if (! preg_match('/^[a-z0-9._%+\-]+@[a-z0-9\-]+(\.[a-z0-9\-]+)+$/i', $email)) {
                        $fail('Please provide a valid email address.');

                        return;
                    }

                    $domain = substr((string) strstr($email, '@'), 1);
                    if ($domain === '' || str_contains($domain, '*') || str_contains($domain, '..') || ! str_contains($domain, '.')) {
                        $fail('Please provide a valid email address.');
                    }
                },
            ],
            'mobile_country_code' => ['required', 'string', 'regex:/^\+[0-9]{1,4}$/'],
            'mobile' => ['required', 'string', 'min:7', 'max:15', 'regex:/^[0-9]+$/'],
            'country' => ['nullable', 'string', 'max:120'],
            'office_address' => ['nullable', 'string', 'max:1000'],
            'notes' => ['nullable', 'string', 'max:2000'],
            'terms' => ['accepted'],
            'website' => ['nullable', 'url', 'max:255'],
            'cnic' => ['nullable', 'string', 'max:50'],
            'ntn' => ['nullable', 'string', 'max:50'],
            'iata_number' => ['nullable', 'string', 'max:50'],
            'years_in_business' => ['nullable', 'integer', 'min:0', 'max:100'],
            'expected_booking_volume' => ['nullable', 'string', 'max:255'],
            'services_interested' => ['nullable', 'array'],
            'services_interested.*' => ['string', 'max:120'],
        ];
    }

    public function rules(): array
    {
        return self::sharedRules();
    }

    public function messages(): array
    {
        return [
            'company_name.required' => self::COMPANY_NAME_FORMAT_MESSAGE,
            'company_name.regex' => self::COMPANY_NAME_FORMAT_MESSAGE,
            'business_type.required' => 'Select a business type.',
            'city.regex' => self::CITY_FORMAT_MESSAGE,
            'first_name.regex' => self::NAME_LETTERS_MESSAGE,
            'email.email' => 'Please provide a valid email address.',
            'mobile_country_code.required' => 'Please select a country code.',
            'mobile_country_code.regex' => 'Please select a country code.',
            'mobile.regex' => self::MOBILE_DIGITS_MESSAGE,
            'mobile.min' => 'Mobile number must be at least 7 digits.',
            'mobile.max' => 'Mobile number must not exceed 15 digits.',
            'terms.accepted' => 'Please confirm the submitted information is accurate.',
        ];
    }

    /**
     * Validated attributes ready for AgentApplication persistence (combined mobile, no terms/code).
     *
     * @return array<string, mixed>
     */
    public function applicationAttributes(): array
    {
        $validated = $this->validated();
        unset($validated['terms'], $validated['mobile_country_code']);

        $validated['mobile'] = StoreCustomerRegistrationRequest::formatStoredPhone(
            (string) $this->validated('mobile_country_code'),
            (string) $this->validated('mobile'),
        );

        return $validated;
    }
}
