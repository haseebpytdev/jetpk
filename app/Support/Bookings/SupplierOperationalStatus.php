<?php

namespace App\Support\Bookings;

class SupplierOperationalStatus
{
    /**
     * @param  array<string, mixed>|null  $bookingMeta  Optional `Booking::meta` for Sabre dry-run wording when supplier status is still `not_started`.
     * @return array{code: string, label: string, meaning: string}
     */
    public static function fromValues(?string $supplierStatus, ?string $provider, bool $hasPnr = false, ?array $bookingMeta = null): array
    {
        $raw = strtolower(trim((string) $supplierStatus));
        $provider = strtolower(trim((string) $provider));
        $providerSupportsAutomation = in_array($provider, ['duffel', 'sabre', 'pia_ndc', 'airline_direct', 'iati'], true);

        $code = match (true) {
            $raw === 'failed' => 'failed',
            in_array($raw, ['manual_review', 'review_required'], true) => 'manual_review',
            $raw === 'pending' => 'pending',
            $raw === 'pending_ticketing' && ! $hasPnr => 'pending',
            in_array($raw, ['created', 'booked', 'pending_ticketing', 'ticketed'], true) || $hasPnr => 'booked',
            $raw === 'ready' => 'ready',
            ! $providerSupportsAutomation && in_array($raw, ['not_started', '', 'none', 'unknown'], true) => 'not_supported',
            in_array($raw, ['payment_pending', 'offer_validated'], true) => 'ready',
            default => 'not_started',
        };

        $meaning = self::meaning($code);

        if ($code === 'not_started'
            && $provider === 'sabre'
            && ! $hasPnr
            && is_array($bookingMeta)) {
            $outcome = $bookingMeta['sabre_checkout_outcome'] ?? null;
            if (is_array($outcome)
                && ($outcome['status'] ?? '') === 'dry_run'
                && ($outcome['live_call_attempted'] ?? false) === false) {
                $meaning = 'Sabre dry-run prepared, no live PNR attempted.';
            }
        }

        return [
            'code' => $code,
            'label' => self::label($code),
            'meaning' => $meaning,
        ];
    }

    public static function label(string $code): string
    {
        return str_replace('_', ' ', $code);
    }

    public static function meaning(string $code): string
    {
        return match ($code) {
            'not_started' => 'No supplier booking attempted.',
            'ready' => 'Payment/offer state allows supplier booking.',
            'pending' => 'Supplier request in progress.',
            'booked' => 'Supplier reference/PNR stored.',
            'failed' => 'Supplier booking failed.',
            'manual_review' => 'Requires staff review.',
            'not_supported' => 'Provider cannot perform this action automatically.',
            default => 'Supplier status unavailable.',
        };
    }
}
