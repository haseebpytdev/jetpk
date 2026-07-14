<?php

namespace App\Console\Commands;

use App\Models\Booking;
use App\Services\Suppliers\Sabre\Gds\SabreBookingOfferRefreshService;
use App\Support\Bookings\SabreControlledFreshPnrContextApply;
use App\Support\Bookings\SabreControlledStrongRevalidationLinkageApply;
use App\Support\Sabre\SabreControlledPnrFinalReadinessDiagnostics;
use App\Support\Sabre\SabreControlledPnrSellabilityDiagnostics;
use App\Support\Sabre\SabreControlledPnrStrongRevalidationLinkageDiagnostics;
use App\Support\Security\SensitiveDataRedactor;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Schema;

/**
 * F9N: Controlled fresh shop context apply before PNR (offer refresh meta only; no PNR/ticketing/cancellation).
 */
class SabreControlledApplyFreshPnrContextCommand extends Command
{
    protected $signature = 'sabre:controlled-apply-fresh-pnr-context
                            {--booking= : Booking ID}
                            {--reference= : Booking reference (booking_reference)}
                            {--dry-run : Evaluate eligibility and fresh probe only — no DB mutation}
                            {--confirm= : Exact phrase APPLY-FRESH-CONTEXT-FOR-BOOKING-{id} to apply}
                            {--json : Emit machine-readable lines only}';

    protected $description = 'Apply controlled fresh Sabre shop context to booking meta (no PNR; exact --confirm required to mutate)';

    public function handle(
        SabreControlledFreshPnrContextApply $applySupport,
        SabreControlledPnrSellabilityDiagnostics $sellabilityDiagnostics,
        SabreControlledPnrStrongRevalidationLinkageDiagnostics $linkageDiagnostics,
        SabreControlledStrongRevalidationLinkageApply $strongLinkageApply,
        SabreControlledPnrFinalReadinessDiagnostics $finalReadinessDiagnostics,
        SabreBookingOfferRefreshService $offerRefreshService,
    ): int {
        $booking = $this->resolveBooking();
        if ($booking === null) {
            $this->emitPayload([
                'classification' => 'fresh_context_apply_error',
                'error' => ($this->option('booking') === null && $this->option('reference') === null)
                    ? 'missing_booking_option'
                    : 'booking_not_found',
                'db_mutation_attempted' => false,
                'context_applied' => false,
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

        try {
            $refresh = $offerRefreshService->refresh($booking, false);
        } catch (\Throwable $e) {
            $this->emitPayload($this->safePayload($booking, [
                'classification' => 'controlled_fresh_context_apply_failed',
                'eligible' => false,
                'blockers' => ['fresh_probe_exception'],
                'probe_error_summary' => substr($e->getMessage(), 0, 120),
                'would_apply' => false,
                'db_mutation_attempted' => false,
                'context_applied' => false,
                'live_supplier_call_attempted' => true,
            ]));

            return self::FAILURE;
        }

        $probe = $applySupport->buildProbeFromRefresh($refresh);
        $eligibility = $applySupport->evaluateEligibility(
            $booking,
            $probe,
            $sellability,
            $dryRun,
            $confirmProvided,
        );

        $wouldApply = ($eligibility['eligible'] ?? false) === true && ! $productionBlocked;

        $basePayload = $this->safePayload($booking, [
            'booking_id' => $booking->id,
            'booking_reference' => (string) ($booking->reference_code ?? ''),
            'eligible' => (bool) ($eligibility['eligible'] ?? false),
            'blockers' => is_array($eligibility['blockers'] ?? null) ? array_values($eligibility['blockers']) : [],
            'fresh_probe_status' => (string) ($probe['probe_status'] ?? ''),
            'match_found' => (bool) ($probe['match_found'] ?? false),
            'match_confidence' => (string) ($probe['match_confidence'] ?? ''),
            'same_flight_numbers' => (bool) ($probe['same_flight_numbers'] ?? false),
            'same_rbd_list' => (bool) ($probe['same_rbd_list'] ?? false),
            'fare_basis_match' => (bool) ($probe['fare_basis_match'] ?? false),
            'existing_rbd_list' => is_array($probe['existing_rbd_list'] ?? null) ? $probe['existing_rbd_list'] : [],
            'fresh_rbd_list' => is_array($probe['fresh_rbd_list'] ?? null) ? $probe['fresh_rbd_list'] : [],
            'existing_fare_basis_list' => is_array($probe['existing_fare_basis_list'] ?? null) ? $probe['existing_fare_basis_list'] : [],
            'fresh_fare_basis_list' => is_array($probe['fresh_fare_basis_list'] ?? null) ? $probe['fresh_fare_basis_list'] : [],
            'probe_reasons' => is_array($probe['reasons'] ?? null) ? $probe['reasons'] : [],
            'controlled_pnr_manual_review_approved' => (bool) ($eligibility['controlled_pnr_manual_review_approved'] ?? false),
            'cpnr_schema_validation_status' => (string) ($eligibility['cpnr_schema_validation_status'] ?? ''),
            'recommended_lane' => (string) ($sellability['recommended_lane'] ?? ''),
            'live_supplier_call_attempted' => true,
            'pnr_create_attempted' => false,
            'ticketing_attempted' => false,
            'cancellation_attempted' => false,
            'controlled_pnr_retry_after_fresh_context_apply_requires_new_approval' => false,
            'final_freshness_rerun' => (bool) ($eligibility['final_freshness_rerun'] ?? false),
        ]);

        if ($dryRun) {
            $payload = array_merge($basePayload, [
                'classification' => 'controlled_fresh_context_apply_dry_run',
                'would_apply' => $wouldApply,
                'db_mutation_attempted' => false,
                'context_applied' => false,
            ]);
            if ($productionBlocked) {
                $payload['blockers'][] = 'production_confirm_required_for_live_apply';
            }
            $this->emitPayload($payload);

            return self::SUCCESS;
        }

        if ($productionBlocked) {
            $payload = array_merge($basePayload, [
                'classification' => 'controlled_fresh_context_apply_failed',
                'would_apply' => false,
                'db_mutation_attempted' => false,
                'context_applied' => false,
                'blocked_message' => 'Missing or invalid --confirm phrase. No booking meta mutation attempted.',
            ]);
            $payload['blockers'][] = 'missing_or_invalid_confirm_phrase';
            $this->emitPayload($payload);

            return self::FAILURE;
        }

        if (! ($eligibility['eligible'] ?? false)) {
            $payload = array_merge($basePayload, [
                'classification' => 'controlled_fresh_context_apply_failed',
                'would_apply' => false,
                'db_mutation_attempted' => false,
                'context_applied' => false,
                'blocked_message' => 'Fresh context apply eligibility gates blocked mutation.',
            ]);
            $this->emitPayload($payload);

            return self::FAILURE;
        }

        try {
            $applyResult = $offerRefreshService->refresh($booking, true);
        } catch (\Throwable $e) {
            $this->emitPayload($this->safePayload($booking, array_merge($basePayload, [
                'classification' => 'controlled_fresh_context_apply_failed',
                'would_apply' => false,
                'db_mutation_attempted' => false,
                'context_applied' => false,
                'apply_error_summary' => substr($e->getMessage(), 0, 120),
            ])));

            return self::FAILURE;
        }

        if (($applyResult['applied'] ?? false) !== true) {
            $this->emitPayload($this->safePayload($booking, array_merge($basePayload, [
                'classification' => 'controlled_fresh_context_apply_failed',
                'would_apply' => false,
                'db_mutation_attempted' => false,
                'context_applied' => false,
                'apply_blockers' => is_array($applyResult['reasons'] ?? null) ? $applyResult['reasons'] : [],
            ])));

            return self::FAILURE;
        }

        $booking->refresh();
        $metaBeforeApply = is_array($booking->meta) ? $booking->meta : [];
        $priorFreshRecord = $applySupport->extractRecord($metaBeforeApply);
        $priorStrongRecord = $strongLinkageApply->extractRecord($metaBeforeApply);
        $record = $applySupport->buildApplyRecord($booking, $probe, $priorFreshRecord);
        $meta = $metaBeforeApply;
        $meta[SabreControlledFreshPnrContextApply::META_KEY] = $record;
        $booking->forceFill(['meta' => $meta]);
        $booking->save();
        $booking->refresh();

        $linkageInspect = $linkageDiagnostics->inspectBooking($booking, false);
        $matrix = is_array($linkageInspect['strong_linkage_matrix'] ?? null)
            ? $linkageInspect['strong_linkage_matrix']
            : [];
        $linkageOutcome = $strongLinkageApply->preserveOrInvalidateAfterFreshRerun(
            $booking,
            $priorStrongRecord,
            $matrix,
        );
        $booking->refresh();

        $metaAfter = is_array($booking->meta) ? $booking->meta : [];
        $postSellability = $sellabilityDiagnostics->inspectBooking($booking, false);
        $postFinalReadiness = $finalReadinessDiagnostics->inspectBooking($booking);

        $payload = $this->safePayload($booking, array_merge($basePayload, [
            'classification' => 'controlled_fresh_context_applied',
            'would_apply' => true,
            'db_mutation_attempted' => true,
            'context_applied' => true,
            'applied_at' => (string) ($record['applied_at'] ?? ''),
            'refreshed_context_ready_for_controlled_pnr' => ($postSellability['stale_context_risk'] ?? true) === false,
            'offer_refresh_status' => (string) ($metaAfter['offer_refresh_status'] ?? ''),
            'offer_refresh_reason' => (string) ($metaAfter['offer_refresh_reason'] ?? ''),
            'last_revalidated_at' => (string) ($metaAfter['last_revalidated_at'] ?? ''),
            'selected_offer_created_at' => (string) ($postSellability['selected_offer_created_at'] ?? ''),
            'safe_refresh_context_complete' => (bool) ($postSellability['safe_refresh_context_complete'] ?? false),
            'pricing_snapshot_present' => (bool) ($postSellability['pricing_snapshot_present'] ?? false),
            'validated_offer_snapshot_present' => (bool) ($postSellability['validated_offer_snapshot_present'] ?? false),
            'stale_context_risk' => (bool) ($postSellability['stale_context_risk'] ?? true),
            'recommended_lane_after_apply' => (string) ($postSellability['recommended_lane'] ?? ''),
            'controlled_pnr_retry_after_fresh_context_apply_requires_new_approval' => true,
            'strong_linkage_preserved' => (bool) ($linkageOutcome['strong_linkage_preserved'] ?? false),
            'strong_linkage_recheck_required' => (bool) ($linkageOutcome['strong_linkage_recheck_required'] ?? false),
            'final_freshness_ready_after_apply' => (bool) ($postFinalReadiness['final_freshness_ready'] ?? false),
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
        return trim((string) $this->option('confirm')) === SabreControlledFreshPnrContextApply::confirmPhraseForBooking($booking);
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
        $payload['booking_reference'] ??= (string) ($booking->reference_code ?? '');
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
