<?php

namespace App\Services\Suppliers\Adapters;

use App\Contracts\Suppliers\FlightSupplierInterface;
use App\Data\FlightSearchRequestData;
use App\Data\FlightSearchResultData;
use App\Data\NormalizedFlightOfferData;
use App\Data\OfferValidationResultData;
use App\Enums\SupplierConnectionStatus;
use App\Enums\SupplierEnvironment;
use App\Enums\SupplierProvider;
use App\Models\SupplierConnection;
use App\Services\Suppliers\Duffel\DuffelClient;
use App\Services\Suppliers\Duffel\DuffelOfferNormalizer;
use App\Services\Suppliers\Duffel\DuffelProviderException;
use App\Services\Suppliers\SupplierDiagnosticLogger;
use Carbon\Carbon;
use Illuminate\Support\Facades\Log;
use Throwable;

class DuffelFlightSupplierAdapter implements FlightSupplierInterface
{
    public function __construct(
        private readonly DuffelClient $client,
        private readonly DuffelOfferNormalizer $normalizer,
        private readonly SupplierDiagnosticLogger $diagnosticLogger,
    ) {}

    public function search(FlightSearchRequestData $request, SupplierConnection $connection): FlightSearchResultData
    {
        if (! $this->connectionReady($connection)) {
            $this->diagnosticLogger->log(
                connection: $connection,
                action: 'search',
                status: 'warning',
                safeMessage: 'Connection is inactive for Duffel search.'
            );

            return new FlightSearchResultData(
                supplier_provider: SupplierProvider::Duffel,
                offers: [],
                warnings: ['Duffel supplier connection is inactive.'],
                meta: ['connection_id' => $connection->id]
            );
        }

        if (! $this->tokenExists($connection)) {
            $this->diagnosticLogger->log(
                connection: $connection,
                action: 'search',
                status: 'failed',
                safeMessage: 'Missing Duffel access token.'
            );

            return new FlightSearchResultData(
                supplier_provider: SupplierProvider::Duffel,
                offers: [],
                warnings: ['Provider search is temporarily unavailable.'],
                meta: ['connection_id' => $connection->id]
            );
        }

        try {
            $response = $this->client->createOfferRequest($request, $connection);
            $diagnostic = is_array($response['_ota_diagnostic'] ?? null) ? $response['_ota_diagnostic'] : [];
            $offers = $this->normalizer->normalizeMany($response, $connection);
            if ($offers === []) {
                $this->diagnosticLogger->log(
                    connection: $connection,
                    action: 'search',
                    status: 'warning',
                    durationMs: isset($diagnostic['duration_ms']) ? (int) $diagnostic['duration_ms'] : null,
                    safeMessage: 'Duffel returned no fares for the route/date.',
                    correlationId: isset($diagnostic['correlation_id']) ? (string) $diagnostic['correlation_id'] : null
                );

                return new FlightSearchResultData(
                    supplier_provider: SupplierProvider::Duffel,
                    offers: [],
                    warnings: ['Duffel returned no fares for this route/date.'],
                    meta: ['connection_id' => $connection->id]
                );
            }

            $this->diagnosticLogger->log(
                connection: $connection,
                action: 'search',
                status: 'success',
                durationMs: isset($diagnostic['duration_ms']) ? (int) $diagnostic['duration_ms'] : null,
                safeMessage: 'Duffel search completed.',
                correlationId: isset($diagnostic['correlation_id']) ? (string) $diagnostic['correlation_id'] : null,
                meta: [
                    'offers_count' => count($offers),
                ],
            );

            return new FlightSearchResultData(
                supplier_provider: SupplierProvider::Duffel,
                offers: $offers,
                warnings: [],
                meta: ['connection_id' => $connection->id]
            );
        } catch (DuffelProviderException $exception) {
            $this->diagnosticLogger->log(
                connection: $connection,
                action: 'search',
                status: 'failed',
                safeMessage: $exception->safeMessage,
                meta: [
                    'error_code' => $exception->normalizedCode,
                ],
            );

            return new FlightSearchResultData(
                supplier_provider: SupplierProvider::Duffel,
                offers: [],
                warnings: ['Provider search is temporarily unavailable.'],
                meta: ['connection_id' => $connection->id, 'error_code' => $exception->normalizedCode]
            );
        } catch (Throwable) {
            $this->diagnosticLogger->log(
                connection: $connection,
                action: 'search',
                status: 'failed',
                safeMessage: 'Duffel search transport error.'
            );

            return new FlightSearchResultData(
                supplier_provider: SupplierProvider::Duffel,
                offers: [],
                warnings: ['Provider search is temporarily unavailable.'],
                meta: ['connection_id' => $connection->id]
            );
        }
    }

    public function validateOffer(NormalizedFlightOfferData|string $offer, FlightSearchRequestData $request, SupplierConnection $connection): OfferValidationResultData
    {
        $original = is_string($offer) ? null : $offer;
        $offerId = is_string($offer)
            ? $offer
            : ((string) ($offer->raw_reference ?: $offer->offer_id));

        if (! $this->connectionReady($connection) || ! $this->tokenExists($connection)) {
            $this->diagnosticLogger->log(
                connection: $connection,
                action: 'validate_offer',
                status: 'failed',
                safeMessage: 'Duffel provider is not ready for validation.',
                meta: [
                    'offer_id' => $offerId,
                    'reason_code' => 'provider_auth_failed',
                ]
            );

            return new OfferValidationResultData(
                is_valid: false,
                status: 'provider_error',
                original_offer_id: $offerId,
                warnings: ['Duffel provider is not ready for validation.']
            );
        }

        try {
            $response = $this->client->getOffer($offerId, $connection);
            $diagnostic = is_array($response['_ota_diagnostic'] ?? null) ? $response['_ota_diagnostic'] : [];
            $validated = $this->normalizer->normalizeOne($response, $connection);
            if ($validated === null) {
                $this->diagnosticLogger->log(
                    connection: $connection,
                    action: 'validate_offer',
                    status: 'warning',
                    durationMs: isset($diagnostic['duration_ms']) ? (int) $diagnostic['duration_ms'] : null,
                    safeMessage: 'Duffel offer is unavailable.',
                    correlationId: isset($diagnostic['correlation_id']) ? (string) $diagnostic['correlation_id'] : null,
                    meta: [
                        'offer_id' => $offerId,
                        'reason_code' => 'offer_unavailable',
                    ]
                );

                return new OfferValidationResultData(
                    is_valid: false,
                    status: 'unavailable',
                    original_offer_id: $offerId,
                    warnings: ['Selected Duffel fare is no longer available.']
                );
            }

            if ($validated->expires_at !== null) {
                try {
                    if (Carbon::parse($validated->expires_at)->isPast()) {
                        $this->diagnosticLogger->log(
                            connection: $connection,
                            action: 'validate_offer',
                            status: 'warning',
                            durationMs: isset($diagnostic['duration_ms']) ? (int) $diagnostic['duration_ms'] : null,
                            safeMessage: 'Duffel offer has expired.',
                            correlationId: isset($diagnostic['correlation_id']) ? (string) $diagnostic['correlation_id'] : null,
                            meta: [
                                'offer_id' => $offerId,
                                'reason_code' => 'offer_expired',
                            ]
                        );

                        return new OfferValidationResultData(
                            is_valid: false,
                            status: 'expired',
                            original_offer_id: $offerId,
                            warnings: ['Selected Duffel fare has expired.']
                        );
                    }
                } catch (Throwable) {
                    // Ignore parse issues and continue normal comparison.
                }
            }

            $oldTotal = $original?->fare_breakdown->supplier_total;
            $newTotal = $validated->fare_breakdown->supplier_total;
            $priceChanged = $oldTotal !== null && abs($oldTotal - $newTotal) > 0.009;

            $this->diagnosticLogger->log(
                connection: $connection,
                action: 'validate_offer',
                status: $priceChanged ? 'warning' : 'success',
                durationMs: isset($diagnostic['duration_ms']) ? (int) $diagnostic['duration_ms'] : null,
                safeMessage: $priceChanged ? 'Duffel offer price changed.' : 'Duffel offer validated.',
                correlationId: isset($diagnostic['correlation_id']) ? (string) $diagnostic['correlation_id'] : null,
                meta: [
                    'offer_id' => $offerId,
                    'reason_code' => $priceChanged ? 'price_changed' : 'offer_valid',
                ]
            );

            return new OfferValidationResultData(
                is_valid: ! $priceChanged,
                status: $priceChanged ? 'price_changed' : 'valid',
                original_offer_id: $offerId,
                validated_offer: $validated,
                price_changed: $priceChanged,
                old_total: $oldTotal,
                new_total: $newTotal,
                currency: $validated->fare_breakdown->currency,
                warnings: $priceChanged ? ['Duffel fare changed during validation.'] : []
            );
        } catch (DuffelProviderException $exception) {
            if ($exception->normalizedCode === 'offer_unavailable') {
                $diagnosticMeta = $this->mergeDuffelValidateOfferMeta(
                    $offerId,
                    'offer_unavailable',
                    'offer_unavailable',
                    $exception,
                );
                $this->diagnosticLogger->log(
                    connection: $connection,
                    action: 'validate_offer',
                    status: 'warning',
                    safeMessage: 'Duffel offer is unavailable.',
                    meta: $diagnosticMeta,
                );
                Log::info('duffel.validate_offer.trace', $this->validateOfferTraceContext($offerId));

                return new OfferValidationResultData(
                    is_valid: false,
                    status: 'unavailable',
                    original_offer_id: $offerId,
                    warnings: ['Selected Duffel fare is no longer available.'],
                    meta: $diagnosticMeta,
                );
            }

            $reasonCode = match ($exception->normalizedCode) {
                'supplier_transport_failed' => 'provider_timeout',
                'supplier_auth_failed' => 'provider_auth_failed',
                'supplier_rate_limited' => 'provider_rate_limited',
                'supplier_request_invalid' => 'supplier_request_invalid',
                default => 'provider_error',
            };

            $isRequestInvalid = $exception->normalizedCode === 'supplier_request_invalid';
            $diagnosticMeta = $this->mergeDuffelValidateOfferMeta(
                $offerId,
                $reasonCode,
                $isRequestInvalid ? 'supplier_request_invalid' : $exception->normalizedCode,
                $exception,
            );

            Log::warning('duffel.validate_offer.exception', array_merge(
                $this->validateOfferTraceContext($offerId),
                [
                    'normalized_code' => $exception->normalizedCode,
                    'http_status' => $exception->httpStatus,
                    'endpoint' => $diagnosticMeta['endpoint'] ?? null,
                    'request_context' => $diagnosticMeta['request_context'] ?? null,
                    'duffel_errors' => $diagnosticMeta['duffel_errors'] ?? [],
                ],
            ));

            $this->diagnosticLogger->log(
                connection: $connection,
                action: 'validate_offer',
                status: 'failed',
                safeMessage: $isRequestInvalid
                    ? 'Duffel rejected the validation request. Check request payload/offer id.'
                    : $exception->safeMessage,
                meta: $diagnosticMeta,
            );

            return new OfferValidationResultData(
                is_valid: false,
                status: 'provider_error',
                original_offer_id: $offerId,
                warnings: $isRequestInvalid
                    ? ['Fare validation is temporarily unavailable. Please try again.']
                    : [$exception->safeMessage],
                meta: $diagnosticMeta,
            );
        } catch (Throwable) {
            $this->diagnosticLogger->log(
                connection: $connection,
                action: 'validate_offer',
                status: 'failed',
                safeMessage: 'Duffel validation transport error.',
                meta: [
                    'offer_id' => $offerId,
                    'reason_code' => 'provider_timeout',
                ]
            );

            return new OfferValidationResultData(
                is_valid: false,
                status: 'provider_error',
                original_offer_id: $offerId,
                warnings: ['Duffel validation is temporarily unavailable.']
            );
        }
    }

    /**
     * @return array<string, mixed>
     */
    private function mergeDuffelValidateOfferMeta(
        string $offerId,
        string $reasonCode,
        string $errorCode,
        DuffelProviderException $exception,
    ): array {
        $ctx = $exception->context;

        return array_merge($ctx, [
            'offer_id' => $offerId,
            'reason_code' => $reasonCode,
            'error_code' => $errorCode,
            'http_status' => $ctx['http_status'] ?? $exception->httpStatus,
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    private function validateOfferTraceContext(string $offerId): array
    {
        return [
            'route' => request()?->route()?->getName(),
            'path' => request()?->path(),
            'supplier_offer_id' => $offerId,
            'endpoint' => 'GET /air/offers/{id}',
            'request_handler' => 'DuffelClient::getOffer',
            'passenger_payload_included' => false,
            'requires_instant_payment_known' => null,
            'hold_attempt_before_passenger_details' => false,
        ];
    }

    public function provider(): SupplierProvider
    {
        return SupplierProvider::Duffel;
    }

    private function connectionReady(SupplierConnection $connection): bool
    {
        if (! $connection->isActive() || $connection->status !== SupplierConnectionStatus::Active) {
            return false;
        }

        return in_array($connection->environment, [SupplierEnvironment::Sandbox, SupplierEnvironment::Demo, SupplierEnvironment::Live], true);
    }

    private function tokenExists(SupplierConnection $connection): bool
    {
        $credentials = is_array($connection->credentials) ? $connection->credentials : [];

        return trim((string) ($credentials['access_token'] ?? '')) !== '';
    }
}
