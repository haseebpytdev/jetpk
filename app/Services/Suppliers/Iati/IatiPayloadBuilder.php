<?php

namespace App\Services\Suppliers\Iati;

use App\Data\FlightSearchRequestData;
use App\Models\Booking;
use App\Models\BookingPassenger;
use App\Services\Suppliers\Iati\Exceptions\IatiValidationException;
use Carbon\Carbon;

/**
 * Builds IATI Flight API v2 request payloads from OTA search/booking data.
 */
class IatiPayloadBuilder
{
    public function __construct(
        private readonly IatiPassengerNormalizer $passengerNormalizer,
    ) {}

    /**
     * @return array<string, mixed>
     */
    public function buildSearchPayload(FlightSearchRequestData $request): array
    {
        if ($request->origin === '' || $request->destination === '' || $request->departure_date === '') {
            throw new IatiValidationException(
                'supplier_request_invalid',
                422,
                'Origin, destination, and departure date are required.',
            );
        }

        $payload = [
            'from_destination' => ['code' => strtoupper($request->origin), 'city' => false],
            'to_destination' => ['code' => strtoupper($request->destination), 'city' => false],
            'departure_date' => $this->formatDate($request->departure_date),
            'pax_list' => $this->paxList($request->adults, $request->children, $request->infants),
            'accept_pending' => true,
            'cabin_type' => $this->cabinType($request->cabin),
        ];

        $returnDate = $request->return_date;
        if ($returnDate !== null && trim($returnDate) !== '' && in_array($request->trip_type, ['return', 'round_trip', 'roundtrip'], true)) {
            $payload['return_date'] = $this->formatDate($returnDate);
        }

        return $payload;
    }

    /**
     * @param  array<string, mixed>  $providerContext
     * @return array<string, mixed>
     */
    public function buildFarePayload(array $providerContext): array
    {
        $departureFareKey = trim((string) ($providerContext['departure_fare_key'] ?? ''));
        if ($departureFareKey === '') {
            throw new IatiValidationException('supplier_request_invalid', 422, 'IATI departure fare key is missing.');
        }

        $payload = [
            'departure_fare_key' => $departureFareKey,
            'pax_list' => $this->paxListFromContext($providerContext),
        ];

        $returnFareKey = trim((string) ($providerContext['return_fare_key'] ?? ''));
        if ($returnFareKey !== '') {
            $payload['return_fare_key'] = $returnFareKey;
        }

        return $payload;
    }

    /**
     * @param  array<string, mixed>  $fareData
     * @param  array<string, mixed>  $providerContext
     * @param  list<array<string, mixed>>  $passengers
     * @param  array<string, mixed>  $contact
     * @param  list<string>  $offerKeys
     * @return array<string, mixed>
     */
    public function buildBookPayload(
        array $fareData,
        array $providerContext,
        array $passengers,
        array $contact,
        array $offerKeys,
        bool $acceptPending = true,
        ?string $notes = null,
    ): array {
        $fareDetailKey = trim((string) ($fareData['fare_detail_key'] ?? $providerContext['fare_detail_key'] ?? ''));
        if ($fareDetailKey === '') {
            throw new IatiValidationException('supplier_request_invalid', 422, 'IATI fare detail key is missing.');
        }

        if ($offerKeys === []) {
            throw new IatiValidationException('supplier_request_invalid', 422, 'IATI offer keys are missing.');
        }

        $payload = [
            'fare_detail_key' => $fareDetailKey,
            'contact' => $contact,
            'pax_list' => $passengers,
            'offers' => array_values($offerKeys),
        ];

        if ($acceptPending) {
            $payload['accept_pending'] = true;
        }

        if ($notes !== null && trim($notes) !== '') {
            $payload['notes'] = trim($notes);
        }

        return $payload;
    }

    /**
     * @return array<string, mixed>
     */
    public function buildOptionPayload(
        array $fareData,
        array $providerContext,
        array $passengers,
        array $contact,
        array $offerKeys,
    ): array {
        return $this->buildBookPayload($fareData, $providerContext, $passengers, $contact, $offerKeys, false);
    }

    /**
     * @return array<string, mixed>
     */
    public function buildOrderListPayload(string $startDate, string $endDate): array
    {
        return [
            'start_date' => $this->formatDate($startDate),
            'end_date' => $this->formatDate($endDate),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function buildAirportPayload(string $languageCode = 'en'): array
    {
        return ['language_code' => $languageCode !== '' ? $languageCode : 'en'];
    }

    /**
     * @return list<array{type: string, count: int}>
     */
    public function paxList(int $adults, int $children, int $infants): array
    {
        $list = [
            ['type' => 'ADULT', 'count' => max(1, $adults)],
        ];

        if ($children > 0) {
            $list[] = ['type' => 'CHILD', 'count' => $children];
        }

        if ($infants > 0) {
            $list[] = ['type' => 'INFANT', 'count' => $infants];
        }

        return $list;
    }

    /**
     * @param  array<string, mixed>  $providerContext
     * @return list<array{type: string, count: int}>
     */
    public function paxListFromContext(array $providerContext): array
    {
        $counts = is_array($providerContext['pax_counts'] ?? null) ? $providerContext['pax_counts'] : [];

        return $this->paxList(
            (int) ($counts['adults'] ?? $counts['ADULT'] ?? 1),
            (int) ($counts['children'] ?? $counts['CHILD'] ?? 0),
            (int) ($counts['infants'] ?? $counts['INFANT'] ?? 0),
        );
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function buildPassengersFromBooking(Booking $booking): array
    {
        $this->passengerNormalizer->assertBookingPassengersReady($booking);
        $booking->loadMissing('passengers');

        $passengers = [];
        foreach ($booking->passengers as $passenger) {
            $normalized = $this->passengerNormalizer->normalize($passenger);
            $passengers[] = [
                'name' => (string) $normalized['given_name'],
                'lastname' => (string) $normalized['surname'],
                'birthdate' => $this->formatDate((string) $normalized['date_of_birth']),
                'type' => (string) $normalized['type'],
                'gender' => $this->passengerNormalizer->mapGenderForApi($normalized),
                'identity_info' => $this->identityInfo($passenger, $normalized),
            ];
        }

        return $passengers;
    }

    /**
     * @return array<string, mixed>
     */
    public function buildContactFromBooking(Booking $booking, ?string $organizationId = null): array
    {
        $booking->loadMissing('contact');
        $contact = $booking->contact;

        $email = trim((string) ($contact?->email ?? $booking->customer_email ?? ''));
        if ($email === '') {
            throw new IatiValidationException('contact_data_incomplete', 422, 'Contact email is required for IATI booking.');
        }

        $phone = preg_replace('/\D+/', '', (string) ($contact?->phone ?? ''));
        $countryCode = preg_replace('/\D+/', '', trim((string) ($contact?->phone_country_code ?? '')));

        if ($phone === '') {
            throw new IatiValidationException('contact_data_incomplete', 422, 'Contact phone is required for IATI booking.');
        }

        if ($countryCode === '') {
            throw new IatiValidationException('contact_data_incomplete', 422, 'Contact phone country code is required for IATI booking.');
        }

        if (strlen($phone) <= 7) {
            throw new IatiValidationException('contact_data_incomplete', 422, 'Contact phone must include area and subscriber digits.');
        }

        $areaCode = substr($phone, 0, 3);
        $phoneNumber = substr($phone, 3);

        $payload = [
            'email' => $email,
            'phone' => [
                'country_code' => $countryCode,
                'area_code' => $areaCode,
                'phone_number' => $phoneNumber,
            ],
        ];

        if ($organizationId !== null && trim($organizationId) !== '') {
            $payload['organization_id'] = trim($organizationId);
        }

        return $payload;
    }

    /**
     * @param  array<string, mixed>  $normalized
     * @return array<string, mixed>
     */
    protected function identityInfo(BookingPassenger $passenger, array $normalized): array
    {
        $passport = trim((string) ($passenger->passport_number ?? ''));
        $nationality = strtoupper(trim((string) ($normalized['nationality'] ?? '')));
        $expiry = trim((string) ($normalized['passport_expiry_date'] ?? ''));

        if ($nationality === '') {
            throw new IatiValidationException(
                'passenger_data_incomplete',
                422,
                'Passenger nationality is required before booking with the supplier.',
            );
        }

        if ($passport === '') {
            throw new IatiValidationException(
                'passenger_data_incomplete',
                422,
                'Passenger passport number is required before booking with the supplier.',
            );
        }

        return [
            'not_turkish_citizen' => $nationality !== 'TR',
            'not_pakistan_citizen' => $nationality !== 'PK',
            'passport' => [
                'no' => $passport,
                'citizenship_country' => $nationality,
                'end_date' => $this->formatDate($expiry),
            ],
        ];
    }

    protected function cabinType(string $cabin): string
    {
        return match (strtolower(trim($cabin))) {
            'business', 'premium_business' => 'BUSINESS',
            'first' => 'FIRST',
            default => 'ECONOMY',
        };
    }

    protected function formatDate(string $date): string
    {
        try {
            return Carbon::parse($date)->format('Y-m-d');
        } catch (\Throwable) {
            throw new IatiValidationException('supplier_request_invalid', 422, 'Invalid date format.');
        }
    }
}
