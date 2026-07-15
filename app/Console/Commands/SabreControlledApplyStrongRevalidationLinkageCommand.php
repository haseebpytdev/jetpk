<?php

namespace App\Console\Commands;

use App\Models\Booking;
use App\Support\Bookings\SabreControlledStrongRevalidationLinkageApply;
use App\Support\Sabre\SabreControlledPnrSellabilityDiagnostics;
use App\Support\Sabre\SabreControlledPnrStrongRevalidationLinkageDiagnostics;
use App\Support\Security\SensitiveDataRedactor;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Schema;

/**
 * F9O: Controlled apply of strong BFM revalidation linkage to booking meta (no PNR/ticketing/cancellation).
 */
class SabreControlledApplyStrongRevalidationLinkageCommand extends Command
{
    protected $signature = 'sabre:controlled-apply-strong-revalidation-linkage
                            {--booking= : Booking ID}
                            {--reference= : Booking reference (booking_reference)}
                            {--dry-run : Evaluate eligibility only — no DB mutation}
                            {--confirm= : Exact phrase APPLY-STRONG-REVALIDATION-LINKAGE-FOR-BOOKING with numeric booking id suffix to apply}
                            {--json : Emit machine-readable lines only}';

    protected $description = 'Apply controlled strong Sabre BFM revalidation linkage to booking meta (no PNR; exact --confirm required to mutate)';

    public function handle(
        SabreControlledStrongRevalidationLinkageApply $applySupport,
        SabreControlledPnrStrongRevalidationLinkageDiagnostics $linkageDiagnostics,
        SabreControlledPnrSellabilityDiagnostics $sellabilityDiagnostics,
    ): int {
        $booking = $this->resolveBooking();
        if ($booking === null) {
            $this->emitPayload([
                'classification' => 'strong_linkage_apply_error',
                'error' => ($this->option('booking') === null && $this->option('reference') === null)
                    ? 'missing_booking_option'
                    : 'booking_not_found',
                'db_mutation_attempted' => false,
                'linkage_applied' => false,
                'live_supplier_call_attempted' => false,
                'pnr_create_attempted' => false,
                'ticketing_attempted' => false,
                'cancellation_attempted' => false,
            ]);

            return self::FAILURE;
        }

        $dryRun = $this->option('dry-run') === true;
        $confirmProvided = $this->confirmPhraseMatches($booking);
        $productionBlocked = $this->productionRequiresConfirm() && ! $dryRun && ! $confirmProvided;

        $sellability = $sellabilityDiagnostics->inspectBooking($booking, false);
        $linkageInspect = $linkageDiagnostics->inspectBooking($booking, false);
        $eligibility = $applySupport->evaluateEligibility($booking, $sellability, $linkageInspect, $dryRun);

        $wouldApply = ($eligibility['eligible'] ?? false) === true && ! $productionBlocked;

        $basePayload = $this->safePayload($booking, [
            'booking_id' => $booking->id,
            'booking_reference' => (string) ($booking->reference_code ?? $booking->booking_reference ?? ''),
            'eligible' => (bool) ($eligibility['eligible'] ?? false),
            'blockers' => is_array($eligibility['blockers'] ?? null) ? array_values($eligibility['blockers']) : [],
            'current_revalidation_linkage_strength' => (string) ($linkageInspect['current_revalidation_linkage_strength'] ?? ''),
            'strong_revalidation_candidate' => (bool) ($eligibility['strong_linkage_candidate'] ?? false),
            'strong_linkage_candidate' => (bool) ($eligibility['strong_linkage_candidate'] ?? false),
            'strong_linkage_blockers' => is_array($eligibility['strong_linkage_blockers'] ?? null)
                ? array_values($eligibility['strong_linkage_blockers'])
                : [],
            'recommended_lane' => (string) ($linkageInspect['recommended_lane'] ?? ''),
            'f9o_diagnostic_recommended_lane' => (string) ($eligibility['f9o_diagnostic_recommended_lane'] ?? ''),
            'sellability_recommended_lane' => (string) ($eligibility['sellability_recommended_lane'] ?? ''),
            'sellability_lane_used_as_hard_gate' => (bool) ($eligibility['sellability_lane_used_as_hard_gate'] ?? false),
            'stale_context_risk_hard_blocker' => (bool) ($eligibility['stale_context_risk_hard_blocker'] ?? false),
            'formal_revalidation_linkage_complete_before_apply' => (bool) ($eligibility['formal_revalidation_linkage_complete_before_apply'] ?? false),
            'weak_revalidation_risk' => (bool) ($linkageInspect['weak_revalidation_risk'] ?? true),
            'stale_context_risk' => (bool) ($linkageInspect['stale_context_risk'] ?? true),
            'live_supplier_call_attempted' => false,
            'pnr_create_attempted' => false,
            'ticketing_attempted' => false,
            'cancellation_attempted' => false,
            'controlled_pnr_retry_after_fresh_context_apply_requires_new_approval' => true,
        ]);

        if ($dryRun) {
            $payload = array_merge($basePayload, [
                'classification' => 'controlled_strong_linkage_apply_dry_run',
                'would_apply' => $wouldApply,
                'db_mutation_attempted' => false,
                'linkage_applied' => false,
            ]);
            if ($productionBlocked) {
                $payload['blockers'][] = 'production_confirm_required_for_live_apply';
            }
            $this->emitPayload($payload);

            return self::SUCCESS;
        }

        if ($productionBlocked) {
            $payload = array_merge($basePayload, [
                'classification' => 'controlled_strong_linkage_apply_failed',
                'would_apply' => false,
                'db_mutation_attempted' => false,
                'linkage_applied' => false,
                'blocked_message' => 'Missing or invalid --confirm phrase. No booking meta mutation attempted.',
            ]);
            $payload['blockers'][] = 'missing_or_invalid_confirm_phrase';
            $this->emitPayload($payload);

            return self::FAILURE;
        }

        if (! ($eligibility['eligible'] ?? false)) {
            $payload = array_merge($basePayload, [
                'classification' => 'controlled_strong_linkage_apply_failed',
                'would_apply' => false,
                'db_mutation_attempted' => false,
                'linkage_applied' => false,
                'blocked_message' => 'Strong linkage apply eligibility gates blocked mutation.',
            ]);
            $this->emitPayload($payload);

            return self::FAILURE;
        }

        $applyResult = $applySupport->applyLinkage($booking, $linkageInspect);

        if (($applyResult['applied'] ?? false) !== true) {
            $this->emitPayload($this->safePayload($booking, array_merge($basePayload, [
                'classification' => 'controlled_strong_linkage_apply_failed',
                'would_apply' => false,
                'db_mutation_attempted' => false,
                'linkage_applied' => false,
                'apply_blockers' => is_array($applyResult['blockers'] ?? null) ? $applyResult['blockers'] : [],
            ])));

            return self::FAILURE;
        }

        $booking->refresh();
        $postLinkage = $linkageDiagnostics->inspectBooking($booking, false);
        $postSellability = $sellabilityDiagnostics->inspectBooking($booking, false);
        $record = is_array($applyResult['record'] ?? null) ? $applyResult['record'] : [];

        $payload = $this->safePayload($booking, array_merge($basePayload, [
            'classification' => 'controlled_strong_linkage_applied',
            'would_apply' => true,
            'db_mutation_attempted' => true,
            'linkage_applied' => true,
            'applied_at' => (string) ($record['applied_at'] ?? ''),
            'applied_fields' => is_array($applyResult['applied_fields'] ?? null) ? $applyResult['applied_fields'] : [],
            'readiness_before_auto_pnr' => (bool) (($applyResult['readiness_before']['auto_pnr_pricing_context_ready'] ?? false)),
            'readiness_after_auto_pnr' => (bool) (($applyResult['readiness_after']['auto_pnr_pricing_context_ready'] ?? false)),
            'current_revalidation_linkage_strength_after' => (string) ($postLinkage['current_revalidation_linkage_strength'] ?? ''),
            'weak_revalidation_risk_after' => (bool) ($postLinkage['weak_revalidation_risk'] ?? true),
            'recommended_lane_after_apply' => (string) ($postLinkage['recommended_lane'] ?? ''),
            'sellability_recommended_lane_after_apply' => (string) ($postSellability['recommended_lane'] ?? ''),
            'controlled_pnr_retry_after_fresh_context_apply_requires_new_approval' => true,
        ]));

        $this->emitPayload($payload);

        return self::SUCCESS;
    }

    protected function resolveBooking(): ?Booking
    {
        $bookingId = $this->option('booking');
        if ($bookingId !== null && $bookingId !== '' && is_numeric($bookingId)) {
            return Booking::query()->find((int) $bookingId);
        }

        $reference = trim((string) $this->option('reference'));
        if ($reference !== '') {
            $booking = Booking::query()->where('booking_reference', $reference)->first();
            if ($booking !== null) {
                return $booking;
            }

            if (Schema::hasColumn('bookings', 'reference_code')) {
                return Booking::query()->where('reference_code', $reference)->first();
            }
        }

        return null;
    }

    protected function confirmPhraseMatches(Booking $booking): bool
    {
        return trim((string) $this->option('confirm')) === SabreControlledStrongRevalidationLinkageApply::confirmPhraseForBooking($booking);
    }

    protected function productionRequiresConfirm(): bool
    {
        return (string) config('app.env', 'production') === 'production';
    }

    /**
     * @param  array<string, mixed>  $payload
     * @return array<string, mixed>
     */
    protected function safePayload(Booking $booking, array $payload): array
    {
        $payload['booking_id'] ??= $booking->id;
        $payload['booking_reference'] ??= (string) ($booking->reference_code ?? $booking->booking_reference ?? '');
        $payload['pnr_create_attempted'] ??= false;
        $payload['ticketing_attempted'] ??= false;
        $payload['cancellation_attempted'] ??= false;

        return SensitiveDataRedactor::redact($payload);
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    protected function emitPayload(array $payload): void
    {
        if ((bool) $this->option('json')) {
            $this->line(json_encode($payload, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE));

            return;
        }

        foreach ($payload as $key => $value) {
            if (is_bool($value)) {
                $this->line($key.'='.($value ? 'true' : 'false'));
            } elseif (is_array($value)) {
                $this->line($key.'='.json_encode($value, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE));
            } elseif ($value === null || $value === '') {
                $this->line($key.'=');
            } else {
                $this->line($key.'='.$value);
            }
        }
    }
}
