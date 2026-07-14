<?php

namespace App\Services\Suppliers\Duffel;

use App\Models\Booking;
use InvalidArgumentException;

class DuffelOrderRequestBuilder
{
    /**
     * @return array<string, mixed>
     */
    public function build(Booking $booking): array
    {
        $booking->loadMissing(['passengers', 'contact', 'customer']);
        $meta = is_array($booking->meta) ? $booking->meta : [];
        $selectedOffer = $this->extractOfferReference($meta);
        if ($selectedOffer === null) {
            throw new InvalidArgumentException('Validated Duffel offer reference is missing.');
        }

        $contactEmail = (string) ($booking->contact?->email ?? $booking->customer?->email ?? '');
        $contactPhone = (string) ($booking->contact?->phone ?? '');

        $passengers = [];
        foreach ($booking->passengers->values() as $index => $passenger) {
            $metaRow = is_array($passenger->meta) ? $passenger->meta : [];
            $row = array_filter([
                'id' => 'pas_'.($index + 1),
                'type' => $this->mapPassengerType($metaRow['traveler_type'] ?? null),
                'given_name' => $passenger->first_name,
                'family_name' => $passenger->last_name,
                'born_on' => $passenger->date_of_birth?->format('Y-m-d'),
                'email' => $contactEmail !== '' ? $contactEmail : null,
                'phone_number' => $contactPhone !== '' ? $contactPhone : null,
                'title' => is_string($passenger->title) && trim($passenger->title) !== '' ? trim($passenger->title) : null,
                'gender' => $this->safeGender($metaRow['gender'] ?? null),
            ], static fn (mixed $value): bool => ! ($value === null || $value === ''));

            $passengers[] = $row;
        }

        return [
            'data' => [
                'type' => 'order',
                'selected_offers' => [$selectedOffer],
                'passengers' => $passengers,
            ],
        ];
    }

    /**
     * @param  array<string, mixed>  $meta
     */
    private function extractOfferReference(array $meta): ?string
    {
        $candidates = [
            data_get($meta, 'validated_offer_snapshot.raw_reference'),
            data_get($meta, 'normalized_offer_snapshot.raw_reference'),
            data_get($meta, 'validated_offer_snapshot.offer_id'),
            data_get($meta, 'normalized_offer_snapshot.offer_id'),
        ];

        foreach ($candidates as $candidate) {
            $value = trim((string) $candidate);
            if ($value !== '') {
                return $value;
            }
        }

        return null;
    }

    private function mapPassengerType(mixed $type): string
    {
        return match (strtolower(trim((string) $type))) {
            'child', 'chd' => 'child',
            'infant', 'inf' => 'infant_without_seat',
            default => 'adult',
        };
    }

    private function safeGender(mixed $gender): ?string
    {
        $normalized = strtolower(trim((string) $gender));

        return in_array($normalized, ['m', 'male', 'f', 'female'], true) ? $normalized : null;
    }
}
