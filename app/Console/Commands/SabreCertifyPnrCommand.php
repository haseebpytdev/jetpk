<?php

namespace App\Console\Commands;

use App\Models\Booking;
use App\Models\SupplierBookingAttempt;
use App\Services\Suppliers\Sabre\SabreBookingService;
use App\Services\Suppliers\Sabre\SabreInspectGate;
use App\Support\Bookings\ComplexItineraryPolicy;
use App\Support\Bookings\SabreOfferRefreshAcceptance;
use App\Support\Bookings\SabrePnrCertificationClassifier;
use App\Support\Bookings\SabrePnrCertificationSupport;
use Illuminate\Console\Command;

/**
 * C1: Local/testing Sabre PNR certification matrix (dry-run readiness + optional controlled --send).
 */
class SabreCertifyPnrCommand extends Command
{
    protected $signature = 'sabre:certify-pnr
                            {--booking= : Booking primary key}
                            {--send : Perform one controlled Passenger Records create (certification bypasses R5 complex guard)}
                            {--revalidate-first : Run one revalidation attempt before Passenger Records (certification only; does not change public checkout)}
                            {--mode=dry-run : dry-run or send (tests/CI may use --mode=send when --send flag parsing differs)}
                            {--dry-run : Payload/readiness only (forces dry-run even if --send)}
                            {--json : Emit machine-readable lines only}';

    protected $description = '[local/testing only] C1 Sabre PNR certification matrix — safe readiness, optional controlled create, PNR expiry extraction.';

    public function handle(
        SabreBookingService $sabreBooking,
        SabrePnrCertificationSupport $certificationSupport,
    ): int {
        if (! SabreInspectGate::allowed()) {
            $this->emitPayload(['error' => 'environment_not_allowed', 'booking_id' => $this->resolveBookingId()]);

            return self::FAILURE;
        }

        $bookingId = $this->resolveBookingId();
        if ($bookingId === null) {
            $this->emitPayload(['error' => 'missing_booking_id']);

            return self::FAILURE;
        }

        $booking = Booking::query()->find($bookingId);
        if ($booking === null) {
            $this->emitPayload(['error' => 'booking_not_found', 'booking_id' => $bookingId]);

            return self::FAILURE;
        }

        if (! $certificationSupport->isSabreBooking($booking)) {
            $this->emitPayload([
                'error' => 'booking_not_sabre',
                'booking_id' => $booking->id,
            ]);

            return self::FAILURE;
        }

        $send = $this->wantsCertificationSend();
        $dryRun = ! $send;

        $tripType = $certificationSupport->detectTripType($booking);
        $readiness = $certificationSupport->buildReadiness($booking);
        $pricingReadiness = $sabreBooking->assessAutoPnrPricingContextReadinessForBooking($booking);
        $revalidatePolicy = $certificationSupport->certificationRevalidatePolicy($pricingReadiness, $readiness);
        $r5PublicWouldDefer = ComplexItineraryPolicy::shouldDeferSabrePnr($booking, true);

        $payloadInspect = [];
        $wireContractValid = false;
        if ($dryRun) {
            $payloadInspect = $sabreBooking->inspectBookingPayloadShapeForCommand($booking);
            $wireContractValid = $certificationSupport->wireContractValidFromInspect($payloadInspect);
        }
        $segmentCount = (int) ($readiness['segment_count'] ?? ($payloadInspect['segment_count'] ?? 0));

        $payload = [
            'booking_id' => $booking->id,
            'trip_type' => $tripType,
            'mode' => $send && ! $dryRun ? 'send' : 'dry_run',
            'readiness' => $readiness,
            'wire_contract_valid' => $wireContractValid,
            'segment_count' => $segmentCount,
            'r5_public_checkout_would_defer' => $r5PublicWouldDefer,
            'validation_ok' => (bool) ($payloadInspect['validation_ok'] ?? false),
            'booking_schema' => (string) ($payloadInspect['booking_schema'] ?? ''),
            'pricing_context_ready' => ($pricingReadiness['auto_pnr_pricing_context_ready'] ?? false) === true,
            'missing_pricing_context_fields' => is_array($pricingReadiness['missing_pricing_context_fields'] ?? null)
                ? array_values($pricingReadiness['missing_pricing_context_fields'])
                : [],
            'certification_revalidate_required' => ($revalidatePolicy['required'] ?? false) === true,
            'certification_revalidate_reasons' => is_array($revalidatePolicy['reasons'] ?? null)
                ? array_values($revalidatePolicy['reasons'])
                : [],
            'certification_revalidate_exempt' => ($revalidatePolicy['exempt'] ?? false) === true,
            'certification_revalidate_exempt_reason' => isset($revalidatePolicy['exempt_reason'])
                ? (string) $revalidatePolicy['exempt_reason']
                : null,
            'revalidate_first_attempted' => false,
            'revalidate_success' => false,
            'pnr_created' => false,
            'pnr' => trim((string) ($booking->pnr ?? '')) !== '' ? strtoupper(trim((string) $booking->pnr)) : null,
            'classification' => '',
            'host_statuses' => [],
            'error_code' => null,
            'response_error_codes' => [],
            'response_error_messages' => [],
            'supplier_pnr_expires_at' => null,
            'supplier_pnr_expiry_source' => null,
            'latest_attempt_id' => null,
            'latest_attempt_action' => null,
        ];

        if ($dryRun && ! $send) {
            $payload['classification'] = SabrePnrCertificationClassifier::classifyDryRun(
                $r5PublicWouldDefer,
                $wireContractValid,
            );
            $this->emitPayload($payload);

            return self::SUCCESS;
        }

        $revalidateContext = [];
        $shouldRevalidateFirst = $this->option('revalidate-first')
            || (($revalidatePolicy['required'] ?? false) === true);
        if ($shouldRevalidateFirst) {
            $revalidateContext = $sabreBooking->runCertificationRevalidateFirst($booking);
            $payload['revalidate_first_attempted'] = ($revalidateContext['attempted'] ?? false) === true;
            $payload['revalidate_success'] = ($revalidateContext['success'] ?? false) === true;
            if (($revalidateContext['success'] ?? false) === true) {
                $sabreBooking->persistCertificationRevalidateLinkageForBooking($booking, $revalidateContext);
                $booking->refresh();
            }
        }

        if ($this->shouldSkipSendForManualPricingRequired($pricingReadiness, $revalidateContext)) {
            $payload['classification'] = SabrePnrCertificationClassifier::PNR_REQUIRES_MANUAL_SABRE_PRICING;
            $payload['error_code'] = 'sabre_pnr_manual_pricing_required';
            $this->emitPayload($payload);

            return self::FAILURE;
        }

        if (SabreOfferRefreshAcceptance::requiresAcceptance($booking)) {
            $payload['classification'] = SabrePnrCertificationClassifier::UPDATED_FARE_REQUIRES_ACCEPTANCE;
            $payload['error_code'] = SabreOfferRefreshAcceptance::ERROR_CODE_REQUIRES_ACCEPTANCE;
            $payload['pnr_created'] = false;
            $this->emitPayload($payload);

            return self::FAILURE;
        }

        $supplierResult = $sabreBooking->createSupplierBookingForCertification($booking, null);
        $booking->refresh();

        $pnr = $supplierResult->pnr ?? (trim((string) ($booking->pnr ?? '')) !== '' ? strtoupper(trim((string) $booking->pnr)) : null);
        $pnrCreated = $pnr !== null && $pnr !== '';

        $latestAttempt = SupplierBookingAttempt::query()
            ->where('booking_id', $booking->id)
            ->whereIn('action', [SabrePnrCertificationSupport::ACTION_CERTIFICATION, 'create_pnr'])
            ->orderByDesc('id')
            ->first();

        $safeSummary = array_merge(
            is_array($latestAttempt?->safe_summary) ? $latestAttempt->safe_summary : [],
            is_array($supplierResult->safe_summary) ? $supplierResult->safe_summary : [],
            $this->pricingContextSafeSummarySlice($pricingReadiness),
            $revalidateContext,
        );
        $errorCode = $supplierResult->error_code ?? ($latestAttempt?->error_code ?? null);
        $errorDigest = SabrePnrCertificationClassifier::sanitizedErrorDigest($safeSummary);

        $createResult = array_merge(
            is_array($supplierResult->safe_summary) ? $supplierResult->safe_summary : [],
            array_filter([
                'pnr' => $pnr,
                'error_code' => $errorCode,
                'http_status' => $safeSummary['http_status'] ?? null,
            ]),
        );

        $expiry = $certificationSupport->persistExpiryFromCreateResult($booking, $createResult);
        if (! $expiry['stored'] && $pnrCreated) {
            $expiry = $certificationSupport->tryPersistExpiryFromRetrieveProbe($booking->fresh());
        }

        $booking->refresh();
        $meta = is_array($booking->meta) ? $booking->meta : [];

        $payload['pnr_created'] = $pnrCreated;
        $payload['pnr'] = $pnrCreated ? $pnr : null;
        $payload['host_statuses'] = SabrePnrCertificationClassifier::sanitizedHostStatuses($safeSummary);
        $payload['error_code'] = $errorCode;
        $payload['response_error_codes'] = $errorDigest['response_error_codes'];
        $payload['response_error_messages'] = $errorDigest['response_error_messages'];
        $payload['latest_attempt_id'] = $latestAttempt?->id;
        $payload['latest_attempt_action'] = $latestAttempt?->action;
        $payload['supplier_pnr_expires_at'] = $meta[SabrePnrCertificationSupport::META_EXPIRES_AT] ?? null;
        $payload['supplier_pnr_expiry_source'] = $meta[SabrePnrCertificationSupport::META_EXPIRY_SOURCE] ?? null;
        $payload['classification'] = SabrePnrCertificationClassifier::classifySendOutcome(
            $pnrCreated,
            $payload['supplier_pnr_expires_at'] !== null,
            is_string($errorCode) ? $errorCode : null,
            $safeSummary,
        );
        $payload['fresh_shop_guard_result'] = $this->resolveFreshShopGuardResult($safeSummary, $latestAttempt);

        $this->emitPayload($payload);

        return $pnrCreated ? self::SUCCESS : self::FAILURE;
    }

    /**
     * @param  array<string, mixed>  $pricingReadiness
     * @param  array<string, mixed>  $revalidateContext
     */
    protected function shouldSkipSendForManualPricingRequired(array $pricingReadiness, array $revalidateContext): bool
    {
        if (! $this->wantsCertificationSend() || (bool) $this->option('dry-run')) {
            return false;
        }

        if (($pricingReadiness['auto_pnr_pricing_context_ready'] ?? false) === true) {
            return false;
        }

        if (($revalidateContext['success'] ?? false) === true) {
            return false;
        }

        if (($revalidateContext['includes_sabre_error_27131'] ?? false) === true) {
            return true;
        }

        return false;
    }

    /**
     * @param  array<string, mixed>  $pricingReadiness
     * @return array<string, mixed>
     */
    protected function pricingContextSafeSummarySlice(array $pricingReadiness): array
    {
        return [
            'auto_pnr_pricing_context_ready' => ($pricingReadiness['auto_pnr_pricing_context_ready'] ?? false) === true,
            'pricing_context_ready' => ($pricingReadiness['auto_pnr_pricing_context_ready'] ?? false) === true,
            'missing_pricing_context_fields' => is_array($pricingReadiness['missing_pricing_context_fields'] ?? null)
                ? array_values($pricingReadiness['missing_pricing_context_fields'])
                : [],
            'has_pricing_information_ref' => ($pricingReadiness['has_pricing_information_ref'] ?? false) === true,
            'has_offer_reference' => ($pricingReadiness['has_offer_reference'] ?? false) === true,
            'has_revalidation_linkage_complete' => ($pricingReadiness['has_revalidation_linkage_complete'] ?? false) === true,
        ];
    }

    protected function wantsCertificationSend(): bool
    {
        if ((bool) $this->option('dry-run')) {
            return false;
        }

        if ((bool) $this->option('send')) {
            return true;
        }

        return strtolower(trim((string) $this->option('mode', 'dry-run'))) === 'send';
    }

    /**
     * @param  array<string, mixed>  $safeSummary
     * @return array<string, mixed>
     */
    protected function resolveFreshShopGuardResult(array $safeSummary, ?SupplierBookingAttempt $latestAttempt): array
    {
        $fromSummary = $safeSummary['fresh_shop_guard_result'] ?? null;
        if (is_array($fromSummary) && $fromSummary !== []) {
            return $this->normalizeFreshShopGuardResult($fromSummary);
        }

        $attemptSummary = is_array($latestAttempt?->safe_summary) ? $latestAttempt->safe_summary : [];
        $fromAttempt = $attemptSummary['fresh_shop_guard_result'] ?? null;
        if (is_array($fromAttempt) && $fromAttempt !== []) {
            return $this->normalizeFreshShopGuardResult($fromAttempt);
        }

        return $this->normalizeFreshShopGuardResult([]);
    }

    /**
     * @param  array<string, mixed>  $raw
     * @return array<string, mixed>
     */
    protected function normalizeFreshShopGuardResult(array $raw): array
    {
        return [
            'per_segment_guard_passed' => ($raw['per_segment_guard_passed'] ?? false) === true,
            'per_segment_block_reason' => isset($raw['per_segment_block_reason'])
                ? (string) $raw['per_segment_block_reason']
                : null,
            'full_itinerary_guard_attempted' => ($raw['full_itinerary_guard_attempted'] ?? false) === true,
            'full_itinerary_guard_passed' => ($raw['full_itinerary_guard_passed'] ?? false) === true,
            'full_itinerary_guard_reason' => isset($raw['full_itinerary_guard_reason'])
                ? (string) $raw['full_itinerary_guard_reason']
                : null,
            'allowed_by_full_itinerary_confirmation' => ($raw['allowed_by_full_itinerary_confirmation'] ?? false) === true,
        ];
    }

    protected function resolveBookingId(): ?int
    {
        $raw = $this->option('booking');
        if ($raw === null || $raw === '' || ! is_numeric($raw)) {
            return null;
        }

        return (int) $raw;
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    protected function emitPayload(array $payload): void
    {
        app(SabrePnrCertificationSupport::class)->assertOutputSafe($payload);
        $line = 'pnr_certification_json='.json_encode($payload, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
        if (! (bool) $this->option('json')) {
            $this->line('Sabre PNR certification (local/testing only — no raw payloads, no PII).');
            $this->line('trip_type='.(string) ($payload['trip_type'] ?? ''));
            $this->line('mode='.(string) ($payload['mode'] ?? 'dry_run'));
            if (isset($payload['classification']) && (string) $payload['classification'] !== '') {
                $this->line('classification='.(string) $payload['classification']);
            }
            if (array_key_exists('pricing_context_ready', $payload)) {
                $this->line('pricing_context_ready='.(($payload['pricing_context_ready'] ?? false) ? 'true' : 'false'));
            }
        }
        $this->line($line);
    }
}
