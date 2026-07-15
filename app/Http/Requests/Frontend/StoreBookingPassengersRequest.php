<?php

namespace App\Http\Requests\Frontend;

use App\Models\User;
use App\Services\Booking\InternationalRouteDetector;
use App\Services\FlightSearch\FlightSearchResultStore;
use App\Support\FlightSearch\TravellerCountRules;
use App\Support\Geo\CountryList;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Password;
use Illuminate\Validation\Validator;

class StoreBookingPassengersRequest extends FormRequest
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
        if (! $this->isMethod('post')) {
            return [];
        }

        $loggedIn = Auth::check();

        $emailRules = ['required', 'email', 'max:255'];
        if (! $loggedIn && $this->boolean('create_account')) {
            $emailRules[] = Rule::unique(User::class, 'email');
        }

        $passwordRules = ['nullable', 'confirmed'];
        if (! $loggedIn && $this->boolean('create_account')) {
            $passwordRules = ['required', 'confirmed', Password::defaults()];
        }

        $rules = [
            'flight_id' => ['nullable', 'string', 'max:128'],
            'offer_id' => ['nullable', 'string', 'max:128'],
            'search_id' => ['nullable', 'string', 'max:128'],
            'fare_option_key' => ['nullable', 'string', 'max:120'],
            'return_fare_option_key' => ['nullable', 'string', 'max:120'],
            'outbound_fare_option_key' => ['nullable', 'string', 'max:120'],
            'outbound_key' => ['nullable', 'string', 'max:128'],
            'combo_id' => ['nullable', 'string', 'max:128'],
            'adults' => ['required', 'integer', 'min:1', 'max:9'],
            'children' => ['required', 'integer', 'min:0', 'max:8'],
            'infants' => ['required', 'integer', 'min:0', 'max:8'],
            'total_passengers' => ['nullable', 'integer', 'min:1', 'max:9'],
            'lead_passenger_index' => ['nullable', 'integer', 'min:0'],
            'passengers' => ['required', 'array', 'min:1', 'max:9'],
            'passengers.*.passenger_type' => ['required', 'string', 'in:adult,child,infant'],
            'passengers.*.title' => ['required', 'string', 'max:16'],
            'passengers.*.first_name' => ['required', 'string', 'max:120'],
            'passengers.*.last_name' => ['required', 'string', 'max:120'],
            'passengers.*.date_of_birth' => ['required', 'date', 'before:today'],
            'passengers.*.nationality' => ['nullable', 'string', 'size:2', Rule::in(CountryList::alpha2Codes())],
            'passengers.*.gender' => ['required', 'string', 'max:32'],
            'passengers.*.document_type' => $this->passengerDocumentTypeRules(),
            'passengers.*.passport_number' => ['nullable', 'string', 'max:64'],
            'passengers.*.passport_issuing_country' => ['nullable', 'string', 'size:2', Rule::in(CountryList::alpha2Codes())],
            'passengers.*.passport_expiry_date' => ['nullable', 'date_format:Y-m-d', 'after:today'],
            'passengers.*.passport_issue_date' => ['nullable', 'date_format:Y-m-d', 'before_or_equal:today'],
            'passengers.*.national_id_number' => ['nullable', 'string', 'max:64'],
            'passengers.*.country_of_residence' => ['nullable', 'string', 'max:120'],
            'passengers.*.place_of_birth' => ['nullable', 'string', 'max:120'],
            'contact_name' => ['nullable', 'string', 'max:160'],
            'email' => $emailRules,
            'phone_country_code' => ['nullable', 'string', 'max:8'],
            'phone_number' => ['nullable', 'string', 'regex:/^\d*$/', 'max:15'],
            'phone' => ['required', 'string', 'max:64'],
            'country' => ['nullable', 'string', 'max:120'],
            'create_account' => ['sometimes', 'boolean'],
            'password' => $passwordRules,
        ];

        return $rules;
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return [
            'flight_id' => __('Flight selection'),
            'offer_id' => __('Offer'),
            'search_id' => __('Search'),
            'adults' => __('Adults'),
            'children' => __('Children'),
            'infants' => __('Infants'),
            'total_passengers' => __('Total passengers'),
            'lead_passenger_index' => __('Lead passenger'),
            'passengers' => __('Passengers'),
            'passengers.*.passenger_type' => __('Passenger type'),
            'passengers.*.title' => __('Title'),
            'passengers.*.first_name' => __('First name'),
            'passengers.*.last_name' => __('Last name'),
            'passengers.*.date_of_birth' => __('Date of birth'),
            'passengers.*.nationality' => __('Nationality'),
            'passengers.*.gender' => __('Gender'),
            'passengers.*.document_type' => __('Document type'),
            'passengers.*.passport_number' => __('Passport number'),
            'passengers.*.passport_issuing_country' => __('Passport issuing country'),
            'passengers.*.passport_expiry_date' => __('Passport expiry date'),
            'passengers.*.passport_issue_date' => __('Passport issue date'),
            'passengers.*.national_id_number' => __('National ID number'),
            'passengers.*.country_of_residence' => __('Country of residence'),
            'passengers.*.place_of_birth' => __('Place of birth'),
            'contact_name' => __('Contact name'),
            'email' => __('Email'),
            'phone_country_code' => __('Country code'),
            'phone_number' => __('Mobile number'),
            'phone' => __('Mobile'),
            'country' => __('Country'),
            'create_account' => __('Create account'),
            'password' => __('Password'),
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'passengers.*.passport_expiry_date.date_format' => __('Enter the passport expiry using the date picker (year-month-day).'),
            'passengers.*.passport_expiry_date.after' => __('Passport expiry must be after today.'),
            'passengers.*.passport_issue_date.date_format' => __('Enter the passport issue date using the date picker (year-month-day).'),
            'passengers.*.passport_issue_date.before_or_equal' => __('Passport issue date must be today or in the past.'),
            'password.confirmed' => __('Passwords do not match.'),
            'phone_number.regex' => __('Mobile number must contain digits only.'),
        ];
    }

    protected function prepareForValidation(): void
    {
        if (! $this->isMethod('post')) {
            return;
        }

        $adults = max(1, (int) $this->input('adults', 1));
        $children = max(0, (int) $this->input('children', 0));
        $infants = max(0, (int) $this->input('infants', 0));
        $total = $adults + $children + $infants;
        $passengers = $this->input('passengers');

        // Backward compatibility: convert legacy single-passenger payload to passengers[0].
        if (! is_array($passengers) || $passengers === []) {
            $passengers = [[
                'passenger_type' => 'adult',
                'title' => $this->input('title'),
                'first_name' => $this->input('first_name'),
                'last_name' => $this->input('last_name'),
                'date_of_birth' => $this->input('dob'),
                'nationality' => $this->input('nationality'),
                'gender' => $this->input('gender'),
                'document_type' => $this->input('document_type', 'passport'),
                'passport_number' => $this->input('passport_number'),
                'passport_issuing_country' => $this->input('passport_issuing_country'),
                'passport_expiry_date' => $this->input('passport_expiry_date'),
                'passport_issue_date' => $this->input('passport_issue_date'),
                'national_id_number' => $this->input('national_id_number'),
                'country_of_residence' => $this->input('country_of_residence'),
                'place_of_birth' => $this->input('place_of_birth'),
            ]];
        }

        $detector = app(InternationalRouteDetector::class);
        $nationalIdTravelAllowed = $detector->nationalIdTravelDocumentsAllowedForOffer(
            $this->offerSnapshotForTravelDocuments(),
            (string) $this->input('from', ''),
            (string) $this->input('to', ''),
        );
        $passportOnlyRoute = ! $nationalIdTravelAllowed;

        foreach ($passengers as $idx => $passenger) {
            if (! is_array($passenger)) {
                continue;
            }
            $docType = $passportOnlyRoute
                ? 'passport'
                : (string) ($passenger['document_type'] ?? 'passport');
            if (! in_array($docType, ['passport', 'national_id'], true)) {
                $docType = 'passport';
            }
            $passengers[$idx]['document_type'] = $docType;
            $natRaw = $passenger['nationality'] ?? null;
            $passengers[$idx]['nationality'] = ($natRaw !== null && trim((string) $natRaw) !== '')
                ? strtoupper(trim((string) $natRaw))
                : null;
            $passengers[$idx]['passport_issuing_country'] = isset($passenger['passport_issuing_country'])
                ? strtoupper((string) $passenger['passport_issuing_country'])
                : null;
        }

        $phone = trim((string) $this->input('phone', ''));
        if ($phone === '') {
            $countryCode = trim((string) $this->input('phone_country_code', ''));
            $phoneNumber = preg_replace('/\D+/', '', (string) $this->input('phone_number', '')) ?? '';
            if ($countryCode !== '' && $phoneNumber !== '') {
                if (! str_starts_with($countryCode, '+')) {
                    $countryCode = '+'.$countryCode;
                }
                $phone = $countryCode.$phoneNumber;
            }
        }

        $this->merge([
            'create_account' => $this->boolean('create_account'),
            'adults' => $adults,
            'children' => $children,
            'infants' => $infants,
            'total_passengers' => $total,
            'passengers' => $passengers,
            'lead_passenger_index' => is_numeric($this->input('lead_passenger_index')) ? (int) $this->input('lead_passenger_index') : 0,
            'phone' => $phone,
        ]);
    }

    public function withValidator($validator): void
    {
        if (! $this->isMethod('post')) {
            return;
        }

        $validator->after(function (Validator $validator): void {
            $detector = app(InternationalRouteDetector::class);
            $nationalIdTravelAllowed = $detector->nationalIdTravelDocumentsAllowedForOffer(
                $this->offerSnapshotForTravelDocuments(),
                (string) $this->input('from', ''),
                (string) $this->input('to', ''),
            );
            $passengers = $this->input('passengers', []);
            if (! is_array($passengers)) {
                return;
            }

            $adults = (int) $this->input('adults', 1);
            $children = (int) $this->input('children', 0);
            $infants = (int) $this->input('infants', 0);
            $expectedTotal = $adults + $children + $infants;

            if ($expectedTotal > 9) {
                $validator->errors()->add('total_passengers', __('Total passengers cannot exceed 9.'));
            }

            if (count($passengers) !== $expectedTotal) {
                $validator->errors()->add('passengers', __('Passenger count must match selected search criteria.'));
            }

            $adultCount = 0;
            $childCount = 0;
            $infantCount = 0;
            $leadIndexes = [];
            $leadPassengerIndex = (int) $this->input('lead_passenger_index', 0);
            $departDate = (string) $this->input('depart', $this->input('search_depart', ''));
            $departure = $departDate !== '' ? Carbon::parse($departDate)->startOfDay() : null;
            $ageRules = (array) config('ota.passenger_age_rules', []);
            $adultMin = (int) ($ageRules['adult_min_years'] ?? 12);
            $childMin = (int) ($ageRules['child_min_years'] ?? 2);
            $childMax = (int) ($ageRules['child_max_years'] ?? 11);
            $infantMax = (int) ($ageRules['infant_max_years'] ?? 1);

            foreach ($passengers as $idx => $passenger) {
                $type = (string) ($passenger['passenger_type'] ?? '');
                if ($type === 'adult') {
                    $adultCount++;
                } elseif ($type === 'child') {
                    $childCount++;
                } elseif ($type === 'infant') {
                    $infantCount++;
                }

                if ($leadPassengerIndex === $idx) {
                    $leadIndexes[] = $idx;
                    if ($type !== 'adult') {
                        $validator->errors()->add("passengers.$idx.passenger_type", __('Lead passenger must be an adult.'));
                    }
                }

                $docType = (string) ($passenger['document_type'] ?? 'passport');
                $needsPassportFields = ! $nationalIdTravelAllowed || $docType === 'passport';

                if ($needsPassportFields) {
                    $passportMessage = ! $nationalIdTravelAllowed
                        ? __('This field is required for international itineraries.')
                        : __('This field is required when passport is selected as the identity document.');
                    foreach (['passport_number', 'passport_issuing_country', 'passport_expiry_date', 'passport_issue_date'] as $field) {
                        if (trim((string) ($passenger[$field] ?? '')) === '') {
                            $validator->errors()->add("passengers.$idx.$field", $passportMessage);
                        }
                    }
                    if (trim((string) ($passenger['nationality'] ?? '')) === '') {
                        $validator->errors()->add("passengers.$idx.nationality", ! $nationalIdTravelAllowed
                            ? __('Nationality is required for international itineraries.')
                            : __('Nationality is required when passport is selected as the identity document.'));
                    }
                } elseif ($nationalIdTravelAllowed && $docType === 'national_id') {
                    if (trim((string) ($passenger['national_id_number'] ?? '')) === '') {
                        $validator->errors()->add("passengers.$idx.national_id_number", __('National ID / CNIC number is required when national ID is selected.'));
                    }
                }

                if ($departure !== null && ! empty($passenger['date_of_birth'])) {
                    try {
                        $dob = Carbon::parse((string) $passenger['date_of_birth'])->startOfDay();
                        $age = $dob->diffInYears($departure);
                        if ($type === 'adult' && $age < $adultMin) {
                            $validator->errors()->add("passengers.$idx.date_of_birth", __('Adult passenger must be at least 12 years old on the departure date.'));
                        }
                        if ($type === 'child' && ($age < $childMin || $age > $childMax)) {
                            $validator->errors()->add("passengers.$idx.date_of_birth", __('Child passenger must be between 2 and 11 years old on the departure date.'));
                        }
                        if ($type === 'infant' && $age > $infantMax) {
                            $validator->errors()->add("passengers.$idx.date_of_birth", __('Infant passenger must be under 2 years old on the departure date.'));
                        }
                    } catch (\Throwable) {
                        // Base date validation already handles invalid formats.
                    }
                }
            }

            if ($adultCount !== $adults) {
                $validator->errors()->add('passengers', __('Adult passenger count must match selected adults.'));
            }
            if ($childCount !== $children) {
                $validator->errors()->add('passengers', __('Child passenger count must match selected children.'));
            }
            if ($infantCount !== $infants) {
                $validator->errors()->add('passengers', __('Infant passenger count must match selected infants.'));
            }
            if (TravellerCountRules::infantsExceedAdults($adults, $infants)) {
                $validator->errors()->add('infants', __(TravellerCountRules::INFANTS_EXCEED_ADULTS_MESSAGE));
            }
            if (count($leadIndexes) !== 1) {
                $validator->errors()->add('lead_passenger_index', __('Exactly one lead passenger is required.'));
            }

            // Backward-compatible error keys for legacy single-passenger tests/forms.
            $aliasMap = [
                'passengers.0.passport_number' => 'passport_number',
                'passengers.0.passport_issuing_country' => 'passport_issuing_country',
                'passengers.0.passport_expiry_date' => 'passport_expiry_date',
                'passengers.0.passport_issue_date' => 'passport_issue_date',
                'passengers.0.nationality' => 'nationality',
                'passengers.0.date_of_birth' => 'dob',
            ];
            foreach ($aliasMap as $from => $to) {
                if ($validator->errors()->has($from)) {
                    foreach ($validator->errors()->get($from) as $message) {
                        $validator->errors()->add($to, $message);
                    }
                }
            }
        });
    }

    /**
     * @return array<int, ValidationRule|string>
     */
    protected function passengerDocumentTypeRules(): array
    {
        $detector = app(InternationalRouteDetector::class);
        $nationalIdTravelAllowed = $detector->nationalIdTravelDocumentsAllowedForOffer(
            $this->offerSnapshotForTravelDocuments(),
            (string) $this->input('from', ''),
            (string) $this->input('to', ''),
        );

        return $nationalIdTravelAllowed
            ? ['required', 'string', Rule::in(['passport', 'national_id'])]
            : ['required', 'string', Rule::in(['passport'])];
    }

    /**
     * @return array<string, mixed>|null
     */
    protected function offerSnapshotForTravelDocuments(): ?array
    {
        $sid = trim((string) $this->input('search_id', ''));
        $oid = trim((string) ($this->input('offer_id', $this->input('flight_id', ''))));
        if ($sid === '' || $oid === '') {
            return null;
        }

        return app(FlightSearchResultStore::class)->findOffer($sid, $oid);
    }
}
