<?php

namespace App\Support\Emails;

/**
 * Structured text/plain from email semantics — not HTML table layout.
 */
final class JetpkEmailPlainTextComposer
{
    /**
     * @param  array{
     *   title?: string,
     *   greeting?: string,
     *   message?: string,
     *   facts?: list<array{label: string, value: string}>,
     *   cta_label?: string|null,
     *   cta_url?: string|null,
     *   support_email?: string|null,
     *   support_phone?: string|null,
     *   footer?: string|null
     * }  $parts
     */
    public static function compose(array $parts): string
    {
        $blocks = [];
        foreach (['title', 'greeting', 'message'] as $key) {
            $value = trim((string) ($parts[$key] ?? ''));
            if ($value !== '') {
                $blocks[] = $value;
            }
        }

        $facts = [];
        foreach ($parts['facts'] ?? [] as $row) {
            $label = trim((string) ($row['label'] ?? ''));
            $value = trim((string) ($row['value'] ?? ''));
            if ($label === '' || $value === '') {
                continue;
            }
            $facts[] = $label.': '.$value;
        }
        if ($facts !== []) {
            $blocks[] = implode("\n", $facts);
        }

        $ctaUrl = trim((string) ($parts['cta_url'] ?? ''));
        $ctaLabel = trim((string) ($parts['cta_label'] ?? ''));
        if ($ctaUrl !== '') {
            $blocks[] = ($ctaLabel !== '' ? $ctaLabel."\n" : '').$ctaUrl;
        }

        $support = [];
        $email = trim((string) ($parts['support_email'] ?? ''));
        $phone = trim((string) ($parts['support_phone'] ?? ''));
        if ($email !== '') {
            $support[] = 'Support: '.$email;
        }
        if ($phone !== '') {
            $support[] = 'Phone: '.$phone;
        }
        if ($support !== []) {
            $blocks[] = implode("\n", $support);
        }

        $footer = trim((string) ($parts['footer'] ?? ''));
        if ($footer !== '') {
            $blocks[] = $footer;
        }

        $text = implode("\n\n", $blocks);
        $text = str_replace(["\u{200C}", "\u{00A0}"], ['', ' '], $text);
        $text = preg_replace("/[ \t]+/", ' ', $text) ?? $text;
        $text = preg_replace("/\n{3,}/", "\n\n", $text) ?? $text;

        return trim($text);
    }

    /**
     * @param  list<array{label: string, value: string}>  $facts
     * @param  array<string, mixed>  $payload
     * @param  array<string, mixed>  $variables
     * @return list<array{label: string, value: string}>
     */
    public static function mergeBookingFacts(array $facts, array $payload, array $variables): array
    {
        $booking = is_array($payload['booking'] ?? null) ? $payload['booking'] : [];
        $itinerary = is_array($payload['itinerary'][0] ?? null) ? $payload['itinerary'][0] : [];
        $passengers = is_array($payload['passengers'] ?? null) ? $payload['passengers'] : [];
        $payment = is_array($payload['payment'] ?? null) ? $payload['payment'] : [];

        $pairs = [
            ['Booking reference', $booking['reference'] ?? $variables['booking_reference'] ?? null],
            ['PNR', $booking['pnr'] ?? $variables['pnr'] ?? null],
            ['Route', $booking['route'] ?? $variables['route'] ?? null],
            ['Trip type', $booking['trip_type'] ?? $variables['trip_type'] ?? null],
            ['Passengers', self::passengerSummary($passengers, $booking['passenger_count'] ?? null)],
            ['Booking status', $booking['status'] ?? $variables['booking_status'] ?? null],
            ['Payment status', $booking['payment_status'] ?? $payment['status'] ?? $variables['payment_status'] ?? null],
            ['Total', self::money($booking['amount'] ?? $payment['amount'] ?? $variables['amount'] ?? null, $booking['currency'] ?? $payment['currency'] ?? $variables['currency'] ?? null)],
            ['Airline', $itinerary['airline'] ?? null],
            ['Flight number', $itinerary['flight_no'] ?? null],
            ['Departure', $itinerary['depart'] ?? $variables['departure_date'] ?? null],
            ['Arrival', $itinerary['arrive'] ?? null],
            ['Baggage', $itinerary['baggage'] ?? null],
        ];

        $seen = [];
        foreach ($facts as $row) {
            $label = strtolower(trim((string) ($row['label'] ?? '')));
            if ($label !== '') {
                $seen[$label] = true;
            }
        }
        foreach ($pairs as [$label, $value]) {
            $value = trim((string) $value);
            if ($value === '' || isset($seen[strtolower($label)])) {
                continue;
            }
            $facts[] = ['label' => $label, 'value' => $value];
            $seen[strtolower($label)] = true;
        }

        return $facts;
    }

    private static function passengerSummary(array $passengers, mixed $count): string
    {
        $names = [];
        foreach ($passengers as $row) {
            if (! is_array($row)) {
                continue;
            }
            $name = trim((string) ($row['name'] ?? ''));
            if ($name !== '') {
                $names[] = $name;
            }
        }
        if ($names !== []) {
            return (string) count($names).' ('.implode(', ', $names).')';
        }
        $countText = trim((string) $count);

        return $countText;
    }

    private static function money(mixed $amount, mixed $currency): string
    {
        $amount = trim((string) $amount);
        $currency = trim((string) $currency);
        if ($amount === '') {
            return '';
        }

        return $currency !== '' ? $currency.' '.$amount : $amount;
    }
}
