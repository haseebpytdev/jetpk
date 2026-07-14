<?php

namespace App\Services\Suppliers\Iati;

use App\Data\FareBreakdownData;
use App\Data\NormalizedFlightOfferData;
use App\Data\OfferValidationResultData;
use App\Models\SupplierConnection;
use App\Services\Suppliers\Iati\Exceptions\IatiException;
use App\Services\Suppliers\Iati\Exceptions\IatiUnavailableException;
use App\Services\Suppliers\Iati\Exceptions\IatiValidationException;
use App\Services\Suppliers\SupplierDiagnosticLogger;
use App\Support\FlightSearch\FlightOfferDisplayPresenter;
use Illuminate\Support\Facades\Log;

/**
 * Non-mutating IATI fare confirmation via POST /rest/flight/v2/fare (fare_detail_key only).
 * Does not call /book, /option, /order, cancel, or ticketing endpoints.
 */
class IatiFareRevalidationService
{
    public const REVALIDATION_ENDPOINT = '/fare';

    private ?int $lastHttpStatus = null;

    public function __construct(
        private readonly IatiClient $client,
        private readonly IatiPayloadBuilder $payloadBuilder,
        private readonly IatiResponseNormalizer $normalizer,
        private readonly IatiFareRulesService $fareRulesService,
        private readonly SupplierDiagnosticLogger $diagnosticLogger,
    ) {}

    public function revalidate(
        NormalizedFlightOfferData $offer,
        SupplierConnection $connection,
        ?string $selectedFareOptionId = null,
    ): OfferValidationResultData {
        $selection = FlightOfferDisplayPresenter::applySelectedFareFamilyOptionToOffer(
            $offer->toArray(),
            $this->normalizedSelectedFareOptionId($selectedFareOptionId),
        );
        if ($selection['error_code'] !== null) {
            return $this->selectedFareOptionSelectionFailed($offer, $connection, $selection, $selectedFareOptionId);
        }

        $workingOffer = NormalizedFlightOfferData::fromArray($selection['offer']);
        $selectionMeta = $this->normalizedSelectedFareOptionId($selectedFareOptionId) !== null
            ? $this->brandedSelectionDiagnostics($selection['resolved'], $workingOffer)
            : [];
        $providerContext = $this->providerContext($workingOffer);
        $oldTotal = (float) $workingOffer->fare_breakdown->supplier_total;
        $currency = $workingOffer->fare_breakdown->currency;
        $this->lastHttpStatus = null;

        try {
            $payload = $this->payloadBuilder->buildFarePayload($providerContext);
            $response = $this->client->post($connection, self::REVALIDATION_ENDPOINT, $payload, [
                'request_context' => 'fare_revalidate',
            ]);
            $diagnostic = is_array($response['_ota_diagnostic'] ?? null) ? $response['_ota_diagnostic'] : [];
            $this->lastHttpStatus = isset($diagnostic['http_status']) ? (int) $diagnostic['http_status'] : null;
            $fare = $this->normalizer->normalizeFareResponse($response, $providerContext);
            $pricing = $this->resolveConfirmedPricing($response, $providerContext, $oldTotal);

            if (($pricing['error'] ?? '') === 'no_selected_offer_match') {
                return $this->noOfferMatchValidation(
                    $connection,
                    $workingOffer,
                    $oldTotal,
                    $currency,
                    $pricing,
                );
            }

            if ($pricing['total'] === null || $pricing['total'] <= 0.0) {
                return $this->incompletePricingValidation(
                    $connection,
                    $workingOffer,
                    $oldTotal,
                    $currency,
                    $pricing,
                );
            }

            $newTotal = $pricing['total'];
            $fare = array_merge($fare, [
                'total' => $pricing['total'],
                'base' => $pricing['base'] ?? (float) ($fare['base'] ?? 0),
                'tax' => $pricing['tax'] ?? (float) ($fare['tax'] ?? 0),
                'currency' => $pricing['currency'] ?: ($fare['currency'] ?? $currency),
            ]);
            $priceChanged = abs($newTotal - $oldTotal) > 0.01;

            Log::channel('iati')->info('iati.fare_revalidate.success', [
                'supplier_connection_id' => $connection->id,
                'offer_id' => $workingOffer->offer_id,
                'old_total' => $oldTotal,
                'new_total' => $newTotal,
                'price_changed' => $priceChanged,
                'correlation_id' => $diagnostic['correlation_id'] ?? null,
            ]);

            $updatedOffer = $this->applyFareToOffer($workingOffer, $fare);

            $this->diagnosticLogger->log(
                connection: $connection,
                action: 'validate_offer',
                status: 'success',
                durationMs: isset($diagnostic['duration_ms']) ? (int) $diagnostic['duration_ms'] : null,
                safeMessage: $priceChanged ? 'IATI fare changed during revalidation.' : 'IATI fare confirmed.',
                correlationId: isset($diagnostic['correlation_id']) ? (string) $diagnostic['correlation_id'] : null,
                meta: [
                    'price_changed' => $priceChanged,
                    'old_total' => $oldTotal,
                    'new_total' => $newTotal,
                ],
            );

            return new OfferValidationResultData(
                is_valid: true,
                status: $priceChanged ? 'price_changed' : 'valid',
                original_offer_id: $workingOffer->offer_id,
                validated_offer: $updatedOffer,
                price_changed: $priceChanged,
                old_total: $oldTotal,
                new_total: $newTotal,
                currency: $fare['currency'] ?: $currency,
                warnings: $priceChanged ? ['Fare price has changed. Please review before continuing.'] : [],
                meta: [
                    'fare_detail_key' => $fare['fare_detail_key'],
                    'change_rules' => $fare['change_rules'],
                    'provider_context' => $fare['provider_context'],
                    'revalidation_http_status' => $this->lastHttpStatus,
                    'revalidation_endpoint' => self::REVALIDATION_ENDPOINT,
                    'baggage_confirmed' => $this->baggageConfirmed($updatedOffer),
                    'booking_class_confirmed' => $this->bookingClassConfirmed($updatedOffer, $fare),
                    'fare_rules_confirmed' => ($fare['change_rules'] ?? []) !== [],
                    'confirmed_total' => $newTotal,
                    'confirmed_total_source_path' => $pricing['source_path'],
                    'confirmed_total_raw_value' => $pricing['raw_value'],
                    'matched_offer_index' => $pricing['matched_offer_index'] ?? null,
                    'matched_confirmed_total_source_path' => $pricing['source_path'],
                    ...$this->offerMatchDiagnosticsFromPricing($pricing),
                    ...$selectionMeta,
                ],
            );
        } catch (IatiUnavailableException $exception) {
            $this->lastHttpStatus = $exception->httpStatus;

            return $this->failedValidation(
                $connection,
                $workingOffer,
                $exception->normalizedCode === 'offer_unavailable' ? 'expired' : 'unavailable',
                $exception,
                $oldTotal,
                $currency,
            );
        } catch (IatiValidationException $exception) {
            $this->lastHttpStatus = $exception->httpStatus;

            return $this->failedValidation($connection, $workingOffer, 'invalid', $exception, $oldTotal, $currency);
        } catch (IatiException $exception) {
            $this->lastHttpStatus = $exception->httpStatus;

            return $this->failedValidation($connection, $workingOffer, 'invalid', $exception, $oldTotal, $currency);
        }
    }

    /**
     * @param  array<string, mixed>  $offerSnapshot
     * @return array<string, mixed>
     */
    public function buildPublicRevalidationReport(
        OfferValidationResultData $validation,
        array $offerSnapshot,
        ?string $selectedFareOptionId = null,
    ): array {
        $providerContext = is_array(data_get($offerSnapshot, 'raw_payload.provider_context'))
            ? data_get($offerSnapshot, 'raw_payload.provider_context')
            : [];
        $departureFareKey = trim((string) ($providerContext['departure_fare_key'] ?? ''));
        $publicStatus = $this->mapPublicStatus($validation);
        $confirmedTotal = $this->confirmedTotalForReport($validation);
        $message = $validation->warnings[0] ?? ($validation->is_valid
            ? ($validation->price_changed
                ? 'The airline fare has changed. Please review the updated price before continuing.'
                : 'Fare confirmed with the airline.')
            : 'We could not confirm this fare with the airline. Please refresh your search or choose another option.');

        return [
            'revalidation_status' => $publicStatus,
            'provider' => 'iati',
            'original_offer_id' => (string) ($validation->original_offer_id ?? $offerSnapshot['offer_id'] ?? $offerSnapshot['id'] ?? ''),
            'original_total' => (float) ($validation->old_total ?? data_get($offerSnapshot, 'fare_breakdown.supplier_total', 0)),
            'confirmed_total' => $confirmedTotal,
            'confirmed_total_source_path' => $validation->meta['confirmed_total_source_path'] ?? null,
            'confirmed_total_raw_value' => $validation->meta['confirmed_total_raw_value'] ?? null,
            'matched_offer_index' => $validation->meta['matched_offer_index'] ?? null,
            'matched_confirmed_total_source_path' => $validation->meta['matched_confirmed_total_source_path'] ?? null,
            'submitted_departure_fare_key_present' => (bool) ($validation->meta['submitted_departure_fare_key_present'] ?? false),
            'submitted_departure_fare_key_suffix' => $validation->meta['submitted_departure_fare_key_suffix'] ?? null,
            'returned_offer_count' => $validation->meta['returned_offer_count'] ?? null,
            'returned_offer_total_values' => $validation->meta['returned_offer_total_values'] ?? null,
            'returned_offer_key_match_count' => $validation->meta['returned_offer_key_match_count'] ?? null,
            'original_total_match_count' => $validation->meta['original_total_match_count'] ?? null,
            'matched_reason' => $validation->meta['matched_reason'] ?? null,
            'price_changed' => $validation->is_valid ? (bool) $validation->price_changed : false,
            'currency' => (string) ($validation->currency ?? data_get($offerSnapshot, 'fare_breakdown.currency', 'PKR')),
            'selected_fare_option_id' => $selectedFareOptionId,
            'selected_fare_option_matched' => $validation->meta['selected_fare_option_matched'] ?? null,
            'selected_fare_option_price' => $validation->meta['selected_fare_option_price'] ?? null,
            'selected_fare_option_original_total' => $validation->meta['selected_fare_option_original_total'] ?? null,
            'selected_fare_option_key_field' => $validation->meta['selected_fare_option_key_field'] ?? null,
            'selected_fare_option_fare_key_present' => $validation->meta['selected_fare_option_fare_key_present'] ?? null,
            'has_fare_key' => $departureFareKey !== '',
            'fare_key_present' => $departureFareKey !== '',
            'baggage_confirmed' => (bool) ($validation->meta['baggage_confirmed'] ?? $this->baggageConfirmedFromSnapshot($offerSnapshot)),
            'booking_class_confirmed' => (bool) ($validation->meta['booking_class_confirmed'] ?? false),
            'fare_rules_confirmed' => (bool) ($validation->meta['fare_rules_confirmed'] ?? (($validation->meta['change_rules'] ?? []) !== [])),
            'revalidation_endpoint' => (string) ($validation->meta['revalidation_endpoint'] ?? self::REVALIDATION_ENDPOINT),
            'revalidation_http_status' => $validation->meta['revalidation_http_status'] ?? $this->lastHttpStatus,
            'supplier_mutation_attempted' => false,
            'booking_created' => false,
            'ticketing_attempted' => false,
            'cancellation_attempted' => false,
            'emails_sent' => false,
            'safe_customer_message' => $message,
        ];
    }

    public function lastHttpStatus(): ?int
    {
        return $this->lastHttpStatus;
    }

    /**
     * @param  array<string, mixed>  $offer
     * @param  array{
     *     offer: array<string, mixed>,
     *     resolved: array{match_field: string, option: array<string, mixed>, brand: array<string, mixed>, index: int}|null,
     *     error_code: string|null,
     *     error_message: string|null
     * }  $selection
     */
    public function buildSelectedFareOptionFailureValidation(
        array $offer,
        array $selection,
        ?string $selectedFareOptionId,
    ): OfferValidationResultData {
        $normalized = NormalizedFlightOfferData::fromArray($offer);
        $message = (string) ($selection['error_message'] ?? 'Selected fare option could not be confirmed. Please choose the fare again.');
        $errorCode = (string) ($selection['error_code'] ?? 'selected_fare_option_not_found');

        return new OfferValidationResultData(
            is_valid: false,
            status: $errorCode === 'selected_fare_option_missing_fare_key' ? 'expired' : 'invalid',
            original_offer_id: $normalized->offer_id,
            price_changed: false,
            old_total: (float) $normalized->fare_breakdown->supplier_total,
            new_total: null,
            currency: $normalized->fare_breakdown->currency,
            warnings: [$message],
            meta: array_merge([
                'error_code' => $errorCode,
                'revalidation_endpoint' => self::REVALIDATION_ENDPOINT,
                'selected_fare_option_id' => $selectedFareOptionId,
            ], $this->brandedSelectionDiagnostics($selection['resolved'], $normalized, false)),
        );
    }

    /**
     * @return array<string, mixed>
     */
    public function auditLinkageFromOffer(NormalizedFlightOfferData $offer, ?string $selectedFareOptionId = null): array
    {
        $selection = FlightOfferDisplayPresenter::applySelectedFareFamilyOptionToOffer(
            $offer->toArray(),
            $this->normalizedSelectedFareOptionId($selectedFareOptionId),
        );
        $workingOffer = NormalizedFlightOfferData::fromArray($selection['offer']);
        $context = $this->providerContext($workingOffer);
        $departureFareKey = trim((string) ($context['departure_fare_key'] ?? ''));
        $diagnostics = $this->brandedSelectionDiagnostics($selection['resolved'], $workingOffer, $selection['error_code'] === null);

        return array_merge([
            'offer_id' => $workingOffer->offer_id,
            'provider' => 'iati',
            'selected_fare_option_id' => $selectedFareOptionId,
            'has_fare_key' => $departureFareKey !== '',
            'fare_key_present' => $departureFareKey !== '',
            'revalidation_endpoint' => self::REVALIDATION_ENDPOINT,
        ], $diagnostics);
    }

    protected function normalizedSelectedFareOptionId(?string $selectedFareOptionId): ?string
    {
        if ($selectedFareOptionId === null) {
            return null;
        }

        $trimmed = trim($selectedFareOptionId);

        return $trimmed !== '' ? $trimmed : null;
    }

    /**
     * @param  array{
     *     offer: array<string, mixed>,
     *     resolved: array{match_field: string, option: array<string, mixed>, brand: array<string, mixed>, index: int}|null,
     *     error_code: string|null,
     *     error_message: string|null
     * }  $selection
     */
    protected function selectedFareOptionSelectionFailed(
        NormalizedFlightOfferData $offer,
        SupplierConnection $connection,
        array $selection,
        ?string $selectedFareOptionId,
    ): OfferValidationResultData {
        $message = (string) ($selection['error_message'] ?? 'Selected fare option could not be confirmed. Please choose the fare again.');
        $errorCode = (string) ($selection['error_code'] ?? 'selected_fare_option_not_found');
        $oldTotal = (float) $offer->fare_breakdown->supplier_total;
        $currency = $offer->fare_breakdown->currency;

        $this->diagnosticLogger->log(
            connection: $connection,
            action: 'validate_offer',
            status: 'failed',
            safeMessage: $message,
            meta: ['error_code' => $errorCode],
        );

        return new OfferValidationResultData(
            is_valid: false,
            status: $errorCode === 'selected_fare_option_missing_fare_key' ? 'expired' : 'invalid',
            original_offer_id: $offer->offer_id,
            price_changed: false,
            old_total: $oldTotal,
            new_total: null,
            currency: $currency,
            warnings: [$message],
            meta: array_merge([
                'error_code' => $errorCode,
                'revalidation_endpoint' => self::REVALIDATION_ENDPOINT,
                'selected_fare_option_id' => $selectedFareOptionId,
            ], $this->brandedSelectionDiagnostics($selection['resolved'], $offer, false)),
        );
    }

    /**
     * @param  array{match_field: string, option: array<string, mixed>, brand: array<string, mixed>, index: int}|null  $resolved
     * @return array<string, mixed>
     */
    protected function brandedSelectionDiagnostics(
        ?array $resolved,
        NormalizedFlightOfferData $offer,
        ?bool $matched = null,
    ): array {
        if ($resolved === null) {
            return [
                'selected_fare_option_matched' => $matched ?? false,
                'selected_fare_option_price' => null,
                'selected_fare_option_original_total' => (float) $offer->fare_breakdown->supplier_total,
                'selected_fare_option_key_field' => null,
                'selected_fare_option_fare_key_present' => false,
            ];
        }

        $option = $resolved['option'];
        $brand = $resolved['brand'];
        $supplierTotal = FlightOfferDisplayPresenter::selectedFareFamilySupplierTotal($option, $brand);

        return [
            'selected_fare_option_matched' => $matched ?? true,
            'selected_fare_option_price' => $option['displayed_price'] ?? $option['price_total'] ?? $supplierTotal,
            'selected_fare_option_original_total' => (float) $offer->fare_breakdown->supplier_total,
            'selected_fare_option_key_field' => $resolved['match_field'],
            'selected_fare_option_fare_key_present' => trim((string) ($brand['departure_fare_key'] ?? '')) !== '',
        ];
    }

    protected function mapPublicStatus(OfferValidationResultData $validation): string
    {
        if (($validation->meta['incomplete_pricing'] ?? false) === true) {
            return 'failed';
        }

        if (($validation->meta['error_code'] ?? '') === 'no_selected_offer_match') {
            return 'failed';
        }

        if ($validation->is_valid) {
            return $validation->price_changed ? 'changed' : 'valid';
        }

        $errorCode = (string) ($validation->meta['error_code'] ?? '');
        if ($validation->status === 'expired' || $errorCode === 'offer_unavailable') {
            return 'expired';
        }

        if ($errorCode === 'supplier_request_invalid' && str_contains(strtolower($validation->warnings[0] ?? ''), 'fare key')) {
            return 'failed';
        }

        return 'failed';
    }

    protected function applyBrandedFareSelection(
        NormalizedFlightOfferData $offer,
        ?string $selectedFareOptionId,
    ): NormalizedFlightOfferData {
        $selection = FlightOfferDisplayPresenter::applySelectedFareFamilyOptionToOffer(
            $offer->toArray(),
            $this->normalizedSelectedFareOptionId($selectedFareOptionId),
        );

        return NormalizedFlightOfferData::fromArray($selection['offer']);
    }

    /**
     * @param  array<string, mixed>  $snapshot
     * @return array<string, mixed>
     */
    protected function applyBrandedFareSelectionToSnapshot(array $snapshot, ?string $selectedFareOptionId): array
    {
        return FlightOfferDisplayPresenter::applySelectedFareFamilyOptionToOffer(
            $snapshot,
            $this->normalizedSelectedFareOptionId($selectedFareOptionId),
        )['offer'];
    }

    /**
     * @param  array<string, mixed>  $fare
     */
    protected function bookingClassConfirmed(NormalizedFlightOfferData $offer, array $fare): bool
    {
        $segments = is_array($offer->segments) ? $offer->segments : [];
        foreach ($segments as $segment) {
            if (is_array($segment) && trim((string) ($segment['booking_class'] ?? $segment['class'] ?? '')) !== '') {
                return true;
            }
        }

        $summary = data_get($fare, 'provider_context.fare_response');

        return is_array($summary) && trim((string) data_get($summary, 'booking_class', '')) !== '';
    }

    protected function baggageConfirmed(NormalizedFlightOfferData $offer): bool
    {
        return $this->baggageConfirmedFromSnapshot($offer->toArray());
    }

    /**
     * @param  array<string, mixed>  $snapshot
     */
    protected function baggageConfirmedFromSnapshot(array $snapshot): bool
    {
        $baggage = is_array($snapshot['baggage'] ?? null) ? $snapshot['baggage'] : [];

        return trim((string) ($baggage['checked'] ?? '')) !== ''
            || trim((string) ($baggage['cabin'] ?? '')) !== ''
            || trim((string) ($baggage['summary'] ?? '')) !== '';
    }

    /**
     * @param  array<string, mixed>  $fare
     */
    protected function applyFareToOffer(NormalizedFlightOfferData $offer, array $fare): NormalizedFlightOfferData
    {
        $rawPayload = is_array($offer->raw_payload) ? $offer->raw_payload : [];
        $rawPayload['provider_context'] = array_merge(
            is_array($rawPayload['provider_context'] ?? null) ? $rawPayload['provider_context'] : [],
            $fare['provider_context'],
        );

        $fareBreakdown = $offer->fare_breakdown;
        $fareBreakdown = new FareBreakdownData(
            base_fare: (float) $fare['base'],
            taxes: (float) $fare['tax'],
            supplier_fees: $fareBreakdown->supplier_fees,
            supplier_total: (float) $fare['total'],
            currency: (string) ($fare['currency'] ?: $fareBreakdown->currency),
            passenger_pricing: $fareBreakdown->passenger_pricing,
            passenger_pricing_available: $fareBreakdown->passenger_pricing_available,
            passenger_counts: $fareBreakdown->passenger_counts,
            fare_basis_codes: $fareBreakdown->fare_basis_codes,
            breakdown_reconciled: true,
        );

        return new NormalizedFlightOfferData(
            offer_id: $offer->offer_id,
            supplier_provider: $offer->supplier_provider,
            supplier_connection_id: $offer->supplier_connection_id,
            airline_code: $offer->airline_code,
            airline_name: $offer->airline_name,
            flight_number: $offer->flight_number,
            origin: $offer->origin,
            destination: $offer->destination,
            departure_at: $offer->departure_at,
            arrival_at: $offer->arrival_at,
            duration_minutes: $offer->duration_minutes,
            stops: $offer->stops,
            cabin: $offer->cabin,
            fare_family: $offer->fare_family,
            refundable: $offer->refundable,
            seats_left: $offer->seats_left,
            segments: $offer->segments,
            baggage: $offer->baggage,
            fare_breakdown: $fareBreakdown,
            expires_at: $offer->expires_at,
            raw_reference: $offer->raw_reference,
            raw_payload: $rawPayload,
            marketing_carrier_chain: $offer->marketing_carrier_chain,
            operating_carrier_chain: $offer->operating_carrier_chain,
            validating_carrier: $offer->validating_carrier,
            primary_display_carrier: $offer->primary_display_carrier,
            mixed_carrier: $offer->mixed_carrier,
            all_airline_codes: $offer->all_airline_codes,
            branded_fares: $offer->branded_fares,
            distribution_channel: $offer->distribution_channel,
        );
    }

    protected function failedValidation(
        SupplierConnection $connection,
        NormalizedFlightOfferData $offer,
        string $status,
        IatiException $exception,
        float $oldTotal,
        string $currency,
    ): OfferValidationResultData {
        $this->diagnosticLogger->log(
            connection: $connection,
            action: 'validate_offer',
            status: 'failed',
            safeMessage: $exception->safeMessage,
            meta: ['error_code' => $exception->normalizedCode],
        );

        return new OfferValidationResultData(
            is_valid: false,
            status: $status,
            original_offer_id: $offer->offer_id,
            price_changed: false,
            old_total: $oldTotal,
            new_total: null,
            currency: $currency,
            warnings: [$exception->safeMessage],
            meta: [
                'error_code' => $exception->normalizedCode,
                'revalidation_http_status' => $exception->httpStatus,
                'revalidation_endpoint' => self::REVALIDATION_ENDPOINT,
                'confirmed_total' => null,
            ],
        );
    }

    /**
     * @param  array<string, mixed>  $pricing
     */
    protected function noOfferMatchValidation(
        SupplierConnection $connection,
        NormalizedFlightOfferData $offer,
        float $oldTotal,
        string $currency,
        array $pricing,
    ): OfferValidationResultData {
        $message = 'Fare confirmation returned multiple fare options. Please select the fare again.';

        Log::channel('iati')->warning('iati.fare_revalidate.no_selected_offer_match', [
            'supplier_connection_id' => $connection->id,
            'offer_id' => $offer->offer_id,
            'old_total' => $oldTotal,
            'returned_offer_count' => $pricing['returned_offer_count'] ?? null,
            'returned_offer_key_match_count' => $pricing['returned_offer_key_match_count'] ?? null,
        ]);

        $this->diagnosticLogger->log(
            connection: $connection,
            action: 'validate_offer',
            status: 'failed',
            safeMessage: $message,
            meta: ['error_code' => 'no_selected_offer_match'],
        );

        return new OfferValidationResultData(
            is_valid: false,
            status: 'no_selected_offer_match',
            original_offer_id: $offer->offer_id,
            price_changed: false,
            old_total: $oldTotal,
            new_total: null,
            currency: $currency,
            warnings: [$message],
            meta: [
                'error_code' => 'no_selected_offer_match',
                'revalidation_http_status' => $this->lastHttpStatus,
                'revalidation_endpoint' => self::REVALIDATION_ENDPOINT,
                'confirmed_total' => null,
                ...$this->offerMatchDiagnosticsFromPricing($pricing),
            ],
        );
    }

    /**
     * @param  array<string, mixed>  $pricing
     */
    protected function incompletePricingValidation(
        SupplierConnection $connection,
        NormalizedFlightOfferData $offer,
        float $oldTotal,
        string $currency,
        array $pricing,
    ): OfferValidationResultData {
        $message = 'Fare confirmation returned incomplete pricing. Please search again.';

        Log::channel('iati')->warning('iati.fare_revalidate.incomplete_pricing', [
            'supplier_connection_id' => $connection->id,
            'offer_id' => $offer->offer_id,
            'old_total' => $oldTotal,
            'source_path' => $pricing['source_path'] ?? null,
            'raw_value' => $pricing['raw_value'] ?? null,
        ]);

        $this->diagnosticLogger->log(
            connection: $connection,
            action: 'validate_offer',
            status: 'failed',
            safeMessage: $message,
            meta: ['error_code' => 'incomplete_pricing'],
        );

        return new OfferValidationResultData(
            is_valid: false,
            status: 'incomplete_pricing',
            original_offer_id: $offer->offer_id,
            price_changed: false,
            old_total: $oldTotal,
            new_total: null,
            currency: $currency,
            warnings: [$message],
            meta: [
                'error_code' => 'incomplete_pricing',
                'incomplete_pricing' => true,
                'revalidation_http_status' => $this->lastHttpStatus,
                'revalidation_endpoint' => self::REVALIDATION_ENDPOINT,
                'confirmed_total' => null,
                'confirmed_total_source_path' => $pricing['source_path'] ?? null,
                'confirmed_total_raw_value' => $pricing['raw_value'] ?? null,
                ...$this->offerMatchDiagnosticsFromPricing($pricing),
            ],
        );
    }

    /**
     * @param  array<string, mixed>  $response
     * @param  array<string, mixed>  $providerContext
     * @return array{
     *     total: float|null,
     *     base: float|null,
     *     tax: float|null,
     *     currency: string|null,
     *     source_path: string|null,
     *     raw_value: mixed,
     *     error?: string,
     *     matched_offer_index?: int|null,
     *     matched_reason?: string|null,
     *     original_total_match_count?: int,
     *     returned_offer_count?: int,
     *     returned_offer_total_values?: list<float|null>,
     *     returned_offer_key_match_count?: int,
     *     submitted_departure_fare_key_present?: bool,
     *     submitted_departure_fare_key_suffix?: string|null
     * }
     */
    protected function resolveConfirmedPricing(array $response, array $providerContext, float $originalTotal): array
    {
        $data = $this->client->unwrapResult($response);
        $submittedDepartureKey = trim((string) ($providerContext['departure_fare_key'] ?? ''));
        $submittedReturnKey = trim((string) ($providerContext['return_fare_key'] ?? ''));
        $offers = array_values(is_array($data['offers'] ?? null) ? $data['offers'] : []);
        $offerCount = count($offers);

        $diagnostics = $this->buildOfferMatchDiagnostics($data, $providerContext, $offers, $originalTotal);

        $matched = $this->findMatchedOfferPricing($offers, $data, $submittedDepartureKey, $submittedReturnKey);
        if ($matched !== null && ($matched['total'] ?? 0) > 0.0) {
            return array_merge($matched, $diagnostics, ['matched_reason' => 'fare_key_match']);
        }

        if ($offerCount === 1) {
            $single = $this->offerItemPricing($offers[0], 0, $data);
            if ($single !== null && ($single['total'] ?? 0) > 0.0) {
                return array_merge($single, $diagnostics, [
                    'matched_offer_index' => 0,
                    'matched_reason' => 'single_offer',
                ]);
            }
        }

        $defaultIndex = $this->findDefaultMarkedOfferIndex($offers);
        if ($defaultIndex !== null) {
            $defaultPricing = $this->offerItemPricing($offers[$defaultIndex], $defaultIndex, $data);
            if ($defaultPricing !== null && ($defaultPricing['total'] ?? 0) > 0.0) {
                return array_merge($defaultPricing, $diagnostics, [
                    'matched_offer_index' => $defaultIndex,
                    'matched_reason' => 'default_marker',
                    'source_path' => 'offers.'.$defaultIndex.'.default_marker',
                ]);
            }
        }

        if ($offerCount > 1) {
            $byOriginalTotal = $this->findMatchedOfferByOriginalTotal($offers, $data, $originalTotal);
            if ($byOriginalTotal !== null && ($byOriginalTotal['total'] ?? 0) > 0.0) {
                return array_merge($byOriginalTotal, $diagnostics);
            }
        }

        $departurePricing = $this->pricingFromMatchedDepartureFare($data, $submittedDepartureKey);
        if ($departurePricing !== null && ($departurePricing['total'] ?? 0) > 0.0) {
            return array_merge($departurePricing, $diagnostics);
        }

        if ($offerCount > 1) {
            return array_merge([
                'total' => null,
                'base' => null,
                'tax' => null,
                'currency' => null,
                'source_path' => null,
                'raw_value' => null,
                'error' => 'no_selected_offer_match',
                'matched_offer_index' => null,
            ], $diagnostics);
        }

        foreach ($this->confirmedPricingCandidates($data) as $candidate) {
            if (($candidate['total'] ?? 0) > 0.0) {
                return array_merge($candidate, $diagnostics);
            }
        }

        return array_merge([
            'total' => null,
            'base' => null,
            'tax' => null,
            'currency' => null,
            'source_path' => null,
            'raw_value' => null,
        ], $diagnostics);
    }

    /**
     * @param  list<array<string, mixed>>  $offers
     * @param  array<string, mixed>  $data
     * @return array{total: float, base: float|null, tax: float|null, currency: string|null, source_path: string, raw_value: mixed, matched_offer_index: int}|null
     */
    protected function findMatchedOfferPricing(
        array $offers,
        array $data,
        string $submittedDepartureKey,
        string $submittedReturnKey,
    ): ?array {
        if ($submittedDepartureKey === '' && $submittedReturnKey === '') {
            return null;
        }

        foreach ($offers as $index => $offer) {
            if (! is_array($offer)) {
                continue;
            }
            if (! $this->offerMatchesSubmittedKeys($offer, $submittedDepartureKey, $submittedReturnKey)) {
                continue;
            }
            $pricing = $this->offerItemPricing($offer, $index, $data);
            if ($pricing === null || ($pricing['total'] ?? 0) <= 0.0) {
                continue;
            }

            return array_merge($pricing, ['matched_offer_index' => $index]);
        }

        return null;
    }

    /**
     * @param  list<array<string, mixed>>  $offers
     * @param  array<string, mixed>  $data
     * @return array{total: float, base: float|null, tax: float|null, currency: string|null, source_path: string, raw_value: mixed, matched_offer_index: int, matched_reason: string, original_total_match_count: int}|null
     */
    protected function findMatchedOfferByOriginalTotal(array $offers, array $data, float $originalTotal): ?array
    {
        if ($originalTotal <= 0.0 || count($offers) < 2) {
            return null;
        }

        $matches = [];
        foreach ($offers as $index => $offer) {
            if (! is_array($offer)) {
                continue;
            }
            $pricing = $this->offerItemPricing($offer, $index, $data);
            if ($pricing === null || ($pricing['total'] ?? 0) <= 0.0) {
                continue;
            }
            if (abs((float) $pricing['total'] - $originalTotal) <= 0.01) {
                $matches[] = array_merge($pricing, ['matched_offer_index' => $index]);
            }
        }

        if (count($matches) !== 1) {
            return null;
        }

        return array_merge($matches[0], [
            'matched_reason' => 'original_total_exact_match',
            'original_total_match_count' => 1,
        ]);
    }

    /**
     * @param  list<array<string, mixed>>  $offers
     * @param  array<string, mixed>  $data
     */
    protected function countOriginalTotalMatches(array $offers, array $data, float $originalTotal): int
    {
        if ($originalTotal <= 0.0) {
            return 0;
        }

        $count = 0;
        foreach ($offers as $index => $offer) {
            if (! is_array($offer)) {
                continue;
            }
            $pricing = $this->offerItemPricing($offer, $index, $data);
            if ($pricing === null || ($pricing['total'] ?? 0) <= 0.0) {
                continue;
            }
            if (abs((float) $pricing['total'] - $originalTotal) <= 0.01) {
                $count++;
            }
        }

        return $count;
    }

    /**
     * @param  array<string, mixed>  $offer
     */
    protected function offerMatchesSubmittedKeys(array $offer, string $submittedDepartureKey, string $submittedReturnKey): bool
    {
        $offerKeys = $this->offerFareKeys($offer);
        if ($submittedDepartureKey !== '' && in_array($submittedDepartureKey, $offerKeys, true)) {
            return true;
        }

        if ($submittedReturnKey !== '' && in_array($submittedReturnKey, $offerKeys, true)) {
            return true;
        }

        return false;
    }

    /**
     * @param  array<string, mixed>  $offer
     * @return list<string>
     */
    protected function offerFareKeys(array $offer): array
    {
        $keys = [];
        foreach ([
            'fare_key',
            'departure_fare_key',
            'return_fare_key',
            'fare_detail_key',
        ] as $field) {
            $value = trim((string) ($offer[$field] ?? ''));
            if ($value !== '') {
                $keys[] = $value;
            }
        }

        foreach ([
            'fare_info.fare_key',
            'fare_info.fare_detail.fare_key',
            'departure.fare_key',
            'return.fare_key',
        ] as $path) {
            $value = trim((string) data_get($offer, $path, ''));
            if ($value !== '') {
                $keys[] = $value;
            }
        }

        return array_values(array_unique($keys));
    }

    /**
     * @param  array<string, mixed>  $offer
     * @param  array<string, mixed>  $data
     * @return array{total: float, base: float|null, tax: float|null, currency: string|null, source_path: string, raw_value: mixed}|null
     */
    protected function offerItemPricing(array $offer, int $index, array $data): ?array
    {
        foreach (['total_price', 'price', 'total_fare', 'total', 'amount'] as $key) {
            $value = $offer[$key] ?? null;
            if (is_numeric($value) && (float) $value > 0.0) {
                return $this->pricingCandidateFromPath(
                    $data,
                    "offers.{$index}.{$key}",
                    (float) $value,
                    $value,
                );
            }
        }

        $nestedTotal = data_get($offer, 'fare_info.fare_detail.price_info.total_fare');
        if (is_numeric($nestedTotal) && (float) $nestedTotal > 0.0) {
            return $this->pricingCandidateFromPath(
                $data,
                "offers.{$index}.fare_info.fare_detail.price_info.total_fare",
                (float) $nestedTotal,
                $nestedTotal,
            );
        }

        return null;
    }

    /**
     * @param  list<array<string, mixed>>  $offers
     */
    protected function findDefaultMarkedOfferIndex(array $offers): ?int
    {
        foreach ($offers as $index => $offer) {
            if (! is_array($offer)) {
                continue;
            }
            foreach (['selected', 'is_selected', 'default', 'is_default', 'current', 'is_current'] as $flag) {
                if (($offer[$flag] ?? false) === true || strtolower((string) ($offer[$flag] ?? '')) === 'true') {
                    return $index;
                }
            }
            if (is_array($offer['default_offer'] ?? null) && $offer['default_offer'] !== []) {
                return $index;
            }
        }

        return null;
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array{total: float, base: float|null, tax: float|null, currency: string|null, source_path: string, raw_value: mixed}|null
     */
    protected function pricingFromMatchedDepartureFare(array $data, string $submittedDepartureKey): ?array
    {
        if ($submittedDepartureKey === '') {
            return null;
        }

        $departureFareKey = trim((string) data_get($data, 'departure_fare.fare_key', ''));
        if ($departureFareKey !== '' && $departureFareKey !== $submittedDepartureKey) {
            return null;
        }

        $path = 'departure_fare.fare_info.fare_detail.price_info.total_fare';
        $value = data_get($data, $path);
        if (is_numeric($value) && (float) $value > 0.0) {
            return $this->pricingCandidateFromPath($data, $path, (float) $value, $value);
        }

        return null;
    }

    /**
     * @param  list<array<string, mixed>>  $offers
     * @return array{
     *     returned_offer_count: int,
     *     returned_offer_total_values: list<float|null>,
     *     returned_offer_key_match_count: int,
     *     original_total_match_count: int,
     *     submitted_departure_fare_key_present: bool,
     *     submitted_departure_fare_key_suffix: string|null
     * }
     */
    protected function buildOfferMatchDiagnostics(array $data, array $providerContext, array $offers, float $originalTotal): array
    {
        $submittedDepartureKey = trim((string) ($providerContext['departure_fare_key'] ?? ''));
        $submittedReturnKey = trim((string) ($providerContext['return_fare_key'] ?? ''));
        $totals = [];
        $matchCount = 0;

        foreach ($offers as $index => $offer) {
            if (! is_array($offer)) {
                $totals[] = null;

                continue;
            }
            $itemPricing = $this->offerItemPricing($offer, $index, $data);
            $totals[] = $itemPricing['total'] ?? null;
            if ($this->offerMatchesSubmittedKeys($offer, $submittedDepartureKey, $submittedReturnKey)) {
                $matchCount++;
            }
        }

        return [
            'returned_offer_count' => count($offers),
            'returned_offer_total_values' => $totals,
            'returned_offer_key_match_count' => $matchCount,
            'original_total_match_count' => $this->countOriginalTotalMatches($offers, $data, $originalTotal),
            'submitted_departure_fare_key_present' => $submittedDepartureKey !== '',
            'submitted_departure_fare_key_suffix' => $this->fareKeySuffix($submittedDepartureKey),
        ];
    }

    /**
     * @param  array<string, mixed>  $pricing
     * @return array<string, mixed>
     */
    protected function offerMatchDiagnosticsFromPricing(array $pricing): array
    {
        return array_filter([
            'returned_offer_count' => $pricing['returned_offer_count'] ?? null,
            'returned_offer_total_values' => $pricing['returned_offer_total_values'] ?? null,
            'returned_offer_key_match_count' => $pricing['returned_offer_key_match_count'] ?? null,
            'original_total_match_count' => $pricing['original_total_match_count'] ?? null,
            'matched_reason' => $pricing['matched_reason'] ?? null,
            'submitted_departure_fare_key_present' => $pricing['submitted_departure_fare_key_present'] ?? null,
            'submitted_departure_fare_key_suffix' => $pricing['submitted_departure_fare_key_suffix'] ?? null,
            'matched_offer_index' => $pricing['matched_offer_index'] ?? null,
            'matched_confirmed_total_source_path' => $pricing['source_path'] ?? null,
        ], fn ($value) => $value !== null);
    }

    protected function fareKeySuffix(string $fareKey): ?string
    {
        $fareKey = trim($fareKey);
        if ($fareKey === '') {
            return null;
        }

        if (strlen($fareKey) <= 8) {
            return substr($fareKey, -4);
        }

        return substr(hash('sha256', $fareKey), 0, 8);
    }

    /**
     * @param  array<string, mixed>  $data
     * @return list<array{total: float, base: float|null, tax: float|null, currency: string|null, source_path: string, raw_value: mixed}>
     */
    protected function confirmedPricingCandidates(array $data): array
    {
        $candidates = [];

        foreach (['total_fare', 'total', 'grand_total', 'amount'] as $key) {
            $value = $data[$key] ?? null;
            if (is_numeric($value) && (float) $value > 0.0) {
                $candidates[] = $this->pricingCandidateFromScalar((float) $value, $key, $value, $data);
            }
        }

        foreach ([
            'fare_detail.total_fare',
            'fare_detail.price_info.total_fare',
            'fare.total_fare',
            'fare_info.fare_detail.price_info.total_fare',
            'departure_fare.fare_info.fare_detail.price_info.total_fare',
            'return_fare.fare_info.fare_detail.price_info.total_fare',
            'fares.0.fare_info.fare_detail.price_info.total_fare',
        ] as $path) {
            $value = data_get($data, $path);
            if (is_numeric($value) && (float) $value > 0.0) {
                $candidates[] = $this->pricingCandidateFromPath($data, $path, (float) $value, $value);
            }
        }

        $offers = array_values(is_array($data['offers'] ?? null) ? $data['offers'] : []);
        foreach ($offers as $index => $offer) {
            if (! is_array($offer)) {
                continue;
            }

            foreach (['total_price', 'price', 'total_fare', 'total', 'amount'] as $key) {
                $value = $offer[$key] ?? null;
                if (is_numeric($value) && (float) $value > 0.0) {
                    $candidates[] = $this->pricingCandidateFromPath(
                        $data,
                        "offers.{$index}.{$key}",
                        (float) $value,
                        $value,
                    );
                }
            }

            $nestedTotal = data_get($data, "offers.{$index}.fare_info.fare_detail.price_info.total_fare");
            if (is_numeric($nestedTotal) && (float) $nestedTotal > 0.0) {
                $candidates[] = $this->pricingCandidateFromPath(
                    $data,
                    "offers.{$index}.fare_info.fare_detail.price_info.total_fare",
                    (float) $nestedTotal,
                    $nestedTotal,
                );
            }
        }

        $paxSum = $this->sumPaxFareTotals($data);
        if ($paxSum !== null && $paxSum['total'] > 0.0) {
            $candidates[] = $paxSum;
        }

        $baseTax = $this->basePlusTaxTotal($data);
        if ($baseTax !== null && $baseTax['total'] > 0.0) {
            $candidates[] = $baseTax;
        }

        return $candidates;
    }

    /**
     * @param  array<string, mixed>  $context
     * @return array{total: float, base: float|null, tax: float|null, currency: string|null, source_path: string, raw_value: mixed}
     */
    protected function pricingCandidateFromScalar(float $total, string $path, mixed $rawValue, array $context): array
    {
        $base = data_get($context, 'base_fare');
        $tax = data_get($context, 'tax');
        $currency = data_get($context, 'currency_code') ?? data_get($context, 'currency');

        return [
            'total' => $total,
            'base' => is_numeric($base) ? (float) $base : null,
            'tax' => is_numeric($tax) ? (float) $tax : null,
            'currency' => is_string($currency) && trim($currency) !== '' ? strtoupper(trim($currency)) : null,
            'source_path' => $path,
            'raw_value' => $rawValue,
        ];
    }

    /**
     * @param  array<string, mixed>  $context
     * @return array{total: float, base: float|null, tax: float|null, currency: string|null, source_path: string, raw_value: mixed}
     */
    protected function pricingCandidateFromPath(array $context, string $path, float $total, mixed $rawValue): array
    {
        $priceInfoPath = str_contains($path, '.price_info.')
            ? substr($path, 0, (int) strpos($path, '.price_info.')).'.price_info'
            : $path.'.price_info';
        $detailPath = str_contains($path, '.fare_detail.')
            ? substr($path, 0, (int) strpos($path, '.fare_detail.')).'.fare_detail'
            : null;

        $base = data_get($context, $priceInfoPath.'.base_fare') ?? data_get($context, $path.'.base_fare');
        $tax = data_get($context, $priceInfoPath.'.tax') ?? data_get($context, $path.'.tax');
        $currency = data_get($context, $detailPath.'.currency_code')
            ?? data_get($context, $priceInfoPath.'.currency_code')
            ?? data_get($context, $path.'.currency_code')
            ?? data_get($context, $path.'.currency');

        return [
            'total' => $total,
            'base' => is_numeric($base) ? (float) $base : null,
            'tax' => is_numeric($tax) ? (float) $tax : null,
            'currency' => is_string($currency) && trim($currency) !== '' ? strtoupper(trim($currency)) : null,
            'source_path' => $path,
            'raw_value' => $rawValue,
        ];
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array{total: float, base: float|null, tax: float|null, currency: string|null, source_path: string, raw_value: mixed}|null
     */
    protected function sumPaxFareTotals(array $data): ?array
    {
        $paxPaths = [
            'fare_detail.pax_fares',
            'fare_info.fare_detail.pax_fares',
            'departure_fare.fare_info.fare_detail.pax_fares',
            'fares.0.fare_info.fare_detail.pax_fares',
        ];

        foreach ($paxPaths as $path) {
            $rows = data_get($data, $path);
            if (! is_array($rows) || $rows === []) {
                continue;
            }

            $total = 0.0;
            $base = 0.0;
            $tax = 0.0;
            $currency = null;

            foreach ($rows as $row) {
                if (! is_array($row)) {
                    continue;
                }
                $priceInfo = is_array($row['price_info'] ?? null) ? $row['price_info'] : [];
                $count = max(1, (int) ($row['number_of_pax'] ?? $row['count'] ?? 1));
                $rowTotal = (float) ($priceInfo['total_fare'] ?? $row['total_fare'] ?? 0);
                if ($rowTotal <= 0.0) {
                    continue;
                }
                $total += $rowTotal * $count;
                $base += (float) ($priceInfo['base_fare'] ?? 0) * $count;
                $tax += (float) ($priceInfo['tax'] ?? 0) * $count;
                $currency = strtoupper((string) ($row['currency_code'] ?? data_get($row, 'fare_detail.currency_code', $currency ?? 'PKR')));
            }

            if ($total > 0.0) {
                return [
                    'total' => $total,
                    'base' => $base > 0.0 ? $base : null,
                    'tax' => $tax > 0.0 ? $tax : null,
                    'currency' => $currency,
                    'source_path' => $path.'[*].price_info.total_fare',
                    'raw_value' => $total,
                ];
            }
        }

        return null;
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array{total: float, base: float|null, tax: float|null, currency: string|null, source_path: string, raw_value: mixed}|null
     */
    protected function basePlusTaxTotal(array $data): ?array
    {
        $pairs = [
            ['fare_detail.price_info.base_fare', 'fare_detail.price_info.tax', 'fare_detail.price_info.total_fare', 'fare_detail.currency_code'],
            ['fare_info.fare_detail.price_info.base_fare', 'fare_info.fare_detail.price_info.tax', 'fare_info.fare_detail.price_info.total_fare', 'fare_info.fare_detail.currency_code'],
            ['departure_fare.fare_info.fare_detail.price_info.base_fare', 'departure_fare.fare_info.fare_detail.price_info.tax', 'departure_fare.fare_info.fare_detail.price_info.total_fare', 'departure_fare.fare_info.fare_detail.currency_code'],
        ];

        foreach ($pairs as [$basePath, $taxPath, $totalPath, $currencyPath]) {
            $base = data_get($data, $basePath);
            $tax = data_get($data, $taxPath);
            $explicitTotal = data_get($data, $totalPath);
            if (is_numeric($explicitTotal) && (float) $explicitTotal > 0.0) {
                continue;
            }
            if (! is_numeric($base) || ! is_numeric($tax)) {
                continue;
            }
            $sum = (float) $base + (float) $tax;
            if ($sum <= 0.0) {
                continue;
            }
            $currency = data_get($data, $currencyPath);

            return [
                'total' => $sum,
                'base' => (float) $base,
                'tax' => (float) $tax,
                'currency' => is_string($currency) && trim($currency) !== '' ? strtoupper(trim($currency)) : null,
                'source_path' => $basePath.'+'.$taxPath,
                'raw_value' => $sum,
            ];
        }

        return null;
    }

    protected function confirmedTotalForReport(OfferValidationResultData $validation): ?float
    {
        if (array_key_exists('confirmed_total', $validation->meta)) {
            $metaTotal = $validation->meta['confirmed_total'];
            if ($metaTotal === null) {
                return null;
            }
            if (is_numeric($metaTotal) && (float) $metaTotal > 0.0) {
                return (float) $metaTotal;
            }

            return null;
        }

        if ($validation->new_total !== null && $validation->new_total > 0.0) {
            return (float) $validation->new_total;
        }

        return null;
    }

    /**
     * @return array<string, mixed>
     */
    protected function providerContext(NormalizedFlightOfferData $offer): array
    {
        $raw = is_array($offer->raw_payload) ? $offer->raw_payload : [];
        $context = is_array($raw['provider_context'] ?? null) ? $raw['provider_context'] : [];

        if (empty($context['pax_counts']) && $offer->fare_breakdown->passenger_counts !== []) {
            $context['pax_counts'] = $offer->fare_breakdown->passenger_counts;
        }

        return $context;
    }
}
