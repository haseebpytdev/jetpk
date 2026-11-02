<?php

namespace App\Support\Emails;

/**
 * Maps BookingEmailPayloadFactory universal_email arrays into JetpkEmailEventRenderer payload keys.
 *
 * Presentation-only: does not alter factory field assembly or supplier data.
 */
final class JetpkBookingUniversalPayloadAdapter
{
    /**
     * @param  array<string, mixed>  $universal
     * @return array<string, mixed>
     */
    public static function toJetpkPayload(array $universal, ?string $recipientRole = null): array
    {
        $bookingIn = is_array($universal['booking'] ?? null) ? $universal['booking'] : [];
        $paymentIn = is_array($universal['payment'] ?? null) ? $universal['payment'] : [];
        $passengersIn = is_array($universal['passengers'] ?? null) ? $universal['passengers'] : [];
        $segmentsIn = is_array($universal['segments'] ?? null) ? $universal['segments'] : [];
        $adminIn = is_array($universal['admin'] ?? null) ? $universal['admin'] : [];
        $ctaIn = is_array($universal['cta'] ?? null) ? $universal['cta'] : [];
        $selectedFare = is_array($universal['selected_fare_family'] ?? null) ? $universal['selected_fare_family'] : null;

        $role = strtolower(trim((string) ($recipientRole ?? $adminIn['recipient_type'] ?? 'customer')));
        $isCustomer = $role === '' || $role === 'customer' || str_contains($role, 'customer');

        $amount = $paymentIn['estimated_selected_fare']
            ?? $paymentIn['estimated_amount_due']
            ?? $paymentIn['total']
            ?? $paymentIn['base_fare_total']
            ?? null;

        $booking = [
            'reference' => $bookingIn['reference'] ?? null,
            'pnr' => $bookingIn['pnr'] ?? null,
            'status' => $bookingIn['current_status'] ?? $bookingIn['status'] ?? null,
            'payment_status' => $paymentIn['status'] ?? null,
            'route' => $bookingIn['route'] ?? null,
            'trip_type' => $bookingIn['trip_type'] ?? null,
            'passenger_count' => $universal['passenger_summary'] ?? count($passengersIn) ?: null,
            'amount' => is_string($amount) ? $amount : (is_numeric($amount) ? (string) $amount : null),
            'currency' => $paymentIn['currency'] ?? null,
            'airline' => $bookingIn['airline'] ?? null,
            'travel_date' => $bookingIn['travel_date'] ?? null,
        ];

        if ($selectedFare !== null) {
            $fareLabel = $selectedFare['fare_family_label']
                ?? (isset($selectedFare['name'], $selectedFare['brand_code'])
                    ? trim((string) $selectedFare['name'].' ('.$selectedFare['brand_code'].')')
                    : ($selectedFare['name'] ?? null));
            $booking['selected_fare_family'] = $fareLabel;
            $booking['selected_fare_baggage'] = $selectedFare['baggage'] ?? $selectedFare['baggage_summary'] ?? null;
            if (! empty($selectedFare['estimated_fare_display']) || ! empty($selectedFare['price_display'])) {
                $booking['amount'] = (string) ($selectedFare['estimated_fare_display'] ?? $selectedFare['price_display']);
            }
        }

        $payment = [
            'amount' => $paymentIn['estimated_selected_fare']
                ?? $paymentIn['estimated_amount_due']
                ?? $paymentIn['total']
                ?? $paymentIn['amount']
                ?? null,
            'currency' => $paymentIn['currency'] ?? null,
            'status' => $paymentIn['status'] ?? null,
            'method' => $paymentIn['method'] ?? null,
            'base_fare_total' => $paymentIn['base_fare_total'] ?? null,
            'estimated_selected_fare' => $paymentIn['estimated_selected_fare'] ?? null,
            'estimated_amount_due' => $paymentIn['estimated_amount_due'] ?? null,
        ];

        $passengers = [];
        foreach ($passengersIn as $passenger) {
            if (! is_array($passenger)) {
                continue;
            }
            $name = trim((string) ($passenger['name'] ?? trim(($passenger['first_name'] ?? '').' '.($passenger['last_name'] ?? ''))));
            if ($name === '') {
                continue;
            }
            $passengers[] = [
                'name' => $name,
                'type' => $passenger['type'] ?? $passenger['passenger_type'] ?? null,
            ];
        }

        $itinerary = [];
        foreach ($segmentsIn as $segment) {
            if (! is_array($segment)) {
                continue;
            }
            $summary = trim((string) ($segment['summary'] ?? ''));
            $label = trim((string) ($segment['label'] ?? ''));
            if ($summary === '' && $label === '') {
                continue;
            }
            // JetPK itinerary partial prefers from/to; map summary into a single readable row.
            $itinerary[] = [
                'label' => $label !== '' ? $label : 'Segment',
                'from' => $summary !== '' ? $summary : ($label !== '' ? $label : null),
                'to' => null,
            ];
        }

        $meta = [
            'notes' => is_array($universal['notes'] ?? null) ? $universal['notes'] : [],
            'status_label' => $universal['status_label'] ?? null,
            'title' => $universal['title'] ?? null,
        ];

        if ($selectedFare !== null) {
            $meta['selected_fare_family'] = $selectedFare;
            $fareBits = array_values(array_filter([
                $booking['selected_fare_family'] ?? null,
                $booking['selected_fare_baggage'] ?? null,
                $booking['amount'] ?? null,
                $selectedFare['validation_note'] ?? null,
            ], fn ($v): bool => is_string($v) && trim($v) !== ''));
            if ($fareBits !== []) {
                $meta['change_summary'] = implode(' · ', $fareBits);
            }
        }

        $payload = [
            'booking' => $booking,
            'payment' => $payment,
            'passengers' => $passengers,
            'itinerary' => $itinerary,
            'meta' => $meta,
            'greeting_name' => $universal['greeting_name'] ?? null,
        ];

        if (! $isCustomer) {
            $payload['admin'] = $adminIn;
            if (! empty($adminIn['admin_booking_url'])) {
                $payload['ctaUrlOverride'] = $adminIn['admin_booking_url'];
            }
        }

        if (! empty($ctaIn['url'])) {
            $payload['cta'] = $ctaIn;
        }

        return $payload;
    }

    public static function eventKeyFromUniversal(array $universal, ?string $fallbackEventKey = null): string
    {
        $type = strtolower(str_replace(['-', ' '], '_', trim((string) ($universal['type'] ?? ''))));
        if ($type !== '') {
            $mapped = JetpkEmailEventTypeMap::eventForType($type);
            if (is_string($mapped) && $mapped !== '') {
                return $mapped;
            }
            if (JetpkEmailEventContentRegistry::find($type) !== null) {
                return $type;
            }
        }

        $fallback = strtolower(trim((string) $fallbackEventKey));

        return $fallback !== '' ? $fallback : 'booking_status_changed';
    }
}
