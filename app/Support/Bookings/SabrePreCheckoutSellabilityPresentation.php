<?php

namespace App\Support\Bookings;

use App\Enums\SupplierProvider;
use App\Models\Booking;

/**
 * E5I/E5J: Passive UX/staff labels for pre-checkout sellability dry-run; E5J gates
 * {@see should_block_public_checkout} when {@see SabrePreCheckoutKnownFailureSoftBlock} config is on.
 */
final class SabrePreCheckoutSellabilityPresentation
{
    /**
     * @param  array<string, mixed>  $dryRun
     * @return array{
     *     label: string,
     *     severity: string,
     *     customer_message: string,
     *     staff_message: string,
     *     should_block_public_checkout: bool,
     *     should_attempt_auto_pnr: bool,
     *     generated_at: string
     * }
     */
    public static function fromDryRun(array $dryRun): array
    {
        $dryRunStatus = (string) ($dryRun['dry_run_status'] ?? '');
        $recommendedAction = (string) ($dryRun['recommended_checkout_action'] ?? '');

        return self::buildPresentation($dryRunStatus, $recommendedAction);
    }

    /**
     * @param  array<string, mixed>  $snapshot  Persisted pre_checkout_sellability_dry_run meta
     * @return array<string, mixed>
     */
    public static function fromDryRunMeta(array $snapshot): array
    {
        $dryRunStatus = (string) ($snapshot['status'] ?? '');
        $recommendedAction = (string) ($snapshot['recommended_checkout_action'] ?? '');

        return self::buildPresentation($dryRunStatus, $recommendedAction);
    }

    /**
     * @return array<string, mixed>|null
     */
    public static function resolveForBooking(Booking $booking): ?array
    {
        $meta = is_array($booking->meta) ? $booking->meta : [];
        $provider = strtolower(trim((string) ($meta['supplier_provider'] ?? $booking->supplier ?? '')));

        if ($provider !== SupplierProvider::Sabre->value) {
            return null;
        }

        $persisted = is_array($meta['pre_checkout_sellability_presentation'] ?? null)
            ? $meta['pre_checkout_sellability_presentation']
            : null;

        if ($persisted !== null && ($persisted['label'] ?? '') !== '') {
            return array_merge($persisted, [
                'should_block_public_checkout' => SabrePreCheckoutKnownFailureSoftBlock::wouldSoftBlockFromMeta($booking),
            ]);
        }

        $dryRunSnapshot = is_array($meta['pre_checkout_sellability_dry_run'] ?? null)
            ? $meta['pre_checkout_sellability_dry_run']
            : null;

        if ($dryRunSnapshot === null || ($dryRunSnapshot['status'] ?? '') === '') {
            return null;
        }

        return array_merge(self::fromDryRunMeta($dryRunSnapshot), [
            'should_block_public_checkout' => SabrePreCheckoutKnownFailureSoftBlock::wouldSoftBlockFromMeta($booking),
        ]);
    }

    /**
     * @param  array<string, mixed>  $presentation
     */
    public static function confirmationNote(array $presentation): ?string
    {
        return match ((string) ($presentation['label'] ?? '')) {
            'Verified automation candidate' => 'Your itinerary is queued for reservation processing.',
            'Fresh search recommended',
            'Do not retry same itinerary' => 'This itinerary may require a fresh availability check. Our team will review and contact you if an alternative is needed.',
            'Availability needs confirmation' => 'Airline availability will be confirmed before reservation is finalized.',
            default => null,
        };
    }

    /**
     * @return array<string, mixed>
     */
    protected static function buildPresentation(string $dryRunStatus, string $recommendedAction): array
    {
        $mapped = match ($dryRunStatus) {
            SabreCertifiedRouteSelector::EVIDENCE_STATUS_EXACT_SUCCESS => [
                'label' => 'Verified automation candidate',
                'severity' => 'success',
                'customer_message' => 'Your booking request has been received and is queued for reservation processing by our team.',
                'staff_message' => 'Exact success evidence matched. Auto-PNR candidate when feature flag is enabled.',
            ],
            SabreCertifiedRouteSelector::EVIDENCE_STATUS_EXACT_FAILED => [
                'label' => 'Fresh search recommended',
                'severity' => 'warning',
                'customer_message' => 'This fare may no longer be available from the airline. Our team will review or a fresh search may be required.',
                'staff_message' => 'Known Sabre host failed pattern. Do not retry same offer.',
            ],
            SabreCertifiedRouteSelector::EVIDENCE_STATUS_HOST_NOOP_BLOCKED => [
                'label' => 'Do not retry same itinerary',
                'severity' => 'danger',
                'customer_message' => 'This itinerary may require alternative options.',
                'staff_message' => 'Host-NOOP blocked. Do not retry same route/flight/date.',
            ],
            SabreCertifiedRouteSelector::EVIDENCE_STATUS_INSUFFICIENT_FLIGHT_DATE => [
                'label' => 'Availability needs confirmation',
                'severity' => 'info',
                'customer_message' => 'This fare requires confirmation before airline reservation is guaranteed.',
                'staff_message' => 'Unknown/unproven flight-date sellability evidence.',
            ],
            default => [
                'label' => 'Manual review',
                'severity' => 'secondary',
                'customer_message' => 'Your booking request is being reviewed by our team.',
                'staff_message' => 'No verified-lane pre-checkout evidence matched.',
            ],
        };

        return array_merge($mapped, [
            'should_block_public_checkout' => SabrePreCheckoutKnownFailureSoftBlock::wouldSoftBlock([
                'dry_run_status' => $dryRunStatus,
                'recommended_checkout_action' => $recommendedAction,
            ]),
            'should_attempt_auto_pnr' => false,
            'generated_at' => now()->toIso8601String(),
        ]);
    }
}
