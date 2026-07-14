<?php

namespace App\Support\Bookings;

use App\Enums\SupplierProvider;
use App\Models\Booking;

/**
 * E5H: Pre-checkout sellability dry-run (read-only; delegates to E5G evidence engine).
 *
 * Classifies selected Sabre itineraries before final checkout without live supplier calls,
 * PNR creation, or customer-facing behavior changes.
 */
final class SabrePreCheckoutSellabilityDryRun
{
    public const ACTION_CANDIDATE_AUTO_PNR_LATER = 'candidate_auto_pnr_later';

    public const ACTION_CONTINUE_MANUAL_SAFE = 'continue_manual_safe';

    public const ACTION_FRESH_SEARCH_RECOMMENDED = 'fresh_search_recommended';

    public const ACTION_BLOCKED_SAME_OFFER = 'blocked_same_offer';

    public function __construct(
        protected SabreVerifiedAutoPnrCandidateDiscovery $discovery,
    ) {}

    /**
     * @return array<string, mixed>
     */
    public function evaluate(Booking $booking): array
    {
        if (! $this->isSabreBooking($booking)) {
            return $this->nonSabreResult($booking);
        }

        $diag = $this->discovery->diagnose($booking);
        $dryRunStatus = (string) ($diag['evidence_status'] ?? SabreCertifiedRouteSelector::EVIDENCE_STATUS_UNKNOWN_CONTROLLED_ONLY);

        return [
            'booking_id' => $booking->id,
            'booking_reference' => (string) ($diag['booking_reference'] ?? $booking->booking_reference ?? ''),
            'dry_run_status' => $dryRunStatus,
            'dry_run_reason_code' => (string) ($diag['evidence_reason_code'] ?? ''),
            'recommended_checkout_action' => $this->resolveRecommendedCheckoutAction($dryRunStatus),
            'public_auto_pnr_allowed_now' => (bool) ($diag['public_auto_pnr_allowed_now'] ?? false),
            'live_supplier_call_attempted' => false,
            'booking_status_updated' => false,
            'evidence_booking_id_success' => $diag['matched_success_booking_id'] ?? null,
            'evidence_booking_id_failed' => $diag['matched_failed_booking_id'] ?? null,
            'segment_summary' => is_array($diag['segment_summary'] ?? null) ? $diag['segment_summary'] : [],
        ];
    }

    /**
     * @param  array<string, mixed>  $dryRun
     */
    public function persistCheckoutMeta(Booking $booking, array $dryRun): void
    {
        if (! $this->isSabreBooking($booking)) {
            return;
        }

        $meta = is_array($booking->meta) ? $booking->meta : [];
        $meta['pre_checkout_sellability_dry_run'] = [
            'status' => (string) ($dryRun['dry_run_status'] ?? ''),
            'reason_code' => (string) ($dryRun['dry_run_reason_code'] ?? ''),
            'recommended_checkout_action' => (string) ($dryRun['recommended_checkout_action'] ?? ''),
            'public_auto_pnr_allowed_now' => (bool) ($dryRun['public_auto_pnr_allowed_now'] ?? false),
            'live_supplier_call_attempted' => false,
            'booking_status_updated' => false,
            'evidence_booking_id_success' => $dryRun['evidence_booking_id_success'] ?? null,
            'evidence_booking_id_failed' => $dryRun['evidence_booking_id_failed'] ?? null,
            'generated_at' => now()->toIso8601String(),
        ];
        $meta['pre_checkout_sellability_presentation'] = SabrePreCheckoutSellabilityPresentation::fromDryRun($dryRun);

        $booking->forceFill(['meta' => $meta])->save();
    }

    /**
     * @return array<string, mixed>
     */
    public function evaluateAndPersist(Booking $booking): array
    {
        $dryRun = $this->evaluate($booking);
        $this->persistCheckoutMeta($booking, $dryRun);

        return $dryRun;
    }

    protected function resolveRecommendedCheckoutAction(string $dryRunStatus): string
    {
        return match ($dryRunStatus) {
            SabreCertifiedRouteSelector::EVIDENCE_STATUS_EXACT_SUCCESS => self::ACTION_CANDIDATE_AUTO_PNR_LATER,
            SabreCertifiedRouteSelector::EVIDENCE_STATUS_EXACT_FAILED,
            SabreCertifiedRouteSelector::EVIDENCE_STATUS_HOST_NOOP_BLOCKED => self::ACTION_BLOCKED_SAME_OFFER,
            SabreCertifiedRouteSelector::EVIDENCE_STATUS_INSUFFICIENT_FLIGHT_DATE => self::ACTION_FRESH_SEARCH_RECOMMENDED,
            default => self::ACTION_CONTINUE_MANUAL_SAFE,
        };
    }

    protected function isSabreBooking(Booking $booking): bool
    {
        $meta = is_array($booking->meta) ? $booking->meta : [];

        return (string) ($meta['supplier_provider'] ?? '') === SupplierProvider::Sabre->value;
    }

    /**
     * @return array<string, mixed>
     */
    protected function nonSabreResult(Booking $booking): array
    {
        return [
            'booking_id' => $booking->id,
            'booking_reference' => (string) ($booking->booking_reference ?? ''),
            'dry_run_status' => SabreCertifiedRouteSelector::EVIDENCE_STATUS_UNKNOWN_CONTROLLED_ONLY,
            'dry_run_reason_code' => SabreVerifiedAutoPnrReadiness::REASON_NOT_SABRE,
            'recommended_checkout_action' => self::ACTION_CONTINUE_MANUAL_SAFE,
            'public_auto_pnr_allowed_now' => false,
            'live_supplier_call_attempted' => false,
            'booking_status_updated' => false,
            'evidence_booking_id_success' => null,
            'evidence_booking_id_failed' => null,
            'segment_summary' => [],
        ];
    }
}
