<?php

namespace App\Services\Suppliers\Sabre\Cancel;

use App\Enums\BookingStatus;
use App\Enums\SupplierProvider;
use App\Models\Booking;
use App\Models\SupplierBookingAttempt;
use App\Models\SupplierConnection;
use App\Services\Suppliers\Sabre\Core\SabreBookingClient;
use App\Services\Suppliers\Sabre\Core\SabreClient;
use App\Services\Suppliers\Sabre\PnrRetrieve\SabrePnrRetrieveProbe;
use App\Services\Suppliers\Sabre\PnrRetrieve\SabreTripOrdersGetBookingInspectSummary;
use App\Support\Security\SensitiveDataRedactor;

/**
 * Gated Sabre Trip Orders cancel for operator-controlled unticketed PNR cancellation.
 * Retrieves before and after cancel, uses the certified confirmationId full-cancel payload, and returns safe scalar outcomes only.
 */
final class SabreBookingCancelService
{
    public const CLASSIFICATION_CANCEL_CONFIRMED = 'CANCEL_CONFIRMED';

    public const CLASSIFICATION_CANCEL_CONFIRMED_AIR_SEGMENTS_REMOVED = 'CANCEL_CONFIRMED_AIR_SEGMENTS_REMOVED';

    public const CLASSIFICATION_HTTP_200_BUT_STILL_ACTIVE = 'HTTP_200_BUT_STILL_ACTIVE';

    public const CATEGORY_CANCEL_NOT_ELIGIBLE = 'CANCEL_NOT_ELIGIBLE';

    public const CATEGORY_CANCEL_PAYLOAD_MISSING = 'CANCEL_PAYLOAD_MISSING';

    public const CATEGORY_TICKETED_REFUND_REQUIRED = 'TICKETED_REFUND_REQUIRED';

    public const CATEGORY_CANCEL_NOT_VERIFIED = 'CANCEL_NOT_VERIFIED';

    public const CATEGORY_CANCEL_SUPPLIER_FAILED = 'CANCEL_SUPPLIER_FAILED';

    public const CATEGORY_CANCEL_VERIFIED = 'CANCEL_VERIFIED';

    public const CATEGORY_LIVE_CANCEL_DISABLED = 'LIVE_CANCEL_DISABLED';

    public function __construct(
        protected SabreCancelPayloadBuilder $payloadBuilder,
        protected SabreBookingClient $bookingClient,
        protected SabrePnrRetrieveProbe $pnrRetrieveProbe,
        protected SabreTripOrdersGetBookingInspectSummary $getBookingInspectSummary,
    ) {}

    /**
     * @param  array<string, mixed>  $executionContext
     * @return array<string, mixed>
     */
    public function cancelForBooking(Booking $booking, bool $operatorConfirmed = false, array $executionContext = []): array
    {
        $meta = is_array($booking->meta) ? $booking->meta : [];
        $provider = strtolower(trim((string) ($meta['supplier_provider'] ?? $booking->supplier ?? '')));

        $base = [
            'booking_id' => $booking->id,
            'provider' => $provider,
            'live_call_attempted' => false,
            'supplier_cancel_verified' => false,
            'ticketing_disabled' => true,
        ];

        if ($provider !== SupplierProvider::Sabre->value) {
            return $this->outcome($base, false, 'not_sabre', self::CATEGORY_CANCEL_NOT_ELIGIBLE,
                'This booking is not eligible for automated supplier cancellation.');
        }

        $pnr = $this->resolvePnr($booking, $meta);
        if ($pnr === '') {
            return $this->outcome($base, false, 'validation_failed', self::CATEGORY_CANCEL_PAYLOAD_MISSING,
                'Supplier cancellation requires a confirmed booking reference from the airline.');
        }

        if ($this->isLocallyCancelled($booking)) {
            return $this->outcome($base, true, 'already_cancelled', self::CATEGORY_CANCEL_VERIFIED,
                'Booking is already cancelled.');
        }

        if ($this->isLocallyTicketed($booking)) {
            return $this->outcome($base, false, 'ticketed_manual_review', self::CATEGORY_TICKETED_REFUND_REQUIRED,
                'Ticketed bookings require manual void or refund handling by our team.');
        }

        $connection = $this->resolveBookingConnection($booking, $meta);
        if ($connection === null) {
            return $this->outcome($base, false, 'connection_missing', self::CATEGORY_CANCEL_NOT_ELIGIBLE,
                'Supplier cancellation cannot be completed automatically; our team will review your request.');
        }

        $globalLiveCancelAllowed = SabreCancelBookingInspectProbe::mayPerformLiveSabreCancelCall();
        $adminLiveCancelAllowed = $this->adminLiveCancelApproved($executionContext);
        if (! $globalLiveCancelAllowed && ! $adminLiveCancelAllowed) {
            return $this->outcome($base, false, $this->liveCancelBlockedReason($executionContext), self::CATEGORY_LIVE_CANCEL_DISABLED,
                'Supplier live cancellation is disabled; local cancellation workflow applies.',
                ['pnr_present' => true, 'supplier_connection_id' => $connection->id]);
        }

        $gate = $this->workflowLiveCancelGates($connection, $operatorConfirmed || $adminLiveCancelAllowed);
        if (($gate['allowed'] ?? false) !== true) {
            return $this->outcome($base, false, 'live_cancel_gated', self::CATEGORY_CANCEL_NOT_ELIGIBLE,
                'Supplier cancellation is not enabled for this environment; our team will review your request.',
                ['live_send_gates' => $gate, 'supplier_connection_id' => $connection->id]);
        }

        $preFetch = $this->pnrRetrieveProbe->fetchTripOrdersGetBooking($booking);
        if (isset($preFetch['error'])) {
            return $this->recordAndReturn($booking, $connection, null, $preFetch, $base, false,
                'pre_retrieve_failed', self::CATEGORY_CANCEL_NOT_ELIGIBLE,
                'We could not verify the booking with the airline; our team will review your cancellation request.');
        }

        $preJson = is_array($preFetch['json'] ?? null) ? $preFetch['json'] : [];
        if ($preJson === []) {
            return $this->recordAndReturn($booking, $connection, null, $preFetch, $base, false,
                'pre_retrieve_empty', self::CATEGORY_CANCEL_NOT_ELIGIBLE,
                'We could not verify the booking with the airline; our team will review your cancellation request.');
        }

        $preTrip = SabreTripOrderCancelContext::fromGetBookingJson($this->getBookingInspectSummary, $preJson);
        $preSafety = $this->getBookingInspectSummary->extractDirectCancelSafetyFlags($preJson);

        $eligibility = $this->preCancelEligibility($preTrip, $preSafety, $preJson);
        if ($eligibility !== null) {
            return $this->outcome(array_merge($base, [
                'supplier_connection_id' => $connection->id,
                'trip_order_context_source' => $preTrip->contextSource,
            ] + $preTrip->safePublicSlice()), false, $eligibility['status'], $eligibility['category'],
                $eligibility['message']);
        }

        $cancelContext = SabreCancelBookingContext::fromBooking($booking, true, $preTrip);
        $equivalenceAnalysis = null;
        $candidates = $this->payloadBuilder->buildCandidatePayloads(
            $pnr,
            $this->resolveSupplierApiBookingId($booking, $meta),
            $this->resolveSupplierReference($booking, $meta),
            $cancelContext,
            $equivalenceAnalysis,
        );
        $selected = $this->selectWorkflowCandidate($candidates);
        if ($selected === []) {
            return $this->recordAndReturn($booking, $connection, null, [], $base, false,
                'no_cancel_payload', self::CATEGORY_CANCEL_PAYLOAD_MISSING,
                'Supplier cancellation could not be prepared; our team will review your request.',
                ['cancel_diagnostics' => $cancelContext->diagnosticsSlice()]);
        }

        $httpResult = $this->bookingClient->inspectCancelBooking(
            $connection,
            $selected['body'],
            [
                'booking_id' => $booking->id,
                'supplier_connection_id' => $connection->id,
                'payload_style' => $selected['style'],
                'primary_identifier_source' => $selected['primary_identifier_source'],
            ],
        );

        $base['live_call_attempted'] = (bool) ($httpResult['live_call_attempted'] ?? false);
        $httpStatus = (int) ($httpResult['http_status'] ?? 0);
        $httpSuccess = ($httpResult['success'] ?? false) === true
            || ($httpStatus >= 200 && $httpStatus < 300);

        if (! $httpSuccess) {
            return $this->recordAndReturn($booking, $connection, $selected, $httpResult, $base, false,
                'supplier_cancel_failed', self::CATEGORY_CANCEL_SUPPLIER_FAILED,
                'We could not complete the cancellation with the airline; our team will follow up.',
                ['cancel_probe' => $this->safeCancelProbeSummary($httpResult)]);
        }

        $postFetch = $this->pnrRetrieveProbe->fetchTripOrdersGetBooking($booking);
        $postCancelSummary = $this->postCancelSummary($postFetch, $httpResult);
        $classification = (string) ($postCancelSummary['classification'] ?? 'UNKNOWN');
        $verified = $this->isConfirmedClassification($classification);

        if (! $verified) {
            return $this->recordAndReturn($booking, $connection, $selected, $httpResult, $base, false,
                'cancel_not_verified', self::CATEGORY_CANCEL_NOT_VERIFIED,
                'Cancellation was submitted but could not be confirmed; our team will verify and follow up.',
                [
                    'cancel_probe' => $this->safeCancelProbeSummary($httpResult),
                    'post_cancel_verification' => $postCancelSummary,
                ]);
        }

        return $this->recordAndReturn($booking, $connection, $selected, $httpResult, $base, true,
            'cancelled', self::CATEGORY_CANCEL_VERIFIED,
            'Booking cancelled with the airline.',
            [
                'supplier_cancel_verified' => true,
                'classification' => $classification,
                'cancel_probe' => $this->safeCancelProbeSummary($httpResult),
                'post_cancel_verification' => $postCancelSummary,
            ]);
    }

    /**
     * Workflow cancel gates: same env flags as inspect probe but no Artisan confirm phrase.
     *
     * @return array<string, mixed>
     */
    public function workflowLiveCancelGates(SupplierConnection $connection, bool $operatorConfirmed = false): array
    {
        $endpointPath = (string) config('suppliers.sabre.cancel_endpoint_path', '/v1/trip/orders/cancelBooking');
        $parts = app(SabreClient::class)->resolveEndpointParts($connection, $endpointPath);
        $resolvedBaseUrl = rtrim((string) ($connection->base_url ?: config('suppliers.sabre.default_base_url')), '/');
        $certBaseConfirmed = SabreCancelBookingInspectProbe::isCertificationBaseUrl($resolvedBaseUrl);
        $productionHostConfirmed = SabreCancelBookingInspectProbe::isProductionBaseUrl($resolvedBaseUrl);
        $baseUrlHost = (string) ($parts['endpoint_host'] ?? 'unknown');
        $appEnv = (string) config('app.env', 'production');

        $gate = [
            'app_env' => $appEnv,
            'base_url_host' => $baseUrlHost,
            'cert_base_url_confirmed' => $certBaseConfirmed,
            'production_host_confirmed' => $productionHostConfirmed,
            'cancel_allow_production_send' => SabreCancelBookingInspectProbe::isCancelProductionSendAllowed(),
            'cancel_allow_production_host' => SabreCancelBookingInspectProbe::isCancelProductionHostAllowed(),
            'operator_confirmed_admin_staff_action' => $operatorConfirmed,
            'allowed' => false,
            'block_reason' => null,
        ];

        if (! $operatorConfirmed && ! SabreCancelBookingInspectProbe::isCancelEnabled()) {
            return array_merge($gate, ['block_reason' => 'sabre_cancel_disabled']);
        }

        if (! $operatorConfirmed && ! SabreCancelBookingInspectProbe::isCancelLiveCallEnabled()) {
            return array_merge($gate, ['block_reason' => 'sabre_cancel_live_call_disabled']);
        }

        if ($certBaseConfirmed) {
            return array_merge($gate, ['allowed' => true, 'block_reason' => null]);
        }

        if ($productionHostConfirmed) {
            if (! $operatorConfirmed && ! SabreCancelBookingInspectProbe::isCancelProductionHostAllowed()) {
                return array_merge($gate, ['block_reason' => 'production_host_not_allowed']);
            }
            if (! $operatorConfirmed && $appEnv === 'production' && ! SabreCancelBookingInspectProbe::isCancelProductionSendAllowed()) {
                return array_merge($gate, ['block_reason' => 'production_send_not_allowed']);
            }

            return array_merge($gate, ['allowed' => true, 'block_reason' => null]);
        }

        return array_merge($gate, ['block_reason' => 'sabre_host_not_cert_or_production']);
    }

    /**
     * @param  array<string, mixed>  $safety
     * @return array{status: string, category: string, message: string}|null
     */
    protected function preCancelEligibility(SabreTripOrderCancelContext $trip, array $safety, array $preJson): ?array
    {
        if (($safety['is_ticketed'] ?? null) === true || ($trip->isTicketed ?? null) === true) {
            return [
                'status' => 'ticketed_booking',
                'category' => self::CATEGORY_TICKETED_REFUND_REQUIRED,
                'message' => 'Ticketed bookings require manual void or refund handling by our team.',
            ];
        }

        if (($safety['ticket_numbers_present'] ?? false) === true) {
            return [
                'status' => 'ticketed_booking',
                'category' => self::CATEGORY_TICKETED_REFUND_REQUIRED,
                'message' => 'Ticketed bookings require manual void or refund handling by our team.',
            ];
        }

        $segmentCount = $this->segmentCountFromProbeRow(
            $this->getBookingInspectSummary->buildForProbeRow(
                $preJson,
                ['http_status' => 200],
            ),
        );
        if ($segmentCount <= 0) {
            return [
                'status' => 'no_active_air_segments',
                'category' => self::CATEGORY_CANCEL_NOT_ELIGIBLE,
                'message' => 'Supplier booking has no active air segments to cancel.',
            ];
        }

        if (($safety['is_cancelable'] ?? null) === false || ($trip->isCancelable ?? null) === false) {
            return [
                'status' => 'not_cancelable',
                'category' => self::CATEGORY_CANCEL_NOT_ELIGIBLE,
                'message' => 'This booking is not eligible for automated cancellation; our team will review your request.',
            ];
        }

        if (($safety['is_cancelable'] ?? null) !== true) {
            return [
                'status' => 'cancelable_unknown',
                'category' => self::CATEGORY_CANCEL_NOT_ELIGIBLE,
                'message' => 'We could not confirm cancellation eligibility with the airline; our team will review your request.',
            ];
        }

        return null;
    }

    /**
     * @param  array<string, mixed>  $postFetch
     */
    protected function isConfirmedClassification(string $classification): bool
    {
        return in_array($classification, [
            self::CLASSIFICATION_CANCEL_CONFIRMED,
            self::CLASSIFICATION_CANCEL_CONFIRMED_AIR_SEGMENTS_REMOVED,
        ], true);
    }

    /**
     * @param  array<string, mixed>  $postFetch
     * @return array<string, mixed>
     */
    protected function postCancelSummary(array $postFetch, array $httpResult): array
    {
        if (isset($postFetch['error'])) {
            return [
                'classification' => $this->classifyCancelOutcome($httpResult, []),
                'error' => (string) $postFetch['error'],
            ];
        }

        $json = is_array($postFetch['json'] ?? null) ? $postFetch['json'] : [];
        $httpStatus = (int) ($postFetch['http_status'] ?? 0);
        if ($json === []) {
            $summary = ['cancel_verification_status' => 'unknown', 'http_status' => $httpStatus];

            return $summary + ['classification' => $this->classifyCancelOutcome($httpResult, $summary)];
        }

        $row = $this->getBookingInspectSummary->buildForProbeRow($json, ['http_status' => $httpStatus]);
        $statusSummary = is_array($row['get_booking_status_summary'] ?? null) ? $row['get_booking_status_summary'] : [];
        $segmentCount = $this->segmentCountFromProbeRow($row);
        $ticketNumbersPresent = (bool) ($this->getBookingInspectSummary->extractDirectCancelSafetyFlags($json)['ticket_numbers_present'] ?? false);
        $pnrShellPresent = ((int) ($statusSummary['traveler_count'] ?? 0) > 0)
            || ((int) ($statusSummary['fare_count'] ?? 0) > 0)
            || ((int) ($statusSummary['remark_count'] ?? 0) > 0)
            || (($statusSummary['contact_info_present'] ?? false) === true);

        $summary = [
            'cancel_verification_possible' => (bool) ($row['cancel_verification_possible'] ?? false),
            'cancel_verification_status' => (string) ($row['cancel_verification_status'] ?? 'unknown'),
            'cancel_verification_reason' => (string) ($row['cancel_verification_reason'] ?? ''),
            'http_status' => $httpStatus,
            'cancel_air_segments_removed' => $httpStatus >= 200
                && $httpStatus < 300
                && $segmentCount === 0
                && $pnrShellPresent
                && ! $ticketNumbersPresent,
            'post_cancel_segment_count' => $segmentCount,
            'ticket_numbers_present' => $ticketNumbersPresent,
        ];
        $summary['classification'] = $this->classifyCancelOutcome($httpResult, $summary);

        return $summary;
    }

    /**
     * @param  array<string, mixed>  $httpResult
     * @param  array<string, mixed>  $postCancel
     */
    protected function classifyCancelOutcome(array $httpResult, array $postCancel): string
    {
        $diag = is_array($httpResult['booking_diagnostics'] ?? null) ? $httpResult['booking_diagnostics'] : [];
        $codes = array_map('strval', is_array($diag['response_error_codes'] ?? null) ? $diag['response_error_codes'] : []);
        $messages = array_map('strval', is_array($diag['response_error_messages'] ?? null) ? $diag['response_error_messages'] : []);
        $combined = strtoupper(implode(' ', array_merge($codes, $messages)));

        if (str_contains($combined, 'CANCEL_DATA_MISSING') || str_contains($combined, 'NO CANCEL DATA PROVIDED')) {
            return 'CANCEL_DATA_MISSING';
        }
        if (str_contains($combined, 'NO_ITEMS_CANCELLED') || str_contains($combined, 'NO ITEMS CANCELLED')) {
            return 'NO_ITEMS_CANCELLED';
        }
        if (str_contains($combined, 'INVALID_CANCEL_TARGET')
            || str_contains($combined, 'INVALID CANCEL TARGET')
            || str_contains($combined, 'SEGMENT_NOT_FOUND')) {
            return 'INVALID_CANCEL_TARGET';
        }

        $httpStatus = (int) ($httpResult['http_status'] ?? 0);
        if ($httpStatus >= 400 && $httpStatus < 500) {
            return 'HOST_VALIDATION_ERROR';
        }

        $verificationStatus = (string) ($postCancel['cancel_verification_status'] ?? '');
        if ($httpStatus >= 200
            && $httpStatus < 300
            && ($postCancel['cancel_air_segments_removed'] ?? false) === true) {
            return self::CLASSIFICATION_CANCEL_CONFIRMED_AIR_SEGMENTS_REMOVED;
        }
        if ($verificationStatus === 'likely_cancelled') {
            return self::CLASSIFICATION_CANCEL_CONFIRMED;
        }
        if ($httpStatus >= 200 && $httpStatus < 300 && $verificationStatus === 'likely_active') {
            return self::CLASSIFICATION_HTTP_200_BUT_STILL_ACTIVE;
        }

        return 'UNKNOWN';
    }

    /**
     * @param  array<string, mixed>  $row
     */
    protected function segmentCountFromProbeRow(array $row): int
    {
        $segmentPathCount = $this->sumSafePathCounts($row['possible_segment_paths'] ?? []);
        $airPathCount = $this->sumSafePathCounts($row['possible_air_item_paths'] ?? []);

        return $segmentPathCount > 0 ? $segmentPathCount : $airPathCount;
    }

    protected function sumSafePathCounts(mixed $paths): int
    {
        if (! is_array($paths)) {
            return 0;
        }

        $count = 0;
        foreach ($paths as $row) {
            if (is_array($row) && is_numeric($row['count'] ?? null)) {
                $count += (int) $row['count'];
            }
        }

        return $count;
    }

    /**
     * @param  array<string, mixed>  $executionContext
     */
    protected function adminLiveCancelApproved(array $executionContext): bool
    {
        $actorContext = strtolower(trim((string) ($executionContext['actor_context'] ?? '')));

        if (($executionContext['admin_live_cancel_approved'] ?? false) !== true) {
            return false;
        }
        if (($executionContext['bypass_global_cancel_flags_for_admin'] ?? false) !== true) {
            return false;
        }
        if (! in_array($actorContext, ['admin', 'staff'], true)) {
            return false;
        }

        return (bool) config('suppliers.sabre.admin_cancel_live_call_enabled', false);
    }

    /**
     * @param  array<string, mixed>  $executionContext
     */
    protected function liveCancelBlockedReason(array $executionContext): string
    {
        $actorContext = strtolower(trim((string) ($executionContext['actor_context'] ?? '')));

        if (($executionContext['admin_live_cancel_approved'] ?? false) === true
            && ! in_array($actorContext, ['admin', 'staff'], true)) {
            return 'not_admin_staff_context';
        }

        if (($executionContext['admin_live_cancel_approved'] ?? false) === true
            && ! (bool) config('suppliers.sabre.admin_cancel_live_call_enabled', false)) {
            return 'admin_gate_not_passed';
        }

        if (($executionContext['admin_live_cancel_approved'] ?? false) === true) {
            return 'admin_gate_not_passed';
        }

        if (($executionContext['bypass_global_cancel_flags_for_admin'] ?? false) === true) {
            return 'admin_gate_not_passed';
        }

        return SabreCancelBookingInspectProbe::isCancelEnabled() ? 'dry_run' : 'global_cancel_disabled';
    }

    /**
     * @param  list<array<string, mixed>>  $candidates
     * @return array{style: string, body: array<string, mixed>, primary_identifier_source: string}|array{}
     */
    protected function selectWorkflowCandidate(array $candidates): array
    {
        foreach ($candidates as $row) {
            if (($row['style'] ?? '') !== SabreCancelPayloadBuilder::STYLE_OFFICIAL_POSTMAN_CONFIRMATION_CANCEL_ALL) {
                continue;
            }
            if (($row['previously_failed_reason'] ?? null) !== null
                || ($row['previously_ineffective_reason'] ?? null) !== null) {
                return [];
            }

            return $row;
        }

        return [];
    }

    /**
     * @param  list<array<string, mixed>>  $candidates
     * @return array{style: string, body: array<string, mixed>, primary_identifier_source: string}|array{}
     */
    protected function selectRecommendedCandidate(array $candidates): array
    {
        foreach ($candidates as $row) {
            if (($row['recommended'] ?? false) === true
                && ($row['previously_failed_reason'] ?? null) === null
                && ($row['previously_ineffective_reason'] ?? null) === null) {
                return $row;
            }
        }
        if (! SabreCancelPayloadBuilder::usesAutoConfiguredPayloadStyle()) {
            return [];
        }
        foreach ($candidates as $row) {
            if (($row['previously_failed_reason'] ?? null) === null
                && ($row['previously_ineffective_reason'] ?? null) === null) {
                return $row;
            }
        }

        return [];
    }

    /**
     * @param  array<string, mixed>  $base
     * @param  array<string, mixed>  $extra
     * @return array<string, mixed>
     */
    protected function outcome(
        array $base,
        bool $success,
        string $status,
        string $category,
        string $message,
        array $extra = [],
    ): array {
        return SensitiveDataRedactor::redact(array_merge($base, $extra, [
            'success' => $success,
            'status' => $status,
            'safe_summary_category' => $category,
            'message' => $message,
        ]));
    }

    /**
     * @param  array{style: string, body: array<string, mixed>, primary_identifier_source: string}|null  $selected
     * @param  array<string, mixed>  $httpResult
     * @param  array<string, mixed>  $base
     * @param  array<string, mixed>  $extra
     * @return array<string, mixed>
     */
    protected function recordAndReturn(
        Booking $booking,
        SupplierConnection $connection,
        ?array $selected,
        array $httpResult,
        array $base,
        bool $success,
        string $status,
        string $category,
        string $message,
        array $extra = [],
    ): array {
        if ($selected !== null && ($base['live_call_attempted'] ?? false) === true) {
            $this->recordCancelAttempt($booking, $connection, $selected, $httpResult, $category, $success);
        }

        return $this->outcome($base, $success, $status, $category, $message, array_merge($extra, [
            'supplier_connection_id' => $connection->id,
            'payload_style' => $selected['style'] ?? null,
        ]));
    }

    /**
     * @param  array{style: string, body: array<string, mixed>, primary_identifier_source: string}  $selected
     * @param  array<string, mixed>  $httpResult
     */
    protected function recordCancelAttempt(
        Booking $booking,
        SupplierConnection $connection,
        array $selected,
        array $httpResult,
        string $category,
        bool $verified,
    ): void {
        $diag = is_array($httpResult['booking_diagnostics'] ?? null) ? $httpResult['booking_diagnostics'] : [];
        $httpStatus = (int) ($httpResult['http_status'] ?? 0);
        $probeSanitized = SabreCancelProbeDiagnostics::cancelProbeSliceFromDigest($diag);

        SupplierBookingAttempt::query()->create([
            'agency_id' => $booking->agency_id,
            'booking_id' => $booking->id,
            'supplier_connection_id' => $connection->id,
            'provider' => SupplierProvider::Sabre->value,
            'action' => 'cancel_booking',
            'status' => $verified ? 'success' : ($httpStatus >= 200 && $httpStatus < 300 ? 'attempted' : 'failed'),
            'error_code' => is_string($httpResult['error_code'] ?? null) ? $httpResult['error_code'] : null,
            'error_message' => null,
            'supplier_reference' => null,
            'request_payload' => null,
            'response_payload' => null,
            'safe_summary' => SensitiveDataRedactor::redact([
                'source' => 'sabre_booking_cancel_workflow',
                'safe_summary_category' => $category,
                'endpoint_path' => $diag['endpoint_path'] ?? config('suppliers.sabre.cancel_endpoint_path'),
                'payload_style' => $selected['style'],
                'primary_identifier_source' => $selected['primary_identifier_source'],
                'http_status' => (string) $httpStatus,
                'supplier_cancel_verified' => $verified,
                'live_call_attempted' => true,
                'ticketing_disabled' => true,
                'response_error_codes' => array_slice(
                    array_map('strval', is_array($diag['response_error_codes'] ?? null) ? $diag['response_error_codes'] : []),
                    0,
                    12,
                ),
                'response_error_messages' => array_slice(
                    array_map('strval', is_array($diag['response_error_messages'] ?? null) ? $diag['response_error_messages'] : []),
                    0,
                    8,
                ),
                'response_error_details_sanitized' => $probeSanitized['response_error_details_sanitized'],
                'validation_missing_fields_sanitized' => $probeSanitized['validation_missing_fields_sanitized'],
            ]),
            'attempted_at' => now(),
            'completed_at' => now(),
        ]);
    }

    /**
     * @param  array<string, mixed>  $httpResult
     * @return array<string, mixed>
     */
    protected function safeCancelProbeSummary(array $httpResult): array
    {
        $diag = is_array($httpResult['booking_diagnostics'] ?? null) ? $httpResult['booking_diagnostics'] : [];
        $sanitized = SabreCancelProbeDiagnostics::cancelProbeSliceFromDigest($diag);

        return [
            'success' => (bool) ($httpResult['success'] ?? false),
            'http_status' => $httpResult['http_status'] ?? null,
            'error_code' => $httpResult['error_code'] ?? null,
            'response_error_codes' => array_slice(
                array_map('strval', is_array($diag['response_error_codes'] ?? null) ? $diag['response_error_codes'] : []),
                0,
                12,
            ),
            'response_error_details_sanitized' => $sanitized['response_error_details_sanitized'],
        ];
    }

    /**
     * @param  array<string, mixed>  $meta
     */
    protected function resolveBookingConnection(Booking $booking, array $meta): ?SupplierConnection
    {
        $cid = data_get($meta, 'supplier_connection_id');
        $cid = is_numeric($cid) ? (int) $cid : 0;
        if ($cid <= 0) {
            return null;
        }

        $connection = SupplierConnection::query()->find($cid);
        if ($connection === null || $connection->provider !== SupplierProvider::Sabre) {
            return null;
        }

        return $connection;
    }

    /**
     * @param  array<string, mixed>  $meta
     */
    protected function resolvePnr(Booking $booking, array $meta): string
    {
        foreach ([
            $booking->pnr,
            $booking->supplier_reference,
            data_get($meta, 'sabre_provider_snapshot.pnr'),
            data_get($meta, 'pnr'),
        ] as $candidate) {
            if (is_string($candidate) && trim($candidate) !== '') {
                return strtoupper(substr(trim($candidate), 0, 32));
            }
        }

        return '';
    }

    /**
     * @param  array<string, mixed>  $meta
     */
    protected function resolveSupplierApiBookingId(Booking $booking, array $meta): ?string
    {
        foreach ([
            $booking->supplier_api_booking_id,
            data_get($meta, 'sabre_provider_snapshot.supplier_api_booking_id'),
        ] as $candidate) {
            if (is_string($candidate) && trim($candidate) !== '') {
                return trim($candidate);
            }
        }

        return null;
    }

    /**
     * @param  array<string, mixed>  $meta
     */
    protected function resolveSupplierReference(Booking $booking, array $meta): ?string
    {
        foreach ([
            $booking->supplier_reference,
            data_get($meta, 'sabre_provider_snapshot.supplier_reference'),
        ] as $candidate) {
            if (is_string($candidate) && trim($candidate) !== '') {
                return trim($candidate);
            }
        }

        return null;
    }

    protected function isLocallyTicketed(Booking $booking): bool
    {
        if ($booking->ticketed_at !== null) {
            return true;
        }
        if ($booking->status === BookingStatus::Ticketed) {
            return true;
        }

        return in_array((string) ($booking->ticketing_status ?? ''), ['ticketed', 'issued'], true);
    }

    protected function isLocallyCancelled(Booking $booking): bool
    {
        if ($booking->status === BookingStatus::Cancelled) {
            return true;
        }

        return $booking->cancelled_at !== null;
    }
}
