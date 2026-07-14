<?php

namespace App\Services\Suppliers\Iati;

use App\Models\Booking;
use App\Models\BookingPassenger;
use App\Services\Suppliers\Iati\Exceptions\IatiValidationException;
use Carbon\Carbon;

/**
 * Maps persisted booking_passengers columns to IATI supplier passenger fields.
 *
 * Primary DB columns: passenger_type, first_name, last_name, date_of_birth, title,
 * gender, nationality, passport_number, passport_issue_date, passport_expiry_date.
 * given_name / surname / type on the model are fallback-only (not real DB columns).
 */
class IatiPassengerNormalizer
{
    /**
     * @return array{
     *     type: string|null,
     *     title: string|null,
     *     given_name: string|null,
     *     surname: string|null,
     *     gender: string|null,
     *     date_of_birth: string|null,
     *     nationality: string|null,
     *     passport_number_present: bool,
     *     passport_issue_date: string|null,
     *     passport_expiry_date: string|null,
     *     passenger_id: int|null,
     *     passenger_index: int|null
     * }
     */
    public function normalize(BookingPassenger $passenger): array
    {
        $givenName = $this->resolveGivenName($passenger);
        $surname = $this->resolveSurname($passenger);

        return [
            'type' => $this->resolveSupplierType($passenger),
            'title' => $this->nullableString($passenger->title),
            'given_name' => $givenName !== '' ? $givenName : null,
            'surname' => $surname !== '' ? $surname : null,
            'gender' => $this->nullableString($passenger->gender),
            'date_of_birth' => $this->formatDateOrNull($passenger->date_of_birth),
            'nationality' => $this->nullableString($passenger->nationality),
            'passport_number_present' => trim((string) ($passenger->passport_number ?? '')) !== '',
            'passport_issue_date' => $this->formatDateOrNull($passenger->passport_issue_date),
            'passport_expiry_date' => $this->formatDateOrNull($passenger->passport_expiry_date),
            'passenger_id' => $passenger->id,
            'passenger_index' => $passenger->passenger_index,
        ];
    }

    /**
     * @return list<array{
     *     type: string|null,
     *     title: string|null,
     *     given_name: string|null,
     *     surname: string|null,
     *     gender: string|null,
     *     date_of_birth: string|null,
     *     nationality: string|null,
     *     passport_number_present: bool,
     *     passport_issue_date: string|null,
     *     passport_expiry_date: string|null,
     *     passenger_id: int|null,
     *     passenger_index: int|null
     * }>
     */
    public function normalizeCollectionForBooking(Booking $booking): array
    {
        $booking->loadMissing('passengers');

        return $booking->passengers
            ->values()
            ->map(fn (BookingPassenger $passenger): array => $this->normalize($passenger))
            ->all();
    }

    /**
     * @return list<string>
     */
    public function missingSupplierFields(BookingPassenger $passenger, int $index): array
    {
        $normalized = $this->normalize($passenger);
        $prefix = 'passengers.'.$index.'.';
        $missing = [];

        if (($normalized['given_name'] ?? '') === '') {
            $missing[] = $prefix.'given_name';
        }
        if (($normalized['surname'] ?? '') === '') {
            $missing[] = $prefix.'surname';
        }
        if (($normalized['type'] ?? '') === '') {
            $missing[] = $prefix.'type';
        }
        if (($normalized['date_of_birth'] ?? '') === '') {
            $missing[] = $prefix.'date_of_birth';
        }
        if (! ($normalized['passport_number_present'] ?? false)) {
            $missing[] = $prefix.'passport_number';
        }
        if (($normalized['passport_expiry_date'] ?? '') === '') {
            $missing[] = $prefix.'passport_expiry_date';
        }

        return $missing;
    }

    /**
     * @return list<string>
     */
    public function missingSupplierFieldsForBooking(Booking $booking): array
    {
        $booking->loadMissing('passengers');

        if ($booking->passengers->isEmpty()) {
            return ['passengers'];
        }

        $missing = [];
        foreach ($booking->passengers->values() as $index => $passenger) {
            $missing = array_merge($missing, $this->missingSupplierFields($passenger, $index));
        }

        return array_values(array_unique($missing));
    }

    public function isPassengerComplete(BookingPassenger $passenger, int $index): bool
    {
        return $this->missingSupplierFields($passenger, $index) === [];
    }

    public function isBookingComplete(Booking $booking): bool
    {
        return $this->missingSupplierFieldsForBooking($booking) === [];
    }

    /**
     * @throws IatiValidationException
     */
    public function assertBookingPassengersReady(Booking $booking): void
    {
        $booking->loadMissing('passengers');

        if ($booking->passengers->isEmpty()) {
            throw IatiValidationException::passengerPayloadIncomplete(['passengers']);
        }

        foreach ($booking->passengers->values() as $index => $passenger) {
            $missing = $this->missingSupplierFields($passenger, $index);
            if ($missing === []) {
                continue;
            }

            throw IatiValidationException::passengerPayloadIncomplete(
                $missing,
                $passenger->id,
                $passenger->passenger_index ?? $index,
            );
        }
    }

    public function mapGenderForApi(array $normalized): string
    {
        $gender = strtoupper(trim((string) ($normalized['gender'] ?? '')));
        if (in_array($gender, ['F', 'FEMALE'], true)) {
            return 'FEMALE';
        }
        if (in_array($gender, ['M', 'MALE'], true)) {
            return 'MALE';
        }

        $title = strtolower(trim((string) ($normalized['title'] ?? '')));
        if (in_array($title, ['mrs', 'ms', 'miss', 'female'], true)) {
            return 'FEMALE';
        }

        return 'MALE';
    }

    protected function resolveGivenName(BookingPassenger $passenger): string
    {
        $firstName = trim((string) ($passenger->first_name ?? ''));
        if ($firstName !== '') {
            return $firstName;
        }

        return trim((string) ($passenger->given_name ?? ''));
    }

    protected function resolveSurname(BookingPassenger $passenger): string
    {
        $lastName = trim((string) ($passenger->last_name ?? ''));
        if ($lastName !== '') {
            return $lastName;
        }

        return trim((string) ($passenger->surname ?? ''));
    }

    protected function resolveSupplierType(BookingPassenger $passenger): ?string
    {
        $raw = strtolower(trim((string) ($passenger->passenger_type ?? $passenger->type ?? '')));
        if ($raw === '') {
            return null;
        }

        return match ($raw) {
            'child', 'chd' => 'CHILD',
            'infant', 'inf' => 'INFANT',
            default => 'ADULT',
        };
    }

    protected function nullableString(mixed $value): ?string
    {
        $string = trim((string) ($value ?? ''));

        return $string !== '' ? $string : null;
    }

    protected function formatDateOrNull(mixed $date): ?string
    {
        if ($date === null || trim((string) $date) === '') {
            return null;
        }

        try {
            return Carbon::parse((string) $date)->format('Y-m-d');
        } catch (\Throwable) {
            return null;
        }
    }
}
