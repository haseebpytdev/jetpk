<?php

namespace App\Services\Suppliers\Sabre\Cancel;

use App\Enums\BookingStatus;
use App\Enums\SupplierProvider;
use App\Models\Booking;
use App\Models\SupplierBookingAttempt;
use App\Models\SupplierConnection;
use App\Services\Suppliers\Sabre\Booking\SabreBookingService;
use App\Services\Suppliers\Sabre\Core\SabreBookingClient;
use App\Services\Suppliers\Sabre\Core\SabreClient;
use App\Services\Suppliers\Sabre\PnrRetrieve\SabrePnrRetrieveProbe;
use App\Services\Suppliers\Sabre\PnrRetrieve\SabreTripOrdersGetBookingInspectSummary;
use App\Support\Security\SensitiveDataRedactor;

/**
 * Sprint 0 / Phase 3G-Cancel: Inspect-only Sabre cancelBooking certification probe (Artisan dry-run in any APP_ENV).
 * Default dry-run; live cancel requires explicit env flags + host-specific --confirm. Never updates booking.status.
 * {@see inspectDirectPnr()} — CERT direct --pnr cleanup without a Booking row (no SupplierBookingAttempt writes).
 */
final class SabreCancelBookingInspectProbe
{
    public const CONFIRM_PHRASE = 'CANCEL-CERT-PNR';

    public const CONFIRM_PHRASE_CERT = 'CANCEL-CERT-PNR';

    public const CONFIRM_PHRASE_PRODUCTION = 'CANCEL-LIVE-PROD-PNR';

    public const PRODUCTION_BASE_URL_HOST = 'api.platform.sabre.com';

    public function __construct(
        protected SabreCancelPayloadBuilder $payloadBuilder,
        protected SabreBookingClient $bookingClient,
        protected SabrePnrRetrieveProbe $pnrRetrieveProbe,
    ) {}

    public static function isCancelEnabled(): bool
    {
        return (bool) config('suppliers.sabre.cancel_enabled', false);
    }

    public static function isCancelLiveCallEnabled(): bool
    {
        return (bool) config('suppliers.sabre.cancel_live_call_enabled', false);
    }

    public static function isCancelConfirmationRequired(): bool
    {
        return (bool) config('suppliers.sabre.cancel_require_confirmation', true);
    }

    public static function isCancelProductionSendAllowed(): bool
    {
        return (bool) config('suppliers.sabre.cancel_allow_production_send', false);
    }

    public static function isCancelProductionHostAllowed(): bool
    {
        return (bool) config('suppliers.sabre.cancel_allow_production_host', false);
    }

    public static function mayPerformLiveSabreCancelCall(): bool
    {
        return self::isCancelEnabled() && self::isCancelLiveCallEnabled();
    }

    /**
     * @return array<string, mixed>
     */
    public function inspect(
        Booking $booking,
        bool $send,
        ?string $confirmPhrase,
        bool $preGetBooking,
        ?string $payloadStyleOverride = null,
        bool $withPnrSnapshot = false,
        bool $refreshTripOrderContext = false,
    ): array {
        $meta = is_array($booking->meta) ? $booking->meta : [];
        $provider = strtolower(trim((string) ($meta['supplier_provider'] ?? $booking->supplier ?? '')));
        $pnr = $this->resolvePnr($booking, $meta);
        $supplierApiBookingId = $this->resolveSupplierApiBookingId($booking, $meta);
        $supplierReference = $this->resolveSupplierReference($booking, $meta);

        $base = [
            'booking_id' => $booking->id,
            'provider' => $provider,
            'mode' => $send ? 'live_probe' : 'dry_run',
            'live_call_attempted' => false,
            'booking_status_updated' => false,
            'ticketing_disabled' => true,
        ];

        if ($provider !== SupplierProvider::Sabre->value) {
            return SensitiveDataRedactor::redact(array_merge($base, [
                'error' => 'booking_not_sabre',
            ]));
        }

        if ($pnr === '') {
            return SensitiveDataRedactor::redact(array_merge($base, [
                'error' => 'booking_missing_pnr',
            ]));
        }

        if ($this->isLocallyCancelled($booking)) {
            return SensitiveDataRedactor::redact(array_merge($base, [
                'error' => 'booking_already_cancelled_locally',
                'pnr_present' => true,
            ]));
        }

        if ($this->isTicketed($booking)) {
            return SensitiveDataRedactor::redact(array_merge($base, [
                'error' => 'booking_ticketed_blocked',
                'pnr_present' => true,
            ]));
        }

        $connection = $this->resolveConnection($meta);
        if ($connection === null) {
            return SensitiveDataRedactor::redact(array_merge($base, [
                'error' => 'sabre_connection_missing',
                'pnr_present' => true,
            ]));
        }

        $endpointPath = (string) config('suppliers.sabre.cancel_endpoint_path', '/v1/trip/orders/cancelBooking');
        $parts = app(SabreClient::class)->resolveEndpointParts($connection, $endpointPath);
        $resolvedBaseUrl = rtrim((string) ($connection->base_url ?: config('suppliers.sabre.default_base_url')), '/');
        $certBaseConfirmed = self::isCertificationBaseUrl($resolvedBaseUrl);
        $productionHostConfirmed = self::isProductionBaseUrl($resolvedBaseUrl);
        $baseUrlHost = (string) ($parts['endpoint_host'] ?? 'unknown');

        $identifiers = [
            'pnr_present' => $pnr !== '',
            'supplier_api_booking_id_present' => $supplierApiBookingId !== null,
            'supplier_reference_present' => $supplierReference !== null,
            'primary_identifier_source' => $this->resolvePrimaryIdentifierSource($supplierApiBookingId, $supplierReference, $pnr),
            'primary_identifier_field' => 'confirmationId',
        ];

        $styleOverride = is_string($payloadStyleOverride) ? trim($payloadStyleOverride) : '';
        $liveRefreshForSend = $send && $refreshTripOrderContext;

        if ($send && $styleOverride !== '' && SabreCancelPayloadBuilder::isDryRunOnlyWrapperStyle($styleOverride)) {
            return SensitiveDataRedactor::redact(array_merge($base, [
                'pnr_present' => true,
                'style_explicitly_selected' => true,
                'selected_payload_style' => $styleOverride,
                'error' => 'cancel_payload_style_dry_run_only',
            ]));
        }

        if ($liveRefreshForSend) {
            $refreshGateError = $this->liveRefreshTripOrderContextGateError($styleOverride);
            if ($refreshGateError !== null) {
                return SensitiveDataRedactor::redact(array_merge($base, [
                    'pnr_present' => true,
                    'trip_order_context_refreshed_for_live_send' => false,
                    'style_explicitly_selected' => $styleOverride !== '',
                    'selected_payload_style' => $styleOverride !== '' ? $styleOverride : null,
                    'error' => $refreshGateError,
                ]));
            }
        }

        $tripOrderResolution = $this->resolveTripOrderContextWithOptionalJson(
            $booking,
            $withPnrSnapshot,
            $refreshTripOrderContext,
            ! $send,
        );
        $tripOrderContext = $tripOrderResolution['context'];
        $refreshedGetBookingJson = $tripOrderResolution['json'];

        if ($liveRefreshForSend) {
            $tripOrderGateError = $this->liveRefreshTripOrderContextPostFetchGateError(
                $tripOrderContext,
                $styleOverride,
            );
            if ($tripOrderGateError !== null) {
                $cancelContext = SabreCancelBookingContext::fromBooking($booking, $withPnrSnapshot, $tripOrderContext);

                return SensitiveDataRedactor::redact(array_merge($base, [
                    'pnr_present' => true,
                    'trip_order_context_refreshed_for_live_send' => true,
                    'style_explicitly_selected' => true,
                    'selected_payload_style' => $styleOverride,
                    'cancel_diagnostics' => $cancelContext->diagnosticsSlice(),
                    'error' => $tripOrderGateError,
                ] + $tripOrderContext->safePublicSlice()));
            }
        }

        $cancelContext = SabreCancelBookingContext::fromBooking($booking, $withPnrSnapshot, $tripOrderContext);
        $getBookingInventory = ($withPnrSnapshot || $refreshTripOrderContext)
            ? $this->resolveGetBookingCancelSchemaInventory(
                $booking,
                $refreshTripOrderContext,
                $withPnrSnapshot,
                $refreshedGetBookingJson,
            )
            : null;
        $equivalenceAnalysis = null;
        $candidates = $this->payloadBuilder->buildCandidatePayloads(
            $pnr,
            $supplierApiBookingId,
            $supplierReference,
            $cancelContext,
            $equivalenceAnalysis,
        );
        $candidatePreview = $this->candidatePreviewRows($candidates, (string) ($parts['endpoint_path'] ?? $endpointPath));
        $selected = $this->selectCandidate($candidates, $styleOverride !== '' ? $styleOverride : null);
        $gates = $this->liveSendGates($send, $confirmPhrase, $certBaseConfirmed, $productionHostConfirmed, $baseUrlHost);

        $selectedStyle = $selected['style'] ?? null;
        $selectedPreviouslyFailed = is_string($selectedStyle) && $cancelContext->stylePreviouslyFailed($selectedStyle);
        $selectedStyleWarning = $this->selectedStyleWarning($selected, $cancelContext);
        $recommendedStyle = $this->resolveRecommendedStyle($candidates, $cancelContext, $equivalenceAnalysis);
        $nextAction = SabreCancelProbeDiagnostics::resolveNextActionRecommendation(
            $cancelContext,
            $recommendedStyle,
            $equivalenceAnalysis,
        );

        $payload = array_merge($base, [
            'pnr_present' => true,
            'trip_order_context_refreshed_for_live_send' => $liveRefreshForSend,
            'identifiers' => $identifiers,
            'endpoint' => [
                'base_url_host' => $baseUrlHost,
                'endpoint_path' => $parts['endpoint_path'] ?? $endpointPath,
                'full_url_redacted' => $baseUrlHost.($parts['endpoint_path'] ?? $endpointPath),
                'cert_base_url_confirmed' => $certBaseConfirmed,
                'production_host_confirmed' => $productionHostConfirmed,
            ],
            'config_flags' => [
                'cancel_enabled' => self::isCancelEnabled(),
                'cancel_live_call_enabled' => self::isCancelLiveCallEnabled(),
                'cancel_require_confirmation' => self::isCancelConfirmationRequired(),
                'cancel_allow_production_send' => self::isCancelProductionSendAllowed(),
                'cancel_allow_production_host' => self::isCancelProductionHostAllowed(),
                'cancel_payload_style' => SabreCancelPayloadBuilder::configuredPayloadStyle(),
                'allowed_cancel_payload_styles' => SabreCancelPayloadBuilder::allowedConfiguredPayloadStyles(),
                'app_env' => (string) config('app.env', 'production'),
                'may_perform_live_cancel_call' => self::mayPerformLiveSabreCancelCall(),
            ],
            'live_send_gates' => $gates,
            'cancel_diagnostics' => $cancelContext->diagnosticsSlice(),
            'candidate_payloads' => $candidatePreview,
            'selected_payload_style' => $selectedStyle,
            'style_explicitly_selected' => $styleOverride !== '',
            'selected_style_previously_failed' => $selectedPreviouslyFailed,
            'selected_style_warning' => $selectedStyleWarning,
            'recommended_payload_style' => $recommendedStyle,
            'next_action_recommendation' => $nextAction,
            'cancel_http_conversation_id_will_send' => true,
        ] + $this->selectedPayloadDiagnosticsSlice($selected) + $this->equivalenceDiagnosticsSlice($equivalenceAnalysis) + $tripOrderContext->safePublicSlice() + $this->cancelSchemaDiagnosticSlice(
            $getBookingInventory,
            $cancelContext,
            $certBaseConfirmed,
            $productionHostConfirmed,
            $parts['endpoint_path'] ?? $endpointPath,
        ));

        if ($styleOverride !== '' && $selected === []) {
            return SensitiveDataRedactor::redact(array_merge($payload, [
                'error' => 'unknown_cancel_payload_style',
                'unknown_style' => $styleOverride,
                'known_styles_for_booking' => array_values(array_map(
                    fn (array $row): string => (string) ($row['style'] ?? ''),
                    $candidates,
                )),
            ]));
        }

        if (! $send) {
            return SensitiveDataRedactor::redact($payload);
        }

        if (($gates['allowed'] ?? false) !== true) {
            return SensitiveDataRedactor::redact(array_merge($payload, [
                'error' => (string) ($gates['block_reason'] ?? 'live_send_blocked'),
            ]));
        }

        if ($selected === []) {
            return SensitiveDataRedactor::redact(array_merge($payload, [
                'error' => 'no_cancel_payload_candidate',
            ]));
        }

        $dryRunOnlyStyleError = $this->dryRunOnlyWrapperStyleGateError($selectedStyle);
        if ($dryRunOnlyStyleError !== null) {
            return SensitiveDataRedactor::redact(array_merge($payload, [
                'error' => $dryRunOnlyStyleError,
            ]));
        }

        $preGetSummary = null;
        if ($preGetBooking && ! $liveRefreshForSend) {
            $pre = $this->pnrRetrieveProbe->fetchTripOrdersGetBooking($booking);
            unset($pre['json']);
            $preGetSummary = SensitiveDataRedactor::redact($pre);
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

        $payload['live_call_attempted'] = (bool) ($httpResult['live_call_attempted'] ?? false);
        $payload['cancel_probe'] = $this->safeCancelProbeSummary($httpResult);
        if ($preGetSummary !== null) {
            $payload['pre_get_booking'] = $preGetSummary;
        }
        if ($payload['live_call_attempted'] === true) {
            $postCancel = $this->postCancelVerificationForBooking($booking);
            $payload['post_cancel_get_booking'] = $postCancel;
            $payload['cancel_outcome_classification'] = $this->classifyCancelOutcome($httpResult, $postCancel);
        }

        if ($payload['live_call_attempted'] === true) {
            $this->recordProbeAttempt($booking, $connection, $selected, $httpResult);
        }

        return SensitiveDataRedactor::redact($payload);
    }

    /**
     * Phase 3G-Cancel: Direct record-locator cancel inspect (no Booking row, no SupplierBookingAttempt writes).
     *
     * @return array<string, mixed>
     */
    public function inspectDirectPnr(
        SupplierConnection $connection,
        string $pnr,
        bool $send,
        ?string $confirmPhrase,
        ?string $payloadStyleOverride = null,
        bool $refreshTripOrderContext = false,
    ): array {
        $pnr = strtoupper(trim($pnr));
        $base = [
            'probe_mode' => 'direct_pnr',
            'connection_id' => $connection->id,
            'provider' => SupplierProvider::Sabre->value,
            'mode' => $send ? 'live_probe' : 'dry_run',
            'live_call_attempted' => false,
            'booking_status_updated' => false,
            'ticketing_disabled' => true,
            'supplier_booking_attempt_recorded' => false,
        ];

        if ($connection->provider !== SupplierProvider::Sabre) {
            return SensitiveDataRedactor::redact(array_merge($base, [
                'error' => 'connection_not_sabre',
            ]));
        }

        $endpointPath = (string) config('suppliers.sabre.cancel_endpoint_path', '/v1/trip/orders/cancelBooking');
        $parts = app(SabreClient::class)->resolveEndpointParts($connection, $endpointPath);
        $resolvedBaseUrl = rtrim((string) ($connection->base_url ?: config('suppliers.sabre.default_base_url')), '/');
        $certBaseConfirmed = self::isCertificationBaseUrl($resolvedBaseUrl);
        $productionHostConfirmed = self::isProductionBaseUrl($resolvedBaseUrl);
        $baseUrlHost = (string) ($parts['endpoint_host'] ?? 'unknown');

        if ($productionHostConfirmed) {
            return SensitiveDataRedactor::redact(array_merge($base, [
                'pnr_present' => true,
                'error' => 'direct_pnr_production_host_blocked',
            ]));
        }

        if (! $certBaseConfirmed) {
            return SensitiveDataRedactor::redact(array_merge($base, [
                'pnr_present' => true,
                'error' => 'direct_pnr_requires_cert_host',
            ]));
        }

        $styleOverride = is_string($payloadStyleOverride) ? trim($payloadStyleOverride) : '';
        $liveRefreshForSend = $send && $refreshTripOrderContext;

        if ($send && $styleOverride !== '') {
            $dryRunOnlyStyleError = $this->dryRunOnlyWrapperStyleGateError($styleOverride, true);
            if ($dryRunOnlyStyleError !== null) {
                return SensitiveDataRedactor::redact(array_merge($base, [
                    'pnr_present' => true,
                    'style_explicitly_selected' => true,
                    'selected_payload_style' => $styleOverride,
                    'error' => $dryRunOnlyStyleError,
                ]));
            }
        }

        if ($send && ! $refreshTripOrderContext) {
            return SensitiveDataRedactor::redact(array_merge($base, [
                'pnr_present' => true,
                'error' => 'direct_pnr_live_send_requires_refresh_trip_order_context',
            ]));
        }

        if ($send && $styleOverride === '') {
            return SensitiveDataRedactor::redact(array_merge($base, [
                'pnr_present' => true,
                'style_explicitly_selected' => false,
                'selected_payload_style' => null,
                'error' => 'direct_pnr_live_send_requires_explicit_booking_id_style',
            ]));
        }

        $tripOrderResolution = $this->resolveDirectTripOrderContext(
            $connection,
            $pnr,
            $refreshTripOrderContext,
        );
        $tripOrderContext = $tripOrderResolution['context'];
        $directCancelSafety = $tripOrderResolution['safety'];

        if ($refreshTripOrderContext && isset($tripOrderResolution['fetch_error'])) {
            return SensitiveDataRedactor::redact(array_merge($base, [
                'pnr_present' => true,
                'error' => (string) $tripOrderResolution['fetch_error'],
            ] + $tripOrderContext->safePublicSlice()));
        }

        if ($liveRefreshForSend) {
            $directLiveStyleGateError = $this->directPnrLiveSendStyleGateError($styleOverride, $tripOrderContext);
            if ($directLiveStyleGateError !== null) {
                $cancelContext = SabreCancelBookingContext::fromDirectPnr($tripOrderContext);

                return SensitiveDataRedactor::redact(array_merge($base, [
                    'pnr_present' => true,
                    'trip_order_context_refreshed_for_live_send' => true,
                    'style_explicitly_selected' => true,
                    'selected_payload_style' => $styleOverride,
                    'direct_cancel_safety' => $directCancelSafety,
                    'cancel_diagnostics' => $cancelContext->diagnosticsSlice(),
                    'error' => $directLiveStyleGateError,
                ] + $tripOrderContext->safePublicSlice()));
            }

            $tripOrderGateError = $this->liveRefreshTripOrderContextPostFetchGateError(
                $tripOrderContext,
                $styleOverride,
            );
            if ($tripOrderGateError !== null) {
                $cancelContext = SabreCancelBookingContext::fromDirectPnr($tripOrderContext);

                return SensitiveDataRedactor::redact(array_merge($base, [
                    'pnr_present' => true,
                    'trip_order_context_refreshed_for_live_send' => true,
                    'style_explicitly_selected' => true,
                    'selected_payload_style' => $styleOverride,
                    'direct_cancel_safety' => $directCancelSafety,
                    'cancel_diagnostics' => $cancelContext->diagnosticsSlice(),
                    'error' => $tripOrderGateError,
                ] + $tripOrderContext->safePublicSlice()));
            }

            $directSafetyGateError = $this->directPnrCancelSafetyGateError($directCancelSafety, $styleOverride);
            if ($directSafetyGateError !== null) {
                $cancelContext = SabreCancelBookingContext::fromDirectPnr($tripOrderContext);

                return SensitiveDataRedactor::redact(array_merge($base, [
                    'pnr_present' => true,
                    'trip_order_context_refreshed_for_live_send' => true,
                    'style_explicitly_selected' => true,
                    'selected_payload_style' => $styleOverride,
                    'direct_cancel_safety' => $directCancelSafety,
                    'cancel_diagnostics' => $cancelContext->diagnosticsSlice(),
                    'error' => $directSafetyGateError,
                ] + $tripOrderContext->safePublicSlice()));
            }
        }

        $cancelContext = SabreCancelBookingContext::fromDirectPnr($tripOrderContext);
        $equivalenceAnalysis = null;
        $candidates = $this->payloadBuilder->buildCandidatePayloads(
            $pnr,
            null,
            null,
            $cancelContext,
            $equivalenceAnalysis,
        );
        $candidatePreview = $this->candidatePreviewRows($candidates, (string) ($parts['endpoint_path'] ?? $endpointPath));
        $selected = $this->selectCandidate($candidates, $styleOverride !== '' ? $styleOverride : null);
        $gates = $this->liveSendGates($send, $confirmPhrase, $certBaseConfirmed, $productionHostConfirmed, $baseUrlHost);

        $selectedStyle = $selected['style'] ?? null;
        $recommendedStyle = $this->resolveRecommendedStyle($candidates, $cancelContext, $equivalenceAnalysis);
        $nextAction = SabreCancelProbeDiagnostics::resolveNextActionRecommendation(
            $cancelContext,
            $recommendedStyle,
            $equivalenceAnalysis,
        );

        $payload = array_merge($base, [
            'pnr_present' => true,
            'trip_order_context_refreshed_for_live_send' => $liveRefreshForSend,
            'identifiers' => [
                'pnr_present' => true,
                'supplier_api_booking_id_present' => false,
                'supplier_reference_present' => false,
                'primary_identifier_source' => 'pnr',
                'primary_identifier_field' => 'confirmationId',
            ],
            'endpoint' => [
                'base_url_host' => $baseUrlHost,
                'endpoint_path' => $parts['endpoint_path'] ?? $endpointPath,
                'full_url_redacted' => $baseUrlHost.($parts['endpoint_path'] ?? $endpointPath),
                'cert_base_url_confirmed' => $certBaseConfirmed,
                'production_host_confirmed' => $productionHostConfirmed,
            ],
            'config_flags' => [
                'cancel_enabled' => self::isCancelEnabled(),
                'cancel_live_call_enabled' => self::isCancelLiveCallEnabled(),
                'cancel_require_confirmation' => self::isCancelConfirmationRequired(),
                'cancel_allow_production_send' => self::isCancelProductionSendAllowed(),
                'cancel_allow_production_host' => self::isCancelProductionHostAllowed(),
                'cancel_payload_style' => SabreCancelPayloadBuilder::configuredPayloadStyle(),
                'allowed_cancel_payload_styles' => SabreCancelPayloadBuilder::allowedConfiguredPayloadStyles(),
                'app_env' => (string) config('app.env', 'production'),
                'may_perform_live_cancel_call' => self::mayPerformLiveSabreCancelCall(),
            ],
            'live_send_gates' => $gates,
            'direct_cancel_safety' => $directCancelSafety,
            'cancel_diagnostics' => $cancelContext->diagnosticsSlice(),
            'candidate_payloads' => $candidatePreview,
            'selected_payload_style' => $selectedStyle,
            'style_explicitly_selected' => $styleOverride !== '',
            'selected_style_previously_failed' => false,
            'selected_style_warning' => null,
            'recommended_payload_style' => $recommendedStyle,
            'next_action_recommendation' => $nextAction,
            'cancel_http_conversation_id_will_send' => true,
        ] + $this->selectedPayloadDiagnosticsSlice($selected) + $this->equivalenceDiagnosticsSlice($equivalenceAnalysis) + $tripOrderContext->safePublicSlice());

        if ($styleOverride !== '' && $selected === []) {
            return SensitiveDataRedactor::redact(array_merge($payload, [
                'error' => 'unknown_cancel_payload_style',
                'unknown_style' => $styleOverride,
                'known_styles_for_pnr' => array_values(array_map(
                    fn (array $row): string => (string) ($row['style'] ?? ''),
                    $candidates,
                )),
            ]));
        }

        if (! $send) {
            return SensitiveDataRedactor::redact($payload);
        }

        if (($gates['allowed'] ?? false) !== true) {
            return SensitiveDataRedactor::redact(array_merge($payload, [
                'error' => (string) ($gates['block_reason'] ?? 'live_send_blocked'),
            ]));
        }

        if ($selected === []) {
            return SensitiveDataRedactor::redact(array_merge($payload, [
                'error' => 'no_cancel_payload_candidate',
            ]));
        }

        $dryRunOnlyStyleError = $this->dryRunOnlyWrapperStyleGateError($selectedStyle, true);
        if ($dryRunOnlyStyleError !== null) {
            return SensitiveDataRedactor::redact(array_merge($payload, [
                'error' => $dryRunOnlyStyleError,
            ]));
        }

        $httpResult = $this->bookingClient->inspectCancelBooking(
            $connection,
            $selected['body'],
            [
                'booking_id' => null,
                'supplier_connection_id' => $connection->id,
                'payload_style' => $selected['style'],
                'primary_identifier_source' => $selected['primary_identifier_source'],
            ],
        );

        $payload['live_call_attempted'] = (bool) ($httpResult['live_call_attempted'] ?? false);
        $payload['cancel_probe'] = $this->safeCancelProbeSummary($httpResult);
        if ($payload['live_call_attempted'] === true) {
            $postCancel = $this->postCancelVerificationForDirectPnr($connection, $pnr);
            $payload['post_cancel_get_booking'] = $postCancel;
            $payload['cancel_outcome_classification'] = $this->classifyCancelOutcome($httpResult, $postCancel);
        }

        return SensitiveDataRedactor::redact($payload);
    }

    /**
     * @return array{
     *   context: SabreTripOrderCancelContext,
     *   safety: ?array<string, mixed>,
     *   fetch_error?: string
     * }
     */
    protected function resolveDirectTripOrderContext(
        SupplierConnection $connection,
        string $pnr,
        bool $refreshTripOrderContext,
    ): array {
        if (! $refreshTripOrderContext) {
            return [
                'context' => SabreTripOrderCancelContext::unavailable(),
                'safety' => null,
            ];
        }

        $inspectSummary = app(SabreTripOrdersGetBookingInspectSummary::class);
        $fetch = $this->pnrRetrieveProbe->fetchTripOrdersGetBookingDirect($connection, $pnr);
        if (isset($fetch['error'])) {
            return [
                'context' => SabreTripOrderCancelContext::unavailable(),
                'safety' => null,
                'fetch_error' => (string) $fetch['error'],
            ];
        }

        $json = is_array($fetch['json'] ?? null) ? $fetch['json'] : [];
        if ($json === []) {
            return [
                'context' => SabreTripOrderCancelContext::unavailable(),
                'safety' => null,
                'fetch_error' => 'trip_order_get_booking_empty',
            ];
        }

        $context = SabreTripOrderCancelContext::fromGetBookingJson($inspectSummary, $json);

        $safety = $inspectSummary->extractDirectCancelSafetyFlags($json);
        unset($json);

        return [
            'context' => $context,
            'safety' => $safety,
        ];
    }

    /**
     * @param  ?array<string, mixed>  $safety
     */
    protected function directPnrLiveSendStyleGateError(
        string $styleOverride,
        SabreTripOrderCancelContext $tripOrderContext,
    ): ?string {
        if ($this->isDirectPnrCertAllowedCancelBookingRequestStyle($styleOverride)) {
            if (! $tripOrderContext->hasBookingSignature()) {
                return 'trip_order_booking_signature_missing';
            }

            return null;
        }

        if (SabreCancelPayloadBuilder::isDryRunOnlyWrapperStyle($styleOverride)) {
            return 'cancel_payload_style_dry_run_only';
        }

        if (SabreCancelPayloadBuilder::isConfirmationOnlyFullCancelStyle($styleOverride)) {
            return null;
        }

        $allowedWithoutSignature = [
            SabreCancelPayloadBuilder::STYLE_TRIP_ORDERS_BOOKING_ID_CANCEL_ALL_ROOT,
        ];
        if (in_array($styleOverride, $allowedWithoutSignature, true)) {
            return null;
        }

        $signatureStyles = [
            SabreCancelPayloadBuilder::STYLE_TRIP_ORDERS_BOOKING_ID_SIGNATURE_CANCEL_ALL,
            SabreCancelPayloadBuilder::STYLE_TRIP_ORDERS_BOOKING_ID_SIGNATURE_CANCEL_DATA,
            SabreCancelPayloadBuilder::STYLE_TRIP_ORDERS_BOOKING_ID_REQUEST_WRAPPED,
        ];
        if (in_array($styleOverride, $signatureStyles, true)) {
            if (! $tripOrderContext->hasBookingSignature()) {
                return 'trip_order_booking_signature_missing';
            }

            return null;
        }

        return 'direct_pnr_live_send_requires_allowed_booking_id_style';
    }

    protected function directPnrCancelSafetyGateError(?array $safety, string $styleOverride = ''): ?string
    {
        if (! is_array($safety)) {
            return 'direct_cancel_safety_unavailable';
        }

        if (($safety['is_ticketed'] ?? null) === true) {
            return 'trip_order_ticketed_blocked';
        }

        if (($safety['is_cancelable'] ?? null) === false) {
            return 'trip_order_not_cancelable';
        }

        if (($safety['is_cancelable'] ?? null) !== true) {
            return 'trip_order_cancelable_unknown';
        }

        if (($safety['ticket_numbers_present'] ?? false) === true) {
            return 'trip_order_ticket_numbers_present_blocked';
        }

        if (! SabreCancelPayloadBuilder::isConfirmationOnlyFullCancelStyle($styleOverride)
            && ($safety['booking_id_present'] ?? false) !== true) {
            return 'trip_order_booking_id_missing';
        }

        return null;
    }

    /**
     * @return array<string, mixed>
     */
    public function listStyles(
        Booking $booking,
        bool $withPnrSnapshot = false,
        bool $refreshTripOrderContext = false,
    ): array {
        $meta = is_array($booking->meta) ? $booking->meta : [];
        $provider = strtolower(trim((string) ($meta['supplier_provider'] ?? $booking->supplier ?? '')));
        $pnr = $this->resolvePnr($booking, $meta);

        if ($provider !== SupplierProvider::Sabre->value) {
            return ['error' => 'booking_not_sabre'];
        }
        if ($pnr === '') {
            return ['error' => 'booking_missing_pnr'];
        }

        $tripOrderContext = $this->resolveTripOrderContext(
            $booking,
            $withPnrSnapshot,
            $refreshTripOrderContext,
            true,
        );
        $cancelContext = SabreCancelBookingContext::fromBooking($booking, $withPnrSnapshot, $tripOrderContext);
        $equivalenceAnalysis = null;
        $candidates = $this->payloadBuilder->buildCandidatePayloads(
            $pnr,
            $this->resolveSupplierApiBookingId($booking, $meta),
            $this->resolveSupplierReference($booking, $meta),
            $cancelContext,
            $equivalenceAnalysis,
        );

        $recommendedStyle = $this->resolveRecommendedStyle($candidates, $cancelContext, $equivalenceAnalysis);
        $nextAction = SabreCancelProbeDiagnostics::resolveNextActionRecommendation(
            $cancelContext,
            $recommendedStyle,
            $equivalenceAnalysis,
        );
        $stopLiveProbing = $nextAction === SabreCancelProbeDiagnostics::NEXT_ACTION_STOP_LIVE_PROBING;
        $styles = [];
        foreach ($candidates as $row) {
            $style = (string) ($row['style'] ?? '');
            $entry = [
                'style' => $style,
                'recommended' => (bool) ($row['recommended'] ?? false),
                'previously_failed_reason' => $row['previously_failed_reason'] ?? null,
                'previously_ineffective_reason' => $row['previously_ineffective_reason'] ?? null,
                'duplicate_of_style' => $row['duplicate_of_style'] ?? null,
                'duplicate_of_failed_style' => (bool) ($row['duplicate_of_failed_style'] ?? false),
                'safe_shape_keys' => $row['safe_shape_keys'] ?? [],
                'primary_identifier_field' => (string) ($row['primary_identifier_field'] ?? ''),
                'required_snapshot_fields_present' => (bool) ($row['required_snapshot_fields_present'] ?? false),
                'required_trip_order_fields_present' => (bool) ($row['required_trip_order_fields_present'] ?? false),
            ];
            if (is_string($row['recommendation_suppressed_reason'] ?? null)) {
                $entry['recommendation_suppressed_reason'] = (string) $row['recommendation_suppressed_reason'];
            }
            $audit = SabreCancelProbeDiagnostics::officialShapeAuditForStyle($style, $cancelContext);
            if ($audit !== null) {
                $entry['official_shape_audit'] = $audit;
            }
            $styles[] = $entry;
        }

        return SensitiveDataRedactor::redact([
            'booking_id' => $booking->id,
            'mode' => 'list_styles',
            'styles' => $styles,
            'recommended_payload_style' => $recommendedStyle,
            'next_action_recommendation' => $nextAction,
            'recommended_style_blocked_reason' => $stopLiveProbing
                ? SabreCancelProbeDiagnostics::STOP_LIVE_PROBING_BLOCKED_REASON
                : ($equivalenceAnalysis['recommended_style_blocked_reason'] ?? null),
            'cancel_diagnostics' => $cancelContext->diagnosticsSlice(),
        ] + $this->equivalenceDiagnosticsSlice($equivalenceAnalysis));
    }

    /**
     * @return array<string, mixed>
     */
    public function supportPacket(
        Booking $booking,
        bool $withPnrSnapshot = false,
        bool $refreshTripOrderContext = false,
    ): array {
        $meta = is_array($booking->meta) ? $booking->meta : [];
        $provider = strtolower(trim((string) ($meta['supplier_provider'] ?? $booking->supplier ?? '')));
        if ($provider !== SupplierProvider::Sabre->value) {
            return ['error' => 'booking_not_sabre'];
        }
        if ($this->resolvePnr($booking, $meta) === '') {
            return ['error' => 'booking_missing_pnr'];
        }

        $connection = $this->resolveConnection($meta);
        $endpointPath = (string) config('suppliers.sabre.cancel_endpoint_path', '/v1/trip/orders/cancelBooking');
        $baseUrlHost = 'unknown';
        $certBaseConfirmed = false;
        $productionHostConfirmed = false;
        if ($connection !== null) {
            $parts = app(SabreClient::class)->resolveEndpointParts($connection, $endpointPath);
            $baseUrlHost = (string) ($parts['endpoint_host'] ?? 'unknown');
            $resolvedBaseUrl = rtrim((string) ($connection->base_url ?: config('suppliers.sabre.default_base_url')), '/');
            $certBaseConfirmed = self::isCertificationBaseUrl($resolvedBaseUrl);
            $productionHostConfirmed = self::isProductionBaseUrl($resolvedBaseUrl);
        }

        $tripOrderResolution = $this->resolveTripOrderContextWithOptionalJson(
            $booking,
            $withPnrSnapshot,
            $refreshTripOrderContext,
            true,
        );
        $tripOrderContext = $tripOrderResolution['context'];
        $getBookingInventory = $this->resolveGetBookingCancelSchemaInventory(
            $booking,
            $refreshTripOrderContext,
            $withPnrSnapshot,
            $tripOrderResolution['json'],
        );
        $cancelContext = SabreCancelBookingContext::fromBooking($booking, $withPnrSnapshot, $tripOrderContext);
        $metaForCandidates = is_array($booking->meta) ? $booking->meta : [];
        $equivalenceAnalysis = null;
        $candidates = $this->payloadBuilder->buildCandidatePayloads(
            $this->resolvePnr($booking, $metaForCandidates),
            $this->resolveSupplierApiBookingId($booking, $metaForCandidates),
            $this->resolveSupplierReference($booking, $metaForCandidates),
            $cancelContext,
            $equivalenceAnalysis,
        );
        $recommendedStyle = $this->resolveRecommendedStyle($candidates, $cancelContext, $equivalenceAnalysis);

        return SensitiveDataRedactor::redact(
            SabreCancelProbeDiagnostics::buildSupportPacket(
                $booking,
                $cancelContext,
                $baseUrlHost,
                $endpointPath,
                $equivalenceAnalysis,
                $recommendedStyle,
                $getBookingInventory,
                $certBaseConfirmed,
                $productionHostConfirmed,
            ),
        );
    }

    /**
     * F3C: Safe getBooking cancel-schema inventory from read-only refresh or cached sync meta (no raw values).
     *
     * @return array<string, mixed>|null
     */
    public function resolveGetBookingCancelSchemaInventory(
        Booking $booking,
        bool $refreshGetBooking,
        bool $withPnrSnapshot,
        ?array $prefetchedGetBookingJson = null,
    ): ?array {
        $inspectSummary = app(SabreTripOrdersGetBookingInspectSummary::class);

        if ($prefetchedGetBookingJson !== null && $prefetchedGetBookingJson !== []) {
            return $inspectSummary->buildCancelSchemaInventory($prefetchedGetBookingJson);
        }

        if ($refreshGetBooking) {
            $fetch = $this->pnrRetrieveProbe->fetchTripOrdersGetBooking($booking);
            $json = is_array($fetch['json'] ?? null) ? $fetch['json'] : [];
            if ($json !== [] && ! isset($fetch['error'])) {
                return $inspectSummary->buildCancelSchemaInventory($json);
            }
        }

        $meta = is_array($booking->meta) ? $booking->meta : [];
        $sync = is_array($meta['pnr_itinerary_sync'] ?? null) ? $meta['pnr_itinerary_sync'] : null;
        if ($sync !== null) {
            return $this->buildInventoryFromCachedCancelMeta($inspectSummary, $sync, $meta, $withPnrSnapshot);
        }

        if ($withPnrSnapshot) {
            return $this->buildInventoryFromCachedCancelMeta($inspectSummary, null, $meta, true);
        }

        return null;
    }

    /**
     * @param  array<string, mixed>|null  $syncSidecar
     * @param  array<string, mixed>  $meta
     * @return array<string, mixed>
     */
    protected function buildInventoryFromCachedCancelMeta(
        SabreTripOrdersGetBookingInspectSummary $inspectSummary,
        ?array $syncSidecar,
        array $meta,
        bool $includeSnapshot,
    ): array {
        $tripContext = is_array($meta['trip_order_cancel_context'] ?? null)
            ? $meta['trip_order_cancel_context']
            : [];
        $synthetic = [
            'bookingId' => $tripContext['bookingId'] ?? $tripContext['booking_id'] ?? null,
            'bookingSignature' => $tripContext['bookingSignature'] ?? $tripContext['booking_signature'] ?? null,
            'isCancelable' => $tripContext['isCancelable'] ?? $tripContext['is_cancelable'] ?? null,
            'isTicketed' => $tripContext['isTicketed'] ?? $tripContext['is_ticketed'] ?? null,
        ];
        if (is_array($syncSidecar)) {
            if (array_key_exists('is_cancelable', $syncSidecar)) {
                $synthetic['isCancelable'] = $syncSidecar['is_cancelable'];
            }
            if (array_key_exists('is_ticketed', $syncSidecar)) {
                $synthetic['isTicketed'] = $syncSidecar['is_ticketed'];
            }
            if (($syncSidecar['ticket_numbers_present'] ?? false) === true) {
                $synthetic['ticketNumbers'] = ['[redacted]'];
            }
        }
        if ($includeSnapshot) {
            $snapshot = is_array($meta['pnr_itinerary_snapshot'] ?? null) ? $meta['pnr_itinerary_snapshot'] : [];
            foreach (['orderId', 'orderItemIds', 'segmentIds', 'serviceItemIds', 'flights', 'segments'] as $k) {
                if (array_key_exists($k, $snapshot)) {
                    $synthetic[$k] = $snapshot[$k];
                }
            }
        }

        return $inspectSummary->buildCancelSchemaInventory($synthetic);
    }

    /**
     * @param  list<array<string, mixed>>  $candidates
     * @return list<array<string, mixed>>
     */
    protected function candidatePreviewRows(array $candidates, ?string $endpointPath = null): array
    {
        $candidatePreview = [];
        foreach ($candidates as $row) {
            $body = is_array($row['body'] ?? null) ? $row['body'] : [];
            $shape = $this->payloadBuilder->selectedPayloadDiagnostics($body);
            $candidatePreview[] = [
                'style' => $row['style'],
                'endpoint_path' => $endpointPath ?? (string) config('suppliers.sabre.cancel_endpoint_path', '/v1/trip/orders/cancelBooking'),
                'method' => 'POST',
                'primary_identifier_field' => $row['primary_identifier_field'],
                'primary_identifier_source' => $row['primary_identifier_source'],
                'recommended' => (bool) ($row['recommended'] ?? false),
                'configured_payload_style' => $row['configured_payload_style'] ?? null,
                'duplicate_of_style' => $row['duplicate_of_style'] ?? null,
                'duplicate_of_failed_style' => (bool) ($row['duplicate_of_failed_style'] ?? false),
                'safe_shape_keys' => $row['safe_shape_keys'] ?? $this->payloadBuilder->safeShapeKeys($row['body']),
                'has_confirmation_id' => (bool) ($shape['selected_payload_has_confirmation_id'] ?? false),
                'has_cancel_all' => (bool) ($shape['selected_payload_has_cancel_all'] ?? false),
                'has_retrieve_booking' => (bool) ($shape['selected_payload_has_retrieve_booking'] ?? false),
                'has_error_handling_policy' => (bool) ($shape['selected_payload_has_error_handling_policy'] ?? false),
                'has_booking_id' => (bool) ($shape['selected_payload_has_booking_id'] ?? false),
                'has_booking_signature' => (bool) ($shape['selected_payload_has_booking_signature'] ?? false),
                'has_order_item_ids' => (bool) ($shape['selected_payload_has_order_item_ids'] ?? false),
                'has_segment_ids' => (bool) ($shape['selected_payload_has_segment_ids'] ?? false),
                'dry_run_only' => SabreCancelPayloadBuilder::isDryRunOnlyWrapperStyle((string) ($row['style'] ?? '')),
                'suppressed_by_history' => ($row['previously_failed_reason'] ?? null) !== null
                    || ($row['previously_ineffective_reason'] ?? null) !== null
                    || ($row['duplicate_of_failed_style'] ?? false) === true,
                'required_snapshot_fields_present' => (bool) ($row['required_snapshot_fields_present'] ?? false),
                'required_trip_order_fields_present' => (bool) ($row['required_trip_order_fields_present'] ?? false),
                'why_candidate_exists' => (string) ($row['why_candidate_exists'] ?? ''),
                'previously_failed_reason' => $row['previously_failed_reason'] ?? null,
                'previously_ineffective_reason' => $row['previously_ineffective_reason'] ?? null,
                'request_body_redacted' => $this->payloadBuilder->redactBodyForPreview($row['body']),
            ];
        }

        return $candidatePreview;
    }

    /**
     * @param  array<string, mixed>|null  $getBookingInventory
     * @return array<string, mixed>
     */
    protected function cancelSchemaDiagnosticSlice(
        ?array $getBookingInventory,
        SabreCancelBookingContext $cancelContext,
        bool $certBaseConfirmed,
        bool $productionHostConfirmed,
        string $endpointPath,
    ): array {
        if (! is_array($getBookingInventory)) {
            return [];
        }

        $hostType = SabreCancelProbeDiagnostics::resolveHostTypeLabel(
            null,
            $certBaseConfirmed,
            $productionHostConfirmed,
        );
        $gapDiagnosis = SabreCancelProbeDiagnostics::inferCancelSchemaGapDiagnosis(
            $cancelContext,
            $getBookingInventory,
        );

        return [
            'endpoint_host_type' => $hostType,
            'get_booking_cancel_schema_inventory' => [
                'top_level_keys_sanitized' => $getBookingInventory['top_level_keys_sanitized'] ?? [],
                'cancel_safety_flags' => $getBookingInventory['cancel_safety_flags'] ?? null,
                'cancel_related_presence' => $getBookingInventory['cancel_related_presence'] ?? null,
                'possible_cancel_related_paths' => array_slice(
                    is_array($getBookingInventory['possible_cancel_related_paths'] ?? null)
                        ? $getBookingInventory['possible_cancel_related_paths']
                        : [],
                    0,
                    16,
                ),
            ],
            'cancel_schema_gap_diagnosis' => $gapDiagnosis,
            'sabre_escalation_note_template' => SabreCancelProbeDiagnostics::buildSabreEscalationNoteTemplate([
                'host_type' => $hostType,
                'endpoint_path' => $endpointPath,
                'gap_diagnosis' => $gapDiagnosis,
            ]),
        ];
    }

    /**
     * @param  array<string, mixed>|array{}  $selected
     * @return array<string, mixed>
     */
    protected function selectedPayloadDiagnosticsSlice(array $selected): array
    {
        if ($selected === [] || ! is_array($selected['body'] ?? null)) {
            return [];
        }

        return $this->payloadBuilder->selectedPayloadDiagnostics($selected['body']);
    }

    /**
     * @return array<string, mixed>
     */
    protected function equivalenceDiagnosticsSlice(?array $equivalenceAnalysis): array
    {
        if (! is_array($equivalenceAnalysis)) {
            return [
                'unique_payload_bodies_tested_count' => null,
                'unique_payload_bodies_failed_or_ineffective_count' => null,
                'duplicate_payload_styles' => [],
                'recommended_style_blocked_reason' => null,
            ];
        }

        return [
            'unique_payload_bodies_tested_count' => (int) ($equivalenceAnalysis['unique_payload_bodies_tested_count'] ?? 0),
            'unique_payload_bodies_failed_or_ineffective_count' => (int) ($equivalenceAnalysis['unique_payload_bodies_failed_or_ineffective_count'] ?? 0),
            'duplicate_payload_styles' => is_array($equivalenceAnalysis['duplicate_payload_styles'] ?? null)
                ? $equivalenceAnalysis['duplicate_payload_styles']
                : [],
            'recommended_style_blocked_reason' => is_array($equivalenceAnalysis)
                ? ($equivalenceAnalysis['recommended_style_blocked_reason'] ?? null)
                : null,
        ];
    }

    /**
     * @param  list<array<string, mixed>>  $candidates
     */
    protected function resolveRecommendedStyle(
        array $candidates,
        ?SabreCancelBookingContext $context = null,
        ?array $equivalenceAnalysis = null,
    ): ?string {
        foreach ($candidates as $row) {
            if (($row['recommended'] ?? false) === true) {
                $style = is_string($row['style'] ?? null) ? (string) $row['style'] : null;
                if ($context !== null
                    && SabreCancelProbeDiagnostics::shouldStopLiveProbing(
                        $context,
                        $equivalenceAnalysis,
                        $candidates,
                        $style,
                    )) {
                    return null;
                }

                return $style;
            }
        }

        if ($context !== null
            && SabreCancelProbeDiagnostics::shouldStopLiveProbing($context, $equivalenceAnalysis, $candidates, null)) {
            return null;
        }

        return null;
    }

    /**
     * @param  array<string, mixed>|array{}  $selected
     */
    protected function selectedStyleWarning(array $selected, SabreCancelBookingContext $context): ?string
    {
        $style = is_string($selected['style'] ?? null) ? (string) $selected['style'] : '';
        if ($style === '') {
            return null;
        }
        $ineffective = $context->previouslyIneffectiveReason($style);
        if ($ineffective !== null) {
            return 'Style '.$style.' previously returned HTTP 200 but getBooking verification suggests the booking remained active ('.$ineffective.').';
        }
        $reason = $context->previouslyFailedReason($style);
        if ($reason === null) {
            return null;
        }

        return 'Style '.$style.' previously failed with '.$reason.' on a prior probe attempt.';
    }

    protected function resolveTripOrderContext(
        Booking $booking,
        bool $withPnrSnapshot,
        bool $refreshTripOrderContext,
        bool $dryRun,
    ): SabreTripOrderCancelContext {
        return $this->resolveTripOrderContextWithOptionalJson(
            $booking,
            $withPnrSnapshot,
            $refreshTripOrderContext,
            $dryRun,
        )['context'];
    }

    /**
     * @return array{context: SabreTripOrderCancelContext, json: ?array<string, mixed>}
     */
    protected function resolveTripOrderContextWithOptionalJson(
        Booking $booking,
        bool $withPnrSnapshot,
        bool $refreshTripOrderContext,
        bool $dryRun,
    ): array {
        if (! $withPnrSnapshot && ! $refreshTripOrderContext) {
            return [
                'context' => SabreTripOrderCancelContext::unavailable(),
                'json' => null,
            ];
        }

        if ($refreshTripOrderContext) {
            $fetch = $this->pnrRetrieveProbe->fetchTripOrdersGetBooking($booking);
            if (isset($fetch['error'])) {
                return [
                    'context' => SabreTripOrderCancelContext::unavailable(),
                    'json' => null,
                ];
            }
            $json = is_array($fetch['json'] ?? null) ? $fetch['json'] : [];
            if ($json === []) {
                return [
                    'context' => SabreTripOrderCancelContext::unavailable(),
                    'json' => null,
                ];
            }

            return [
                'context' => SabreTripOrderCancelContext::fromGetBookingJson(
                    app(SabreTripOrdersGetBookingInspectSummary::class),
                    $json,
                ),
                'json' => $json,
            ];
        }

        return [
            'context' => SabreTripOrderCancelContext::resolve(
                $booking,
                $withPnrSnapshot,
                false,
                $dryRun,
                $this->pnrRetrieveProbe,
                app(SabreTripOrdersGetBookingInspectSummary::class),
            ),
            'json' => null,
        ];
    }

    protected function liveRefreshTripOrderContextGateError(string $styleOverride): ?string
    {
        if ($styleOverride === '') {
            return 'refresh_trip_order_context_live_send_requires_explicit_style';
        }

        if (SabreCancelPayloadBuilder::isDryRunOnlyWrapperStyle($styleOverride)) {
            return 'cancel_payload_style_dry_run_only';
        }

        if (SabreCancelPayloadBuilder::isConfirmationOnlyFullCancelStyle($styleOverride)) {
            return null;
        }

        if (! SabreCancelPayloadBuilder::isBookingIdBasedStyle($styleOverride)) {
            return 'refresh_trip_order_context_live_send_requires_booking_id_style';
        }

        return null;
    }

    protected function isDirectPnrCertAllowedCancelBookingRequestStyle(string $style): bool
    {
        return trim($style) === SabreCancelPayloadBuilder::STYLE_TRIP_ORDERS_CANCEL_BOOKING_REQUEST_BOOKING_ID_SIGNATURE_CANCEL_ALL;
    }

    protected function dryRunOnlyWrapperStyleGateError(?string $style, bool $directPnrLiveSend = false): ?string
    {
        if (! is_string($style) || $style === '') {
            return null;
        }

        if ($directPnrLiveSend && $this->isDirectPnrCertAllowedCancelBookingRequestStyle($style)) {
            return null;
        }

        if (SabreCancelPayloadBuilder::isDryRunOnlyWrapperStyle($style)) {
            return 'cancel_payload_style_dry_run_only';
        }

        return null;
    }

    protected function liveRefreshTripOrderContextPostFetchGateError(
        SabreTripOrderCancelContext $tripOrderContext,
        string $styleOverride,
    ): ?string {
        $confirmationOnlyFullCancel = SabreCancelPayloadBuilder::isConfirmationOnlyFullCancelStyle($styleOverride);

        if (! $confirmationOnlyFullCancel && ! $tripOrderContext->hasBookingId()) {
            return 'trip_order_booking_id_missing';
        }

        if ($tripOrderContext->isCancelable === false) {
            return 'trip_order_not_cancelable';
        }

        if (! $confirmationOnlyFullCancel
            && SabreCancelPayloadBuilder::styleRequiresTripOrderBookingSignature($styleOverride)
            && ! $tripOrderContext->hasBookingSignature()) {
            return 'trip_order_booking_signature_missing';
        }

        return null;
    }

    /**
     * @return array<string, mixed>
     */
    public function liveSendGates(
        bool $send,
        ?string $confirmPhrase,
        bool $certBaseConfirmed,
        bool $productionHostConfirmed,
        string $baseUrlHost,
    ): array {
        $appEnv = (string) config('app.env', 'production');
        $allowProductionSend = self::isCancelProductionSendAllowed();
        $allowProductionHost = self::isCancelProductionHostAllowed();

        $gateOutput = [
            'app_env' => $appEnv,
            'base_url_host' => $baseUrlHost,
            'cert_base_url_confirmed' => $certBaseConfirmed,
            'production_host_confirmed' => $productionHostConfirmed,
            'cancel_allow_production_send' => $allowProductionSend,
            'cancel_allow_production_host' => $allowProductionHost,
            'required_confirm_phrase' => null,
            'provided_confirm_phrase_valid' => false,
            'allowed' => false,
            'block_reason' => null,
        ];

        if (! $send) {
            return array_merge($gateOutput, [
                'block_reason' => 'dry_run_default',
            ]);
        }

        if (! self::isCancelEnabled()) {
            return array_merge($gateOutput, [
                'block_reason' => 'sabre_cancel_disabled',
            ]);
        }

        if (! self::isCancelLiveCallEnabled()) {
            return array_merge($gateOutput, [
                'block_reason' => 'sabre_cancel_live_call_disabled',
            ]);
        }

        $confirmTrim = trim((string) $confirmPhrase);
        $confirmationRequired = self::isCancelConfirmationRequired();

        if ($certBaseConfirmed) {
            $requiredToken = self::CONFIRM_PHRASE_CERT;
            $confirmValid = ! $confirmationRequired || $confirmTrim === $requiredToken;

            return array_merge($gateOutput, [
                'required_confirm_phrase' => $requiredToken,
                'provided_confirm_phrase_valid' => $confirmValid,
                'allowed' => $confirmValid,
                'block_reason' => $confirmValid ? null : 'confirm_phrase_required',
            ]);
        }

        if ($productionHostConfirmed) {
            $requiredToken = self::CONFIRM_PHRASE_PRODUCTION;
            $confirmValid = ! $confirmationRequired || $confirmTrim === $requiredToken;
            if ($confirmTrim === self::CONFIRM_PHRASE_CERT) {
                $confirmValid = false;
            }

            $productionSendOk = $appEnv !== 'production' || $allowProductionSend;

            $gateOutput = array_merge($gateOutput, [
                'required_confirm_phrase' => $requiredToken,
                'provided_confirm_phrase_valid' => $confirmValid,
            ]);

            if (! $allowProductionHost) {
                return array_merge($gateOutput, [
                    'block_reason' => 'production_host_not_allowed',
                ]);
            }

            if (! $productionSendOk) {
                return array_merge($gateOutput, [
                    'block_reason' => 'production_send_not_allowed',
                ]);
            }

            if (! $confirmValid) {
                return array_merge($gateOutput, [
                    'block_reason' => 'confirm_phrase_required',
                ]);
            }

            return array_merge($gateOutput, [
                'allowed' => true,
                'block_reason' => null,
            ]);
        }

        return array_merge($gateOutput, [
            'block_reason' => 'sabre_host_not_cert_or_production',
        ]);
    }

    public static function isCertificationBaseUrl(string $baseUrl): bool
    {
        $baseUrl = trim($baseUrl);
        if ($baseUrl === '') {
            return false;
        }
        if (! str_contains($baseUrl, '://')) {
            $baseUrl = 'https://'.$baseUrl;
        }
        $host = parse_url($baseUrl, PHP_URL_HOST);
        if (! is_string($host) || $host === '') {
            return false;
        }
        $host = strtolower($host);
        $default = (string) config('suppliers.sabre.default_base_url', '');
        if ($default !== '') {
            if (! str_contains($default, '://')) {
                $default = 'https://'.$default;
            }
            $defaultHost = parse_url($default, PHP_URL_HOST);
            if (is_string($defaultHost) && strtolower($defaultHost) === $host) {
                return true;
            }
        }

        return str_contains($host, '.cert.') || str_contains($host, 'api-crt');
    }

    public static function isProductionBaseUrl(string $baseUrl): bool
    {
        $host = self::resolveBaseUrlHost($baseUrl);
        if ($host === '') {
            return false;
        }

        if ($host === self::PRODUCTION_BASE_URL_HOST) {
            return true;
        }

        $default = (string) config('suppliers.sabre.default_base_url', '');
        if ($default !== '') {
            $defaultHost = self::resolveBaseUrlHost($default);
            if ($defaultHost === self::PRODUCTION_BASE_URL_HOST && $host === $defaultHost) {
                return true;
            }
        }

        return false;
    }

    protected static function resolveBaseUrlHost(string $baseUrl): string
    {
        $baseUrl = trim($baseUrl);
        if ($baseUrl === '') {
            return '';
        }
        if (! str_contains($baseUrl, '://')) {
            $baseUrl = 'https://'.$baseUrl;
        }
        $host = parse_url($baseUrl, PHP_URL_HOST);

        return is_string($host) && $host !== '' ? strtolower($host) : '';
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
            data_get($meta, 'supplier_api_booking_id'),
        ] as $candidate) {
            if (is_string($candidate) && trim($candidate) !== '') {
                return trim($candidate);
            }
        }

        $booking->loadMissing('supplierBookings');
        foreach ($booking->supplierBookings as $sb) {
            $apiId = trim((string) ($sb->supplier_api_booking_id ?? ''));
            if ($apiId !== '') {
                return $apiId;
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
            data_get($meta, 'supplier_reference'),
        ] as $candidate) {
            if (is_string($candidate) && trim($candidate) !== '') {
                return trim($candidate);
            }
        }

        return null;
    }

    protected function resolvePrimaryIdentifierSource(?string $apiId, ?string $supRef, string $pnr): string
    {
        if ($apiId !== null) {
            return 'supplier_api_booking_id';
        }
        if ($supRef !== null && strtoupper($supRef) !== $pnr) {
            return 'supplier_reference';
        }

        return 'pnr';
    }

    protected function isTicketed(Booking $booking): bool
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

    /**
     * @param  array<string, mixed>  $meta
     */
    protected function resolveConnection(array $meta): ?SupplierConnection
    {
        $cid = data_get($meta, 'supplier_connection_id');
        $cid = is_numeric($cid) ? (int) $cid : 0;
        if ($cid > 0) {
            $c = SupplierConnection::query()->find($cid);
            if ($c !== null && $c->provider === SupplierProvider::Sabre) {
                return $c;
            }
        }

        return SupplierConnection::query()
            ->where('provider', SupplierProvider::Sabre)
            ->orderBy('id')
            ->first();
    }

    /**
     * @param  list<array{style: string, body: array<string, mixed>, primary_identifier_field: string, primary_identifier_source: string, recommended: bool}>  $candidates
     * @return array{style: string, body: array<string, mixed>, primary_identifier_field: string, primary_identifier_source: string, recommended: bool}|array{}
     */
    protected function selectCandidate(array $candidates, ?string $styleOverride): array
    {
        $style = is_string($styleOverride) ? trim($styleOverride) : '';
        if ($style !== '') {
            foreach ($candidates as $row) {
                if (($row['style'] ?? '') === $style) {
                    return $row;
                }
            }

            return [];
        }
        foreach ($candidates as $row) {
            if (($row['recommended'] ?? false) === true) {
                return $row;
            }
        }

        if (! SabreCancelPayloadBuilder::usesAutoConfiguredPayloadStyle()) {
            return [];
        }

        return $candidates[0] ?? [];
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
            'status' => (string) ($httpResult['status'] ?? 'unknown'),
            'http_status' => $httpResult['http_status'] ?? null,
            'error_code' => $httpResult['error_code'] ?? null,
            'reason_code' => $httpResult['reason_code'] ?? null,
            'safe_message' => isset($httpResult['safe_message']) && is_string($httpResult['safe_message'])
                ? substr($httpResult['safe_message'], 0, 240)
                : null,
            'access_result' => SabreBookingService::discoveryAccessResultForProbe(
                (int) ($httpResult['http_status'] ?? 0),
                null,
            ),
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
            'response_top_level_keys' => array_slice(
                array_map('strval', is_array($diag['response_safe_keys'] ?? null) ? $diag['response_safe_keys'] : []),
                0,
                16,
            ),
            'response_error_details_sanitized' => $sanitized['response_error_details_sanitized'],
            'validation_missing_fields_sanitized' => $sanitized['validation_missing_fields_sanitized'],
            'conversation_id_sent' => ($diag['conversation_id_sent'] ?? false) === true,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    protected function postCancelVerificationForBooking(Booking $booking): array
    {
        $fetch = $this->pnrRetrieveProbe->fetchTripOrdersGetBooking($booking);

        return $this->safePostCancelVerification($fetch);
    }

    /**
     * @return array<string, mixed>
     */
    protected function postCancelVerificationForDirectPnr(SupplierConnection $connection, string $pnr): array
    {
        $fetch = $this->pnrRetrieveProbe->fetchTripOrdersGetBookingDirect($connection, $pnr);

        return $this->safePostCancelVerification($fetch);
    }

    /**
     * @param  array<string, mixed>  $fetch
     * @return array<string, mixed>
     */
    protected function safePostCancelVerification(array $fetch): array
    {
        $json = is_array($fetch['json'] ?? null) ? $fetch['json'] : [];
        $httpStatus = (int) ($fetch['http_status'] ?? 0);
        unset($fetch['json']);

        $summary = [
            'http_status' => $httpStatus,
            'error' => is_string($fetch['error'] ?? null) ? (string) $fetch['error'] : null,
            'cancel_verification_possible' => false,
            'cancel_verification_status' => 'unknown_no_status_fields',
            'cancel_verification_reason' => 'post_cancel_get_booking_empty',
        ];

        if ($json !== [] && ! isset($fetch['error'])) {
            $inspectSummary = app(SabreTripOrdersGetBookingInspectSummary::class);
            $row = $inspectSummary->buildForProbeRow($json, ['http_status' => $httpStatus]);
            $statusSummary = is_array($row['get_booking_status_summary'] ?? null)
                ? $row['get_booking_status_summary']
                : [];
            $airPathCount = $this->sumSafePathCounts($row['possible_air_item_paths'] ?? []);
            $segmentPathCount = $this->sumSafePathCounts($row['possible_segment_paths'] ?? []);
            $segmentCount = $segmentPathCount > 0 ? $segmentPathCount : $airPathCount;
            $ticketNumbersPresent = (bool) ($inspectSummary->extractDirectCancelSafetyFlags($json)['ticket_numbers_present'] ?? false);
            $pnrShellPresent = ((int) ($statusSummary['traveler_count'] ?? 0) > 0)
                || ((int) ($statusSummary['fare_count'] ?? 0) > 0)
                || ((int) ($statusSummary['remark_count'] ?? 0) > 0)
                || (($statusSummary['contact_info_present'] ?? false) === true);

            $summary['cancel_verification_possible'] = (bool) ($row['cancel_verification_possible'] ?? false);
            $summary['cancel_verification_status'] = (string) ($row['cancel_verification_status'] ?? 'unknown_no_status_fields');
            $summary['cancel_verification_reason'] = (string) ($row['cancel_verification_reason'] ?? '');
            $summary['post_cancel_air_segments_present'] = $segmentCount > 0;
            $summary['post_cancel_segment_count'] = $segmentCount;
            $summary['post_cancel_pnr_shell_present'] = $pnrShellPresent;
            $summary['post_cancel_ticket_numbers_present'] = $ticketNumbersPresent;
            $summary['cancel_air_segments_removed'] = $httpStatus >= 200
                && $httpStatus < 300
                && $segmentCount === 0
                && $pnrShellPresent
                && ! $ticketNumbersPresent;
        }

        return $summary;
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
            return 'CANCEL_CONFIRMED_AIR_SEGMENTS_REMOVED';
        }
        if ($verificationStatus === 'likely_cancelled') {
            return 'CANCEL_CONFIRMED';
        }
        if ($httpStatus >= 200 && $httpStatus < 300 && $verificationStatus === 'likely_active') {
            return 'HTTP_200_BUT_STILL_ACTIVE';
        }

        return 'UNKNOWN';
    }

    /**
     * @param  array{style: string, body: array<string, mixed>, primary_identifier_source: string}  $selected
     * @param  array<string, mixed>  $httpResult
     */
    protected function recordProbeAttempt(Booking $booking, SupplierConnection $connection, array $selected, array $httpResult): void
    {
        $diag = is_array($httpResult['booking_diagnostics'] ?? null) ? $httpResult['booking_diagnostics'] : [];
        $httpStatus = (int) ($httpResult['http_status'] ?? 0);
        $access = SabreBookingService::discoveryAccessResultForProbe($httpStatus, null);

        $probeSanitized = SabreCancelProbeDiagnostics::cancelProbeSliceFromDigest($diag);

        SupplierBookingAttempt::query()->create([
            'agency_id' => $booking->agency_id,
            'booking_id' => $booking->id,
            'supplier_connection_id' => $connection->id,
            'provider' => SupplierProvider::Sabre->value,
            'action' => 'inspect_cancel_pnr',
            'status' => $httpStatus >= 200 && $httpStatus < 300 ? 'attempted' : 'failed',
            'error_code' => is_string($httpResult['error_code'] ?? null) ? $httpResult['error_code'] : null,
            'error_message' => null,
            'supplier_reference' => null,
            'request_payload' => null,
            'response_payload' => null,
            'safe_summary' => SensitiveDataRedactor::redact([
                'source' => 'sabre_inspect_cancel_booking',
                'endpoint_path' => $diag['endpoint_path'] ?? config('suppliers.sabre.cancel_endpoint_path'),
                'payload_style' => $selected['style'],
                'primary_identifier_source' => $selected['primary_identifier_source'],
                'http_status' => (string) $httpStatus,
                'access_result' => $access,
                'ticketing_disabled' => true,
                'booking_status_updated' => false,
                'live_call_attempted' => true,
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
                'response_error_paths' => array_slice(
                    array_map('strval', is_array($diag['response_error_paths'] ?? null) ? $diag['response_error_paths'] : []),
                    0,
                    16,
                ),
                'response_missing_fields' => array_slice(
                    array_map('strval', is_array($diag['response_missing_fields'] ?? null) ? $diag['response_missing_fields'] : []),
                    0,
                    16,
                ),
                'response_top_level_keys' => array_slice(
                    array_map('strval', is_array($diag['response_safe_keys'] ?? null) ? $diag['response_safe_keys'] : []),
                    0,
                    16,
                ),
                'response_error_details_sanitized' => $probeSanitized['response_error_details_sanitized'],
                'validation_missing_fields_sanitized' => $probeSanitized['validation_missing_fields_sanitized'],
            ]),
            'attempted_by' => null,
            'attempted_at' => now(),
            'completed_at' => now(),
        ]);
    }
}
