<?php

namespace App\Services\Suppliers\Sabre\Gds;

use App\Enums\SupplierProvider;
use App\Exceptions\SabreRevalidateGatekeeperException;
use App\Models\SupplierConnection;

/**
 * B13 Sabre revalidation helpers used immediately before Trip Orders {@code createBooking} to acquire fare/offer
 * linkage (fare basis, fare reference, price-quote reference, offer reference, validating carrier, baggage, time limit).
 *
 * - {@see self::buildPayload()} produces a sanitized JSON envelope for {@code /v4/shop/flights/revalidate}
 *   (no Authorization, no PCC, no PII; segments in chronological order). B16: BFM/OTA styles; B17:
 *   {@code client_gds_revalidate_v1} uses a separate {@code RevalidateItineraryRQ} root (no {@code OTA_AirLowFareSearchRQ}).
 *   B22: {@code client_gds_revalidate_without_pos}, {@code client_gds_revalidate_without_travel_preferences},
 *   {@code client_gds_revalidate_segments_only}, {@code shop_replay_selected_itinerary_v1} (minimal OTA shop replay),
 *   **B69** {@code iati_like_bfm_revalidate_v1} (OTA-only BFM price-recheck; {@code TPA_Extensions.Flight[]}, DataSources, 50ITINS).
 *   **B71** IATI ODI leg grouping (24h gap + route continuity) with row-level {@code RPH}, locations, {@code SegmentType.Code=O}.
 *   **B72** IATI wire parity: {@code Version}, root {@code TPA_Extensions.IntelliSellTransaction}, {@code SeatsRequested[]} array,
 *   {@code Flight.Number} = marketing flight number, {@code Airline.Marketing}/{@code Airline.Operating}.
 *   **CERT** {@code manager_like_bfm_revalidate_v1}: {@code iati_like_bfm_revalidate_v1} + {@code VerificationItinCallLogic.Value=B}.
 *   **CERT** {@code manager_like_bfm_revalidate_enriched_v1}: manager-like OTA wire + per-flight {@code ResBookDesigCode},
 *   {@code FareBasisCode}, and {@code Airline.Operating} when segment data allows.
 *   {@see self::wireableRequestPayload()} strips {@code _ota_*} keys before HTTP.
 * - {@see self::extractFareLinkage()} parses the provider response into a safe linkage map (per-segment fare basis +
 *   scalar references) suitable for merging into the booking payload and persisting under
 *   {@code SupplierBookingAttempt.safe_summary}. Never stores raw JSON. Grouped-itinerary responses prefer the
 *   itinerary marked {@code currentItinerary=true}, falling back to the first itinerary when absent.
 * - B20: {@see self::digestRevalidateResponseStructure()} safe top-level keys / capped key paths / scalar candidates;
 *   {@see self::extractHttp200ApplicationWarningDigest()} warnings/messages/errors on HTTP 200; broader fare/linkage
 *   paths + bounded amount/currency scan in {@see self::extractFareLinkage()}.
 * - Production revalidate rules: {@see self::assertGatekeeperOrThrow()}, {@see self::evaluateGroupedItineraryMessages()},
 *   {@see self::assertPerSegmentFareBasisComplete()}, {@see self::evaluateRevalidationPricingTripwire()},
 *   {@see self::enrichInternalDraftFromGirArchive()}, {@see self::revalidationPayloadFreezeFingerprint()}.
 */
final class SabreRevalidationPayloadBuilder
{
    /** IATI GDS revalidate: new ODI leg when connection gap exceeds this (seconds). */
    private const IATI_ODI_CONNECTION_GAP_SECONDS = 86400;

    /** Default supplier-total drift allowed vs draft fare before auto-PNR tripwire blocks. */
    private const DEFAULT_REVALIDATE_FARE_TOLERANCE = 0.02;

    private const SAFE_MAX = 120;

    /** @var list<string> */
    private const PRODUCTION_BLOCKED_REVALIDATE_STYLES = [
        'bfm_revalidate_minimal_segments',
    ];

    /** @var list<string> */
    private const GIR_FATAL_MESSAGE_FRAGMENTS = [
        'NO COMBINABLE FARES',
        'NO FARES',
        'RBD',
        'CLASS USED',
        'ERROR DURING PROCESSING',
    ];

    private const DIGEST_SCALAR_CAP = 100;

    private const DIGEST_MAX_KEY_PATHS = 220;

    private const DIGEST_MAX_CANDIDATES = 48;

    /** @var list<string> */
    private const DIGEST_RISKY_KEY_FRAGMENTS = [
        'password', 'secret', 'token', 'authorization', 'accesstoken', 'access_token', 'client_secret',
        'passport', 'email', 'phone', 'telephone', 'contact', 'address', 'street', 'givenname', 'surname',
        'firstname', 'lastname', 'dateofbirth', 'date_of_birth', 'dob', 'pcc', 'pseudocity', 'passenger',
        'traveler', 'travelers', 'credit', 'card', 'cvv',
    ];

    /**
     * Build a sanitized revalidation request envelope from an internal booking draft (output of
     * {@see SabreBookingPayloadBuilder::buildInternalDraft()}). Field names broadly follow Sabre OTA/Trip Search
     * shop revalidate envelope; tune per-tenant.
     *
     * @param  array<string, mixed>  $internalDraft  Valid draft (_valid === true)
     * @return array<string, mixed>
     */
    public function buildPayload(array $internalDraft, ?string $styleOverride = null): array
    {
        $internalDraft = $this->enrichInternalDraftFromGirArchive($internalDraft);
        $style = $this->normalizeRevalidatePayloadStyle($styleOverride);
        if ($style === 'shop_replay_selected_itinerary_v1') {
            return $this->buildShopReplaySelectedItineraryV1Envelope($internalDraft);
        }

        if ($style === 'iati_like_bfm_revalidate_v1') {
            return $this->buildIatiLikeBfmRevalidateV1Envelope($internalDraft);
        }

        if ($style === 'manager_like_bfm_revalidate_v1') {
            return $this->buildManagerLikeBfmRevalidateV1Envelope($internalDraft);
        }

        if ($style === 'manager_like_bfm_revalidate_enriched_v1') {
            return $this->buildManagerLikeBfmRevalidateEnrichedV1Envelope($internalDraft);
        }

        if (in_array($style, [
            'client_gds_revalidate_v1',
            'client_gds_revalidate_without_pos',
            'client_gds_revalidate_without_travel_preferences',
            'client_gds_revalidate_segments_only',
        ], true)) {
            $base = $this->buildClientGdsRevalidateV1Envelope($internalDraft);

            return $this->applyClientGdsRevalidateStyleVariant($base, $style);
        }

        $mergedShopSource = $this->mergeDraftShopSources($internalDraft);

        $segments = is_array($internalDraft['segments'] ?? null) ? $internalDraft['segments'] : [];
        usort($segments, static function (array $a, array $b): int {
            return strcmp(
                (string) ($a['departure_at'] ?? $a['depart_at'] ?? ''),
                (string) ($b['departure_at'] ?? $b['depart_at'] ?? '')
            );
        });

        $passengers = is_array($internalDraft['passengers'] ?? null) ? $internalDraft['passengers'] : [];
        $ptcCounts = ['ADT' => 0, 'CHD' => 0, 'INF' => 0];
        foreach ($passengers as $p) {
            if (! is_array($p)) {
                continue;
            }
            $code = strtoupper(trim((string) ($p['type'] ?? 'ADT')));
            $code = match ($code) {
                'CHD', 'CH', 'CNN' => 'CHD',
                'INF', 'IN', 'INS' => 'INF',
                default => 'ADT',
            };
            $ptcCounts[$code]++;
        }
        if (array_sum($ptcCounts) === 0) {
            $ptcCounts['ADT'] = max(1, count($passengers));
        }

        $fare = is_array($internalDraft['fare'] ?? null) ? $internalDraft['fare'] : [];
        $currency = trim((string) ($fare['currency'] ?? ''));
        $amount = (float) ($fare['amount'] ?? 0);
        $validatingCarrier = strtoupper(trim((string) ($internalDraft['validating_carrier'] ?? '')));
        $selectedOfferId = trim((string) ($internalDraft['selected_offer_id'] ?? ''));
        $supplierOfferId = trim((string) ($internalDraft['supplier_offer_id'] ?? ''));

        $odis = [];
        foreach ($segments as $idx => $seg) {
            if (! is_array($seg)) {
                continue;
            }
            $depAt = (string) ($seg['departure_at'] ?? $seg['depart_at'] ?? '');
            $arrAt = (string) ($seg['arrival_at'] ?? $seg['arrive_at'] ?? '');
            $origin = strtoupper(trim((string) ($seg['origin'] ?? '')));
            $dest = strtoupper(trim((string) ($seg['destination'] ?? '')));
            $mkt = strtoupper(trim((string) ($seg['carrier'] ?? $seg['airline_code'] ?? '')));
            $op = strtoupper(trim((string) ($seg['operating_airline_code'] ?? '')));
            $flightNumber = trim((string) ($seg['flight_number'] ?? $seg['flight_no'] ?? ''));
            $bookingClass = strtoupper(trim((string) ($seg['booking_class'] ?? '')));
            $fareBasis = strtoupper(trim((string) ($seg['fare_basis_code'] ?? '')));
            $cabin = strtoupper(trim((string) ($seg['segment_cabin_code'] ?? '')));

            $flightSegment = array_filter([
                'Number' => $idx + 1,
                'SegmentNumber' => $idx + 1,
                'DepartureDateTime' => $depAt !== '' ? $depAt : null,
                'ArrivalDateTime' => $arrAt !== '' ? $arrAt : null,
                'OriginLocation' => $origin !== '' ? ['LocationCode' => $origin] : null,
                'DestinationLocation' => $dest !== '' ? ['LocationCode' => $dest] : null,
                'MarketingAirline' => $mkt !== '' ? array_filter([
                    'Code' => $mkt,
                    'FlightNumber' => $flightNumber !== '' ? $flightNumber : null,
                ]) : null,
                'OperatingAirline' => $op !== '' ? ['Code' => $op] : null,
                'FlightNumber' => $flightNumber !== '' ? $flightNumber : null,
                'ResBookDesigCode' => $bookingClass !== '' ? $bookingClass : null,
                'ClassOfService' => $bookingClass !== '' ? $bookingClass : null,
                'CabinCode' => $cabin !== '' ? $cabin : null,
                'FareBasisCode' => $fareBasis !== '' ? $fareBasis : null,
            ], static fn ($v) => $v !== null && $v !== [] && $v !== '');

            $odis[] = [
                'FlightSegment' => $flightSegment,
            ];
        }

        $shopContext = $this->sanitizeShopContext($mergedShopSource);
        $fareBasisCodes = $this->fareBasisCodesFromSegmentsAndContext($segments, $shopContext);
        $pricingInformationEnvelope = $this->buildPricingInformationLinkageEnvelope($shopContext);
        $itinRefForPayload = $this->firstContextScalar($shopContext, ['itinerary_ref', 'itinerary_id']);

        $travelerInfoSummary = [
            'AirTravelerAvail' => [
                array_filter([
                    'PassengerTypeQuantity' => array_values(array_filter([
                        $ptcCounts['ADT'] > 0 ? ['Code' => 'ADT', 'Quantity' => $ptcCounts['ADT']] : null,
                        $ptcCounts['CHD'] > 0 ? ['Code' => 'CHD', 'Quantity' => $ptcCounts['CHD']] : null,
                        $ptcCounts['INF'] > 0 ? ['Code' => 'INF', 'Quantity' => $ptcCounts['INF']] : null,
                    ])),
                ], static fn ($v) => $v !== null && $v !== []),
            ],
        ];

        $payload = [
            '_ota_provider' => SupplierProvider::Sabre->value,
            '_ota_payload_schema' => 'sabre_revalidate_v4_shop_flights_v1',
            'OTA_AirLowFareSearchRQ' => [
                'POS' => [
                    'Source' => [
                        ['RequestorID' => array_filter([
                            'Type' => '1',
                            'CompanyName' => ['Code' => 'TN'],
                        ])],
                    ],
                ],
                'OriginDestinationInformation' => $odis,
                'TravelPreferences' => array_filter([
                    'ValidInterlineTicket' => true,
                    'CabinPref' => $this->collectCabinPrefs($segments),
                    'VendorPref' => $validatingCarrier !== ''
                        ? [['Code' => $validatingCarrier, 'PreferLevel' => 'Preferred']]
                        : null,
                ], static fn ($v) => $v !== null && $v !== []),
                'TravelerInfoSummary' => array_filter([
                    'PriceRequestInformation' => array_filter([
                        'CurrencyCode' => $currency !== '' ? $currency : null,
                        'TPA_Extensions' => array_filter([
                            'BrandedFareIndicators' => [
                                'singleBrandedFare' => true,
                            ],
                        ], static fn ($v) => $v !== null && $v !== []),
                    ], static fn ($v) => $v !== null && $v !== ''),
                    'AirTravelerAvail' => $travelerInfoSummary['AirTravelerAvail'],
                ], static fn ($v) => $v !== null && $v !== []),
            ],
            'itinerary' => array_filter([
                'id' => $itinRefForPayload,
                'segments' => $this->buildClientItinerarySegments($segments),
            ], static fn ($v) => $v !== null && $v !== '' && $v !== []),
            'shop_context' => $shopContext !== [] ? $shopContext : null,
            'pricingInformation' => $pricingInformationEnvelope !== [] ? [$pricingInformationEnvelope] : null,
            'fare_context' => array_filter([
                'selected_offer_id' => $selectedOfferId !== '' ? $selectedOfferId : null,
                'supplier_offer_id' => $supplierOfferId !== '' ? $supplierOfferId : null,
                'validating_carrier' => $validatingCarrier !== '' ? $validatingCarrier : null,
                'currency' => $currency !== '' ? $currency : null,
                'expected_total' => $amount > 0 ? $amount : null,
                'fare_basis_codes' => $fareBasisCodes !== [] ? $fareBasisCodes : null,
                'carrier_chain' => is_array($shopContext['carrier_chain'] ?? null) ? $shopContext['carrier_chain'] : null,
                'pricing_information_ref' => $this->firstContextScalar($shopContext, [
                    'pricing_information_ref', 'pricing_0_ref', 'pricing_0_pricingRef', 'pricing_0_pricingInformationRef',
                    'pricing_0_offerItemId', 'pricing_0_offerItemRef',
                ]),
                'pricing_information_id' => $this->firstContextScalar($shopContext, ['pricing_information_id', 'pricing_0_id']),
                'pricing_subsource' => $this->firstContextScalar($shopContext, ['pricing_subsource', 'pricing_0_pricingSubSource', 'pricing_0_pricingSubsource']),
                'fare_source' => $this->firstContextScalar($shopContext, ['fare_source', 'pricing_0_fare_source']),
                'offer_ref' => $this->firstContextScalar($shopContext, ['offer_ref', 'pricing_0_offer_ref', 'pricing_0_offerItemRef']),
                'offer_id' => $this->firstContextScalar($shopContext, ['offer_id', 'pricing_0_offer_id', 'pricing_0_offerItemId']),
                'order_ref' => $this->firstContextScalar($shopContext, ['order_ref', 'pricing_0_order_ref', 'pricing_0_order_id']),
                'itinerary_reference' => $itinRefForPayload ?? $this->firstContextScalar($shopContext, ['itinerary_ref', 'itinerary_id']),
                'leg_refs' => is_array($shopContext['leg_refs'] ?? null) ? $shopContext['leg_refs'] : null,
                'schedule_refs' => is_array($shopContext['schedule_refs'] ?? null) ? $shopContext['schedule_refs'] : null,
                'fare_component_refs' => is_array($shopContext['fare_component_refs'] ?? null) ? $shopContext['fare_component_refs'] : null,
            ], static fn ($v) => $v !== null && $v !== ''),
            'passenger_counts' => array_filter($ptcCounts, static fn (int $n): bool => $n > 0),
        ];
        $payload['_ota_revalidate_payload_style'] = $style;

        return match ($style) {
            'bfm_revalidate_minimal_segments' => $this->withMinimalSegmentsStyle($payload),
            'bfm_revalidate_with_pricing_context' => $this->withPricingContextStyle($payload, $internalDraft),
            'bfm_revalidate_original_like' => $this->withOriginalLikeStyle($payload),
            default => $payload,
        };
    }

    /**
     * @param  array<string, mixed>  $payload
     * @return array<string, mixed>
     */
    protected function withMinimalSegmentsStyle(array $payload): array
    {
        unset($payload['itinerary'], $payload['shop_context'], $payload['pricingInformation'], $payload['fare_context'], $payload['passenger_counts']);
        $payload['_ota_payload_schema'] = 'sabre_revalidate_v4_shop_flights_minimal_segments_v1';

        return $payload;
    }

    /**
     * @param  array<string, mixed>  $payload
     * @return array<string, mixed>
     */
    protected function withOriginalLikeStyle(array $payload): array
    {
        $payload['_ota_payload_schema'] = 'sabre_revalidate_v4_shop_flights_original_like_v1';

        return $payload;
    }

    /**
     * @param  array<string, mixed>  $payload
     * @param  array<string, mixed>  $internalDraft
     * @return array<string, mixed>
     */
    protected function withPricingContextStyle(array $payload, array $internalDraft): array
    {
        $merged = $this->mergeDraftShopSources($internalDraft);
        $shop = $this->sanitizeShopContext($merged);
        $segments = is_array($internalDraft['segments'] ?? null) ? $internalDraft['segments'] : [];
        usort($segments, static function (array $a, array $b): int {
            return strcmp(
                (string) ($a['departure_at'] ?? $a['depart_at'] ?? ''),
                (string) ($b['departure_at'] ?? $b['depart_at'] ?? '')
            );
        });
        $fareBasisCodes = $this->fareBasisCodesFromSegmentsAndContext($segments, $shop);
        $validatingCarrier = strtoupper(trim((string) ($internalDraft['validating_carrier'] ?? '')));
        if ($validatingCarrier === '') {
            $vc = $this->firstContextScalar($shop, ['validating_carrier', 'validating_carrier_code']);
            $validatingCarrier = $vc !== null && $vc !== '' ? strtoupper($vc) : '';
        }
        $bookingClasses = [];
        foreach ($segments as $seg) {
            if (! is_array($seg)) {
                continue;
            }
            $bc = strtoupper(trim((string) ($seg['booking_class'] ?? '')));
            if ($bc !== '') {
                $bookingClasses[] = $bc;
            }
        }
        $legRefs = is_array($shop['leg_refs'] ?? null) ? array_values(array_filter($shop['leg_refs'], static fn ($x): bool => is_scalar($x))) : [];
        $schedRefs = is_array($shop['schedule_refs'] ?? null) ? array_values(array_filter($shop['schedule_refs'], static fn ($x): bool => is_scalar($x))) : [];
        $fcRefs = is_array($shop['fare_component_refs'] ?? null) ? array_slice(array_values($shop['fare_component_refs']), 0, 24) : [];
        $fcdRefs = is_array($shop['fare_component_desc_refs'] ?? null) ? array_slice(array_values($shop['fare_component_desc_refs']), 0, 24) : [];
        $itinRef = $this->firstContextScalar($shop, ['itinerary_ref', 'itinerary_id']);
        $recon = array_filter([
            'itineraryGroupIndex' => (int) ($shop['itinerary_group_index'] ?? 0),
            'itineraryIndex' => (int) ($shop['itinerary_index'] ?? 0),
            'itineraryPricingIndex' => (int) ($shop['itinerary_pricing_index'] ?? 0),
            'pricingInformationIndex' => (int) ($shop['pricing_information_index'] ?? 0),
            'itineraryReference' => $itinRef,
            'validatingCarrierCode' => $validatingCarrier !== '' ? $validatingCarrier : null,
            'fareBasisCodes' => $fareBasisCodes !== [] ? array_values(array_slice($fareBasisCodes, 0, 12)) : null,
            'bookingClasses' => $bookingClasses !== [] ? array_values(array_slice($bookingClasses, 0, 12)) : null,
            'segmentNumbers' => $segments !== [] ? range(1, count($segments)) : null,
            'legRefs' => $legRefs !== [] ? $legRefs : null,
            'scheduleRefs' => $schedRefs !== [] ? $schedRefs : null,
            'fareComponentRefs' => $fcRefs !== [] ? $fcRefs : null,
            'fareComponentDescRefs' => $fcdRefs !== [] ? $fcdRefs : null,
        ], static fn ($v) => $v !== null && $v !== [] && $v !== '');

        $pi = is_array($payload['pricingInformation'] ?? null) ? $payload['pricingInformation'] : [];
        $first = isset($pi[0]) && is_array($pi[0]) ? $pi[0] : [];
        $payload['pricingInformation'] = [array_merge($first, $recon)];
        $payload['_ota_payload_schema'] = 'sabre_revalidate_v4_shop_flights_pricing_context_v1';

        return $payload;
    }

    protected function normalizeRevalidatePayloadStyle(?string $override): string
    {
        $raw = $override !== null && trim($override) !== ''
            ? trim($override)
            : (string) config('suppliers.sabre.revalidate_payload_style', 'bfm_revalidate_v1');
        $allowed = [
            'bfm_revalidate_v1',
            'bfm_revalidate_minimal_segments',
            'bfm_revalidate_with_pricing_context',
            'bfm_revalidate_original_like',
            'client_gds_revalidate_v1',
            'client_gds_revalidate_without_pos',
            'client_gds_revalidate_without_travel_preferences',
            'client_gds_revalidate_segments_only',
            'shop_replay_selected_itinerary_v1',
            'iati_like_bfm_revalidate_v1',
            'manager_like_bfm_revalidate_v1',
            'manager_like_bfm_revalidate_enriched_v1',
        ];

        return in_array($raw, $allowed, true) ? $raw : 'bfm_revalidate_v1';
    }

    /**
     * Remove internal {@code _ota_*} envelope keys before POSTing to Sabre (never sent on the wire).
     *
     * @param  array<string, mixed>  $payload
     * @return array<string, mixed>
     */
    public function wireableRequestPayload(array $payload): array
    {
        $out = [];
        foreach ($payload as $k => $v) {
            if (is_string($k) && str_starts_with($k, '_ota_')) {
                continue;
            }
            $out[$k] = $v;
        }

        return $out;
    }

    /**
     * Deep-copy preview/export tree with disallowed keys removed (PCC, auth, passenger/contact blobs).
     *
     * @param  array<string, mixed>  $payload  Wireable request body only
     * @return array<string, mixed>
     */
    public function sanitizeRevalidatePreviewTree(array $payload): array
    {
        $blockedKeys = [
            'authorization', 'accesstoken', 'access_token', 'client_secret',
            'password', 'epr', 'pseudocitycode', 'pseudo_city_code', 'pcc',
            'email', 'phone', 'passport', 'passportnumber', 'passport_number',
            'dateofbirth', 'date_of_birth', 'dob', 'givenname', 'surname', 'firstname', 'lastname',
            'address', 'street', 'contactinfo', 'contact_info',
        ];

        return $this->sanitizeTree($payload, $blockedKeys);
    }

    /**
     * Structural diagnostics for inspect output (no PII).
     *
     * @param  array<string, mixed>  $payload  Full builder payload (may include {@code _ota_*} keys)
     * @return array<string, mixed>
     */
    public function structuralPayloadDiagnostics(array $payload): array
    {
        $rootKeys = array_values(array_filter(array_keys($payload), static function ($k): bool {
            return is_string($k) && ! str_starts_with($k, '_ota_');
        }));
        $hasOta = array_key_exists('OTA_AirLowFareSearchRQ', $payload);
        $hasRevalidateItin = array_key_exists('RevalidateItineraryRQ', $payload)
            || array_key_exists('revalidateItineraryRQ', $payload);

        $firstSeg = null;
        $segmentCount = 0;

        if ($hasOta) {
            $odis = is_array(data_get($payload, 'OTA_AirLowFareSearchRQ.OriginDestinationInformation'))
                ? data_get($payload, 'OTA_AirLowFareSearchRQ.OriginDestinationInformation') : [];
            $segmentCount = is_array($odis) ? count($odis) : 0;
            if ($segmentCount > 0 && is_array($odis[0] ?? null)) {
                $firstOdi = $odis[0];
                $iatiFlights = data_get($firstOdi, 'TPA_Extensions.Flight');
                if (is_array($iatiFlights) && isset($iatiFlights[0]) && is_array($iatiFlights[0])) {
                    $firstSeg = $iatiFlights[0];
                } else {
                    $firstSeg = is_array($firstOdi['FlightSegment'] ?? null) ? $firstOdi['FlightSegment'] : [];
                }
            }
        } elseif ($hasRevalidateItin) {
            $rq = is_array($payload['RevalidateItineraryRQ'] ?? null)
                ? $payload['RevalidateItineraryRQ']
                : (is_array($payload['revalidateItineraryRQ'] ?? null) ? $payload['revalidateItineraryRQ'] : []);
            $rows = is_array($rq['FlightSegments'] ?? null) ? $rq['FlightSegments'] : [];
            $segmentCount = count($rows);
            if ($segmentCount > 0 && is_array($rows[0] ?? null)) {
                $firstSeg = $rows[0];
            }
        } else {
            $rows = is_array($payload['flightSegments'] ?? null) ? $payload['flightSegments'] : [];
            $segmentCount = count($rows);
            if ($segmentCount > 0 && is_array($rows[0] ?? null)) {
                $firstSeg = $rows[0];
            }
        }

        $firstKeys = is_array($firstSeg) ? array_values(array_filter(array_keys($firstSeg), static fn ($k): bool => is_string($k))) : [];

        $has = static function (array $seg, array $paths): bool {
            foreach ($paths as $p) {
                $v = data_get($seg, $p);
                if (is_string($v) && trim($v) !== '') {
                    return true;
                }
                if (is_numeric($v) && (string) $v !== '') {
                    return true;
                }
                if (is_array($v) && $v !== []) {
                    return true;
                }
            }

            return false;
        };

        $seg = is_array($firstSeg) ? $firstSeg : [];

        $style = (string) ($payload['_ota_revalidate_payload_style'] ?? '');
        $iatiFlights = $this->collectIatiExtensionFlightsFromPayload($payload);
        $flightNodeCount = count($iatiFlights);
        $classValues = [];
        foreach ($iatiFlights as $flight) {
            if (! is_array($flight)) {
                continue;
            }
            $cos = strtoupper(trim((string) ($flight['ClassOfService'] ?? '')));
            if ($cos !== '') {
                $classValues[] = $cos;
            }
        }
        $pccPresent = $this->payloadHasPseudoCityCode($payload);
        $dataSources = data_get($payload, 'OTA_AirLowFareSearchRQ.TravelPreferences.TPA_Extensions.DataSources');
        $hasDataSources = is_array($dataSources)
            && ($dataSources['NDC'] ?? null) === 'Disable'
            && ($dataSources['ATPCO'] ?? null) === 'Enable'
            && ($dataSources['LCC'] ?? null) === 'Enable';
        $intelliRoot = trim((string) data_get(
            $payload,
            'OTA_AirLowFareSearchRQ.TPA_Extensions.IntelliSellTransaction.RequestType.Name',
            ''
        ));
        $intelliTraveler = trim((string) data_get(
            $payload,
            'OTA_AirLowFareSearchRQ.TravelerInfoSummary.TPA_Extensions.IntelliSellTransaction.RequestType.Name',
            ''
        ));
        $intelliName = $intelliRoot !== '' ? $intelliRoot : $intelliTraveler;

        return [
            'revalidate_payload_style' => $style !== '' ? $style : null,
            'root_keys' => array_slice($rootKeys, 0, 32),
            'has_ota_air_low_fare_search_rq' => $hasOta,
            'has_revalidate_itinerary' => $hasRevalidateItin,
            'has_flight_segments' => $segmentCount > 0 || $flightNodeCount > 0,
            'segment_count' => $flightNodeCount > 0 ? $flightNodeCount : $segmentCount,
            'first_segment_keys' => array_slice($firstKeys, 0, 40),
            'has_number' => $has($seg, ['Number', 'number', 'SegmentNumber', 'segmentNumber']),
            'has_departure_datetime' => $has($seg, ['DepartureDateTime', 'departureDateTime', 'departure_at']),
            'has_arrival_datetime' => $has($seg, ['ArrivalDateTime', 'arrivalDateTime', 'arrival_at']),
            'has_class_of_service' => $has($seg, ['ClassOfService', 'classOfService', 'ResBookDesigCode']),
            'has_origin_location' => $has($seg, ['OriginLocation', 'originLocation', 'origin']),
            'has_destination_location' => $has($seg, ['DestinationLocation', 'destinationLocation', 'destination']),
            'has_marketing_airline' => $has($seg, ['MarketingAirline', 'marketingAirline', 'AirlineMarketing'])
                || is_array(data_get($seg, 'Airline.Marketing')),
            'has_operating_airline' => $has($seg, ['OperatingAirline', 'operatingAirline', 'AirlineOperating'])
                || is_array(data_get($seg, 'Airline.Operating')),
            'has_flight_number' => $has($seg, ['FlightNumber', 'flightNumber', 'Number', 'number'])
                || trim((string) data_get($seg, 'MarketingAirline.FlightNumber', '')) !== '',
            'has_pcc' => $pccPresent,
            'has_datasources' => $hasDataSources,
            'has_intellisell_50itins' => $intelliName === '50ITINS',
            'flight_node_count' => $flightNodeCount,
            'class_of_service_values_sanitized' => implode('|', array_slice($classValues, 0, 12)),
            ...$this->iatiLikeGroupingDiagnosticsForPayload($payload, $style, $hasOta),
            ...$this->iatiLikeWireShapeDiagnosticsForPayload($payload, $style, $hasOta, $iatiFlights),
        ];
    }

    /**
     * B72: Safe IATI-like wire shape diagnostics (flight numbers, Airline node, seats, IntelliSell placement, Version key).
     *
     * @param  list<array<string, mixed>>  $iatiFlights
     * @return array<string, mixed>
     */
    protected function iatiLikeWireShapeDiagnosticsForPayload(
        array $payload,
        string $style,
        bool $hasOta,
        array $iatiFlights,
    ): array {
        if ($style !== 'iati_like_bfm_revalidate_v1' || ! $hasOta) {
            return [];
        }

        $rq = is_array($payload['OTA_AirLowFareSearchRQ'] ?? null) ? $payload['OTA_AirLowFareSearchRQ'] : [];
        $hasVersion = array_key_exists('Version', $rq);
        $hasAtVersion = array_key_exists('@Version', $rq);
        $versionKeyType = match (true) {
            $hasVersion && $hasAtVersion => 'both',
            $hasVersion => 'Version',
            $hasAtVersion => '@Version',
            default => 'missing',
        };

        $seatsRaw = data_get($payload, 'OTA_AirLowFareSearchRQ.TravelerInfoSummary.SeatsRequested');
        $seatsRequestedType = match (true) {
            is_array($seatsRaw) => 'array',
            is_int($seatsRaw) || is_float($seatsRaw) || (is_string($seatsRaw) && trim($seatsRaw) !== '') => 'scalar',
            default => 'missing',
        };

        $intelliRoot = trim((string) data_get(
            $payload,
            'OTA_AirLowFareSearchRQ.TPA_Extensions.IntelliSellTransaction.RequestType.Name',
            ''
        )) !== '';
        $intelliTraveler = trim((string) data_get(
            $payload,
            'OTA_AirLowFareSearchRQ.TravelerInfoSummary.TPA_Extensions.IntelliSellTransaction.RequestType.Name',
            ''
        )) !== '';
        $intelliLocation = match (true) {
            $intelliRoot && $intelliTraveler => 'both',
            $intelliRoot => 'root',
            $intelliTraveler => 'traveler_info',
            default => 'missing',
        };

        $numberValues = [];
        $usesIndexSequence = true;
        foreach ($iatiFlights as $idx => $flight) {
            if (! is_array($flight)) {
                continue;
            }
            $num = trim((string) ($flight['Number'] ?? ''));
            if ($num !== '') {
                $numberValues[] = substr($num, 0, 16);
            }
            if ($num !== (string) ($idx + 1)) {
                $usesIndexSequence = false;
            }
        }

        $firstFlight = is_array($iatiFlights[0] ?? null) ? $iatiFlights[0] : [];
        $airline = is_array($firstFlight['Airline'] ?? null) ? $firstFlight['Airline'] : [];

        return [
            'flight_number_values_sanitized' => implode('|', array_slice($numberValues, 0, 12)),
            'flight_node_number_uses_actual_flight_number' => $numberValues !== [] && ! $usesIndexSequence,
            'has_iati_airline_node' => is_array($airline['Marketing'] ?? null),
            'seats_requested_type' => $seatsRequestedType,
            'intellisell_location' => $intelliLocation,
            'version_key_type' => $versionKeyType,
        ];
    }

    /**
     * B71: Safe IATI-like ODI grouping diagnostics (no PCC / PII).
     *
     * @return array<string, mixed>
     */
    protected function iatiLikeGroupingDiagnosticsForPayload(array $payload, string $style, bool $hasOta): array
    {
        if ($style !== 'iati_like_bfm_revalidate_v1' || ! $hasOta) {
            return [];
        }

        $stored = is_array($payload['_ota_iati_grouping'] ?? null) ? $payload['_ota_iati_grouping'] : [];
        if ($stored !== []) {
            return [
                'odi_count' => (int) ($stored['odi_count'] ?? 0),
                'grouped_flight_nodes_per_odi' => (string) ($stored['grouped_flight_nodes_per_odi'] ?? ''),
                'iati_like_segments_grouped' => (bool) ($stored['iati_like_segments_grouped'] ?? false),
                'max_connection_gap_minutes_sanitized' => isset($stored['max_connection_gap_minutes_sanitized'])
                    ? (int) $stored['max_connection_gap_minutes_sanitized'] : null,
                'max_connection_gap_bucket' => (string) ($stored['max_connection_gap_bucket'] ?? 'unknown'),
            ];
        }

        $odis = is_array(data_get($payload, 'OTA_AirLowFareSearchRQ.OriginDestinationInformation'))
            ? data_get($payload, 'OTA_AirLowFareSearchRQ.OriginDestinationInformation') : [];
        $perOdi = [];
        foreach ($odis as $row) {
            if (! is_array($row)) {
                continue;
            }
            $nodes = data_get($row, 'TPA_Extensions.Flight');
            $perOdi[] = is_array($nodes) ? count($nodes) : 0;
        }
        $flightNodeCount = array_sum($perOdi);
        $odiCount = count($perOdi);
        $grouped = $flightNodeCount > $odiCount || in_array(true, array_map(static fn (int $n): bool => $n > 1, $perOdi), true);

        return [
            'odi_count' => $odiCount,
            'grouped_flight_nodes_per_odi' => implode('|', $perOdi),
            'iati_like_segments_grouped' => $grouped,
            'max_connection_gap_minutes_sanitized' => null,
            'max_connection_gap_bucket' => 'unknown',
        ];
    }

    /**
     * @param  array<string, mixed>  $payload
     * @return list<array<string, mixed>>
     */
    protected function collectIatiExtensionFlightsFromPayload(array $payload): array
    {
        $odis = is_array(data_get($payload, 'OTA_AirLowFareSearchRQ.OriginDestinationInformation'))
            ? data_get($payload, 'OTA_AirLowFareSearchRQ.OriginDestinationInformation') : [];
        $flights = [];
        foreach ($odis as $row) {
            if (! is_array($row)) {
                continue;
            }
            $nodes = data_get($row, 'TPA_Extensions.Flight');
            if (! is_array($nodes)) {
                continue;
            }
            foreach ($nodes as $flight) {
                if (is_array($flight)) {
                    $flights[] = $flight;
                }
            }
        }

        return $flights;
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    protected function payloadHasPseudoCityCode(array $payload): bool
    {
        $sources = data_get($payload, 'OTA_AirLowFareSearchRQ.POS.Source');
        if (! is_array($sources)) {
            return false;
        }
        foreach ($sources as $source) {
            if (! is_array($source)) {
                continue;
            }
            if (trim((string) ($source['PseudoCityCode'] ?? '')) !== '') {
                return true;
            }
        }

        return false;
    }

    /**
     * B17 client-style envelope: {@code RevalidateItineraryRQ} + {@code FlightSegments} (PascalCase segment fields).
     *
     * @param  array<string, mixed>  $internalDraft
     * @return array<string, mixed>
     */
    protected function buildClientGdsRevalidateV1Envelope(array $internalDraft): array
    {
        $mergedShopSource = $this->mergeDraftShopSources($internalDraft);
        $shopContext = $this->sanitizeShopContext($mergedShopSource);

        $segments = is_array($internalDraft['segments'] ?? null) ? $internalDraft['segments'] : [];
        usort($segments, static function (array $a, array $b): int {
            return strcmp(
                (string) ($a['departure_at'] ?? $a['depart_at'] ?? ''),
                (string) ($b['departure_at'] ?? $b['depart_at'] ?? '')
            );
        });

        $passengers = is_array($internalDraft['passengers'] ?? null) ? $internalDraft['passengers'] : [];
        $ptcCounts = ['ADT' => 0, 'CHD' => 0, 'INF' => 0];
        foreach ($passengers as $p) {
            if (! is_array($p)) {
                continue;
            }
            $code = strtoupper(trim((string) ($p['type'] ?? 'ADT')));
            $code = match ($code) {
                'CHD', 'CH', 'CNN' => 'CHD',
                'INF', 'IN', 'INS' => 'INF',
                default => 'ADT',
            };
            $ptcCounts[$code]++;
        }
        if (array_sum($ptcCounts) === 0) {
            $ptcCounts['ADT'] = max(1, count($passengers));
        }

        $fare = is_array($internalDraft['fare'] ?? null) ? $internalDraft['fare'] : [];
        $currency = trim((string) ($fare['currency'] ?? ''));
        $validatingCarrier = strtoupper(trim((string) ($internalDraft['validating_carrier'] ?? '')));
        if ($validatingCarrier === '') {
            $vc = $this->firstContextScalar($shopContext, ['validating_carrier', 'validating_carrier_code']);
            $validatingCarrier = $vc !== null && $vc !== '' ? strtoupper($vc) : '';
        }

        $fareBasisCodes = $this->fareBasisCodesFromSegmentsAndContext($segments, $shopContext);
        $fcRefs = is_array($shopContext['fare_component_refs'] ?? null)
            ? array_slice(array_values(array_filter($shopContext['fare_component_refs'], static fn ($x): bool => is_scalar($x))), 0, 24) : [];
        $bagRefs = [];
        foreach (['baggage_allowance_refs', 'baggage_refs', 'baggage_ref_list'] as $bk) {
            $raw = $shopContext[$bk] ?? null;
            if (is_array($raw)) {
                foreach ($raw as $x) {
                    if (is_scalar($x) && trim((string) $x) !== '') {
                        $bagRefs[] = substr(trim((string) $x), 0, self::SAFE_MAX);
                    }
                }
            }
        }
        $bagRefs = array_slice(array_values(array_unique($bagRefs)), 0, 12);

        $flightSegments = [];
        foreach ($segments as $idx => $seg) {
            if (! is_array($seg)) {
                continue;
            }
            $depAt = (string) ($seg['departure_at'] ?? $seg['depart_at'] ?? '');
            $arrAt = (string) ($seg['arrival_at'] ?? $seg['arrive_at'] ?? '');
            $origin = strtoupper(trim((string) ($seg['origin'] ?? '')));
            $dest = strtoupper(trim((string) ($seg['destination'] ?? '')));
            $mkt = strtoupper(trim((string) ($seg['carrier'] ?? $seg['airline_code'] ?? '')));
            $op = strtoupper(trim((string) ($seg['operating_airline_code'] ?? '')));
            $flightNumber = trim((string) ($seg['flight_number'] ?? $seg['flight_no'] ?? ''));
            $bookingClass = strtoupper(trim((string) ($seg['booking_class'] ?? '')));
            $fareBasis = strtoupper(trim((string) ($seg['fare_basis_code'] ?? '')));
            if ($op === '' && $mkt !== '') {
                $op = $mkt;
            }

            $n = (string) ($idx + 1);
            $mktAirline = array_filter([
                'Code' => $mkt !== '' ? $mkt : null,
                'FlightNumber' => $flightNumber !== '' ? $flightNumber : null,
            ], static fn ($v) => $v !== null && $v !== '');
            $opAirline = $op !== '' ? ['Code' => $op] : null;

            $flightSegments[] = array_filter([
                'Number' => $n,
                'DepartureDateTime' => $depAt !== '' ? $depAt : null,
                'ArrivalDateTime' => $arrAt !== '' ? $arrAt : null,
                'ClassOfService' => $bookingClass !== '' ? $bookingClass : null,
                'OriginLocation' => $origin !== '' ? ['LocationCode' => $origin] : null,
                'DestinationLocation' => $dest !== '' ? ['LocationCode' => $dest] : null,
                'MarketingAirline' => $mktAirline !== [] ? $mktAirline : null,
                'OperatingAirline' => $opAirline,
                'FlightNumber' => $flightNumber !== '' ? $flightNumber : null,
                'FareBasisCode' => $fareBasis !== '' ? $fareBasis : null,
            ], static fn ($v) => $v !== null && $v !== [] && $v !== '');
        }

        $ptq = array_values(array_filter([
            $ptcCounts['ADT'] > 0 ? ['Code' => 'ADT', 'Quantity' => $ptcCounts['ADT']] : null,
            $ptcCounts['CHD'] > 0 ? ['Code' => 'CHD', 'Quantity' => $ptcCounts['CHD']] : null,
            $ptcCounts['INF'] > 0 ? ['Code' => 'INF', 'Quantity' => $ptcCounts['INF']] : null,
        ]));

        $rq = array_filter([
            'Version' => '4.0.0',
            'POS' => [
                'Source' => [
                    ['RequestorID' => array_filter([
                        'Type' => '1',
                        'CompanyName' => ['Code' => 'TN'],
                    ])],
                ],
            ],
            'TravelPreferences' => array_filter([
                'ValidInterlineTicket' => true,
                'VendorPref' => $validatingCarrier !== ''
                    ? [['Code' => $validatingCarrier, 'PreferLevel' => 'Preferred']]
                    : null,
            ], static fn ($v) => $v !== null && $v !== []),
            'TravelerInfoSummary' => [
                'AirTravelerAvail' => [
                    array_filter([
                        'PassengerTypeQuantity' => $ptq,
                    ], static fn ($v) => $v !== null && $v !== []),
                ],
            ],
            'CurrencyCode' => $currency !== '' ? $currency : null,
            'ValidatingCarrier' => $validatingCarrier !== '' ? $validatingCarrier : null,
            'PassengerCounts' => array_filter($ptcCounts, static fn (int $n): bool => $n > 0),
            'FlightSegments' => $flightSegments !== [] ? $flightSegments : null,
            'FareBasisCodes' => $fareBasisCodes !== [] ? $fareBasisCodes : null,
            'FareComponentRefs' => $fcRefs !== [] ? $fcRefs : null,
            'BaggageAllowanceRefs' => $bagRefs !== [] ? $bagRefs : null,
        ], static fn ($v) => $v !== null && $v !== [] && $v !== '');

        return [
            '_ota_provider' => SupplierProvider::Sabre->value,
            '_ota_payload_schema' => 'sabre_client_gds_revalidate_v4_shop_flights_v1',
            '_ota_revalidate_payload_style' => 'client_gds_revalidate_v1',
            'RevalidateItineraryRQ' => $rq,
        ];
    }

    /**
     * B22: Apply {@code client_gds_revalidate_*} style variants on top of {@see buildClientGdsRevalidateV1Envelope()}.
     *
     * @param  array<string, mixed>  $envelope  Full builder envelope including {@code _ota_*} keys
     * @return array<string, mixed>
     */
    protected function applyClientGdsRevalidateStyleVariant(array $envelope, string $style): array
    {
        if ($style === 'client_gds_revalidate_v1') {
            return $envelope;
        }

        $rq = is_array($envelope['RevalidateItineraryRQ'] ?? null) ? $envelope['RevalidateItineraryRQ'] : [];

        if ($style === 'client_gds_revalidate_without_pos') {
            unset($rq['POS']);
            $envelope['RevalidateItineraryRQ'] = array_filter($rq, static fn ($v) => $v !== null && $v !== [] && $v !== '');
            $envelope['_ota_payload_schema'] = 'sabre_client_gds_revalidate_v4_without_pos_v1';
            $envelope['_ota_revalidate_payload_style'] = $style;

            return $envelope;
        }

        if ($style === 'client_gds_revalidate_without_travel_preferences') {
            unset($rq['TravelPreferences']);
            $envelope['RevalidateItineraryRQ'] = array_filter($rq, static fn ($v) => $v !== null && $v !== [] && $v !== '');
            $envelope['_ota_payload_schema'] = 'sabre_client_gds_revalidate_v4_without_travel_preferences_v1';
            $envelope['_ota_revalidate_payload_style'] = $style;

            return $envelope;
        }

        if ($style === 'client_gds_revalidate_segments_only') {
            $envelope['RevalidateItineraryRQ'] = array_filter([
                'Version' => $rq['Version'] ?? '4.0.0',
                'FlightSegments' => $rq['FlightSegments'] ?? null,
                'PassengerCounts' => $rq['PassengerCounts'] ?? null,
                'CurrencyCode' => $rq['CurrencyCode'] ?? null,
            ], static fn ($v) => $v !== null && $v !== [] && $v !== '');
            $envelope['_ota_payload_schema'] = 'sabre_client_gds_revalidate_v4_segments_only_v1';
            $envelope['_ota_revalidate_payload_style'] = $style;

            return $envelope;
        }

        return $envelope;
    }

    /**
     * B69/B71/B72: IATI-like BFM revalidate — wire root is {@code OTA_AirLowFareSearchRQ} only; ODI legs group consecutive
     * segments unless connection gap {@code >24h} or route continuity breaks (Binham IATI parity).
     *
     * @param  array<string, mixed>  $internalDraft
     * @return array<string, mixed>
     */
    protected function buildIatiLikeBfmRevalidateV1Envelope(array $internalDraft): array
    {
        $segments = is_array($internalDraft['segments'] ?? null) ? $internalDraft['segments'] : [];
        usort($segments, static function (array $a, array $b): int {
            return strcmp(
                (string) ($a['departure_at'] ?? $a['depart_at'] ?? ''),
                (string) ($b['departure_at'] ?? $b['depart_at'] ?? '')
            );
        });

        $ptcCounts = $this->passengerTypeCountsForIatiRevalidate($internalDraft);
        $seatsRequested = max(1, array_sum($ptcCounts));

        $pcc = $this->resolvePseudoCityCodeFromDraft($internalDraft);
        $sourceRow = [
            'RequestorID' => [
                'Type' => '1',
                'ID' => '1',
                'CompanyName' => ['Code' => 'TN'],
            ],
        ];
        if ($pcc !== '') {
            $sourceRow['PseudoCityCode'] = $pcc;
        }

        $flightNodes = [];
        foreach ($segments as $seg) {
            if (! is_array($seg)) {
                continue;
            }
            $node = $this->buildIatiLikeFlightNodeFromSegment($seg);
            if ($node !== []) {
                $flightNodes[] = $node;
            }
        }

        $grouped = $this->groupIatiLikeOriginDestinationInformation($flightNodes);
        $odis = $grouped['odis'];

        $passengerTypeQuantity = array_values(array_filter([
            $ptcCounts['ADT'] > 0 ? ['Code' => 'ADT', 'Quantity' => $ptcCounts['ADT']] : null,
            $ptcCounts['CNN'] > 0 ? ['Code' => 'CNN', 'Quantity' => $ptcCounts['CNN']] : null,
            $ptcCounts['INF'] > 0 ? ['Code' => 'INF', 'Quantity' => $ptcCounts['INF']] : null,
        ]));

        return [
            '_ota_provider' => SupplierProvider::Sabre->value,
            '_ota_payload_schema' => 'sabre_iati_like_bfm_revalidate_v1',
            '_ota_revalidate_payload_style' => 'iati_like_bfm_revalidate_v1',
            '_ota_iati_grouping' => $grouped['meta'],
            'OTA_AirLowFareSearchRQ' => [
                'Version' => '4',
                'POS' => [
                    'Source' => [$sourceRow],
                ],
                'TPA_Extensions' => [
                    'IntelliSellTransaction' => [
                        'RequestType' => ['Name' => '50ITINS'],
                    ],
                ],
                'OriginDestinationInformation' => $odis,
                'TravelPreferences' => [
                    'ValidInterlineTicket' => true,
                    'TPA_Extensions' => [
                        'DataSources' => [
                            'NDC' => 'Disable',
                            'ATPCO' => 'Enable',
                            'LCC' => 'Enable',
                        ],
                    ],
                ],
                'TravelerInfoSummary' => [
                    'SeatsRequested' => [$seatsRequested],
                    'AirTravelerAvail' => [
                        ['PassengerTypeQuantity' => $passengerTypeQuantity],
                    ],
                ],
            ],
        ];
    }

    /**
     * CERT/diagnostic: Sabre manager revalidation sample parity — {@code iati_like_bfm_revalidate_v1} plus
     * {@code TravelPreferences.TPA_Extensions.VerificationItinCallLogic.Value=B}. OTA-only wire (no sibling keys).
     *
     * @param  array<string, mixed>  $internalDraft
     * @return array<string, mixed>
     */
    protected function buildManagerLikeBfmRevalidateV1Envelope(array $internalDraft): array
    {
        $payload = $this->buildIatiLikeBfmRevalidateV1Envelope($internalDraft);
        $payload['_ota_payload_schema'] = 'sabre_manager_like_bfm_revalidate_v1';
        $payload['_ota_revalidate_payload_style'] = 'manager_like_bfm_revalidate_v1';

        $travelPrefs = is_array(data_get($payload, 'OTA_AirLowFareSearchRQ.TravelPreferences'))
            ? data_get($payload, 'OTA_AirLowFareSearchRQ.TravelPreferences')
            : [];
        $tpa = is_array($travelPrefs['TPA_Extensions'] ?? null) ? $travelPrefs['TPA_Extensions'] : [];
        $tpa['VerificationItinCallLogic'] = ['Value' => 'B'];
        $travelPrefs['TPA_Extensions'] = $tpa;
        data_set($payload, 'OTA_AirLowFareSearchRQ.TravelPreferences', $travelPrefs);

        return $payload;
    }

    /**
     * CERT/diagnostic: manager-like OTA wire with per-flight {@code ResBookDesigCode}, {@code FareBasisCode}, and
     * {@code Airline.Operating} from the selected segment row when available. OTA-only root; not default checkout style.
     *
     * @param  array<string, mixed>  $internalDraft
     * @return array<string, mixed>
     */
    protected function buildManagerLikeBfmRevalidateEnrichedV1Envelope(array $internalDraft): array
    {
        $payload = $this->buildManagerLikeBfmRevalidateV1Envelope($internalDraft);
        $payload['_ota_payload_schema'] = 'sabre_manager_like_bfm_revalidate_enriched_v1';
        $payload['_ota_revalidate_payload_style'] = 'manager_like_bfm_revalidate_enriched_v1';

        $segments = is_array($internalDraft['segments'] ?? null) ? $internalDraft['segments'] : [];
        usort($segments, static function (array $a, array $b): int {
            return strcmp(
                (string) ($a['departure_at'] ?? $a['depart_at'] ?? ''),
                (string) ($b['departure_at'] ?? $b['depart_at'] ?? '')
            );
        });

        $payload = $this->enrichManagerLikeFlightNodesFromSegments($payload, $segments, $internalDraft);
        $payload = $this->ensureIatiLikePosPseudoCityCode($payload, $internalDraft);

        return $payload;
    }

    /**
     * @param  array<string, mixed>  $payload
     * @param  list<array<string, mixed>>  $segments
     * @param  array<string, mixed>  $internalDraft
     * @return array<string, mixed>
     */
    protected function enrichManagerLikeFlightNodesFromSegments(array $payload, array $segments, array $internalDraft): array
    {
        $odis = data_get($payload, 'OTA_AirLowFareSearchRQ.OriginDestinationInformation');
        if (! is_array($odis) || $odis === []) {
            return $payload;
        }

        $shop = $this->sanitizeShopContext($this->mergeDraftShopSources($internalDraft));

        $segIdx = 0;
        foreach ($odis as $odiIdx => $odi) {
            if (! is_array($odi)) {
                continue;
            }
            $flights = data_get($odi, 'TPA_Extensions.Flight');
            if (! is_array($flights) || $flights === []) {
                continue;
            }
            foreach ($flights as $flightIdx => $flight) {
                $seg = is_array($segments[$segIdx] ?? null) ? $segments[$segIdx] : [];
                if (! is_array($flight)) {
                    $segIdx++;

                    continue;
                }
                $enriched = $this->enrichManagerLikeFlightNodeFromSegment(
                    $flight,
                    $seg,
                    $this->resolveManagerLikeFareBasisForSegment($seg, $shop, $segIdx),
                    $this->resolveManagerLikeBookingClassForSegment($seg, $shop, $segIdx),
                );
                $segIdx++;
                data_set(
                    $payload,
                    "OTA_AirLowFareSearchRQ.OriginDestinationInformation.{$odiIdx}.TPA_Extensions.Flight.{$flightIdx}",
                    $enriched,
                );
            }
        }

        return $payload;
    }

    /**
     * @param  array<string, mixed>  $flight
     * @param  array<string, mixed>  $seg
     * @return array<string, mixed>
     */
    protected function enrichManagerLikeFlightNodeFromSegment(
        array $flight,
        array $seg,
        ?string $fareBasisFallback = null,
        ?string $bookingClassOverride = null,
    ): array {
        $bookingClass = is_string($bookingClassOverride) && trim($bookingClassOverride) !== ''
            ? strtoupper(trim($bookingClassOverride))
            : strtoupper(trim((string) ($seg['booking_class'] ?? $seg['class_of_service'] ?? '')));
        if ($bookingClass === '') {
            $bookingClass = strtoupper(trim((string) ($flight['ClassOfService'] ?? $flight['ResBookDesigCode'] ?? '')));
        }

        $fareBasis = is_string($fareBasisFallback) && trim($fareBasisFallback) !== ''
            ? strtoupper(trim($fareBasisFallback))
            : strtoupper(trim((string) ($seg['fare_basis_code'] ?? $seg['fare_basis'] ?? '')));

        $op = strtoupper(trim((string) ($seg['operating_airline_code'] ?? $seg['operating_carrier'] ?? '')));
        if ($op === '') {
            $op = strtoupper(trim((string) data_get($flight, 'Airline.Operating.Code', '')));
        }

        $mkt = strtoupper(trim((string) ($seg['carrier'] ?? $seg['airline_code'] ?? '')));
        if ($mkt === '') {
            $mkt = strtoupper(trim((string) data_get($flight, 'Airline.Marketing.Code', '')));
        }

        if ($bookingClass !== '') {
            $flight['ClassOfService'] = $bookingClass;
            $flight['ResBookDesigCode'] = $bookingClass;
        }

        if ($fareBasis !== '') {
            $flight['FareBasisCode'] = substr($fareBasis, 0, 32);
        }

        $airline = is_array($flight['Airline'] ?? null) ? $flight['Airline'] : [];
        if ($mkt !== '') {
            $airline['Marketing'] = ['Code' => $mkt];
        }
        if ($op !== '') {
            $airline['Operating'] = ['Code' => $op];
        }
        if ($airline !== []) {
            $flight['Airline'] = $airline;
        }

        return array_filter($flight, static fn ($v): bool => $v !== null && $v !== [] && $v !== '');
    }

    /**
     * @param  array<string, mixed>  $seg
     * @param  array<string, mixed>  $shop
     */
    protected function resolveManagerLikeBookingClassForSegment(array $seg, array $shop, int $segmentIndex): string
    {
        foreach ([
            strtoupper(trim((string) ($seg['booking_class'] ?? ''))),
            strtoupper(trim((string) ($seg['class_of_service'] ?? ''))),
        ] as $candidate) {
            if ($candidate !== '') {
                return $candidate;
            }
        }

        $bySeg = is_array($shop['booking_classes_by_segment'] ?? null) ? $shop['booking_classes_by_segment'] : [];
        if (isset($bySeg[$segmentIndex]) && (is_string($bySeg[$segmentIndex]) || is_numeric($bySeg[$segmentIndex]))) {
            $value = strtoupper(trim((string) $bySeg[$segmentIndex]));
            if ($value !== '') {
                return $value;
            }
        }

        $classes = is_array($shop['booking_classes'] ?? null) ? $shop['booking_classes'] : [];
        if (isset($classes[$segmentIndex]) && (is_string($classes[$segmentIndex]) || is_numeric($classes[$segmentIndex]))) {
            $value = strtoupper(trim((string) $classes[$segmentIndex]));
            if ($value !== '') {
                return $value;
            }
        }

        $csv = trim((string) ($shop['booking_classes_csv'] ?? ''));
        if ($csv !== '') {
            $parts = array_values(array_filter(array_map('trim', explode(',', $csv)), static fn (string $s): bool => $s !== ''));
            if (isset($parts[$segmentIndex])) {
                return strtoupper(substr($parts[$segmentIndex], 0, 2));
            }
        }

        return '';
    }

    /**
     * @param  array<string, mixed>  $seg
     * @param  array<string, mixed>  $shop
     */
    protected function resolveManagerLikeFareBasisForSegment(array $seg, array $shop, int $segmentIndex): string
    {
        $fareBasis = strtoupper(trim((string) ($seg['fare_basis_code'] ?? $seg['fare_basis'] ?? '')));
        if ($fareBasis !== '') {
            return substr($fareBasis, 0, 32);
        }

        $bySeg = is_array($shop['fare_basis_codes_by_segment'] ?? null) ? $shop['fare_basis_codes_by_segment'] : [];
        if (isset($bySeg[$segmentIndex]) && (is_string($bySeg[$segmentIndex]) || is_numeric($bySeg[$segmentIndex]))) {
            $value = strtoupper(trim((string) $bySeg[$segmentIndex]));
            if ($value !== '') {
                return substr($value, 0, 32);
            }
        }

        $codes = is_array($shop['fare_basis_codes'] ?? null) ? $shop['fare_basis_codes'] : [];
        if (isset($codes[$segmentIndex]) && (is_string($codes[$segmentIndex]) || is_numeric($codes[$segmentIndex]))) {
            $value = strtoupper(trim((string) $codes[$segmentIndex]));
            if ($value !== '') {
                return substr($value, 0, 32);
            }
        }

        $csv = trim((string) ($shop['fare_basis_codes_csv'] ?? ''));
        if ($csv !== '') {
            $parts = array_values(array_filter(array_map('trim', explode(',', $csv)), static fn (string $s): bool => $s !== ''));
            if (isset($parts[$segmentIndex])) {
                return strtoupper(substr($parts[$segmentIndex], 0, 32));
            }
        }

        return '';
    }

    /**
     * @param  array<string, mixed>  $payload
     * @param  array<string, mixed>  $internalDraft
     * @return array<string, mixed>
     */
    protected function ensureIatiLikePosPseudoCityCode(array $payload, array $internalDraft): array
    {
        $existing = trim((string) data_get($payload, 'OTA_AirLowFareSearchRQ.POS.Source.0.PseudoCityCode', ''));
        if ($existing !== '') {
            return $payload;
        }

        $pcc = $this->resolvePseudoCityCodeFromDraft($internalDraft);
        if ($pcc === '') {
            return $payload;
        }

        $source = data_get($payload, 'OTA_AirLowFareSearchRQ.POS.Source.0');
        if (! is_array($source)) {
            $source = [
                'RequestorID' => [
                    'Type' => '1',
                    'ID' => '1',
                    'CompanyName' => ['Code' => 'TN'],
                ],
            ];
        }
        $source['PseudoCityCode'] = $pcc;
        data_set($payload, 'OTA_AirLowFareSearchRQ.POS.Source.0', $source);

        return $payload;
    }

    /**
     * @param  array<string, mixed>  $seg
     * @return array<string, mixed>
     */
    protected function buildIatiLikeFlightNodeFromSegment(array $seg): array
    {
        $depAt = (string) ($seg['departure_at'] ?? $seg['depart_at'] ?? '');
        $arrAt = (string) ($seg['arrival_at'] ?? $seg['arrive_at'] ?? '');
        $origin = strtoupper(trim((string) ($seg['origin'] ?? '')));
        $dest = strtoupper(trim((string) ($seg['destination'] ?? '')));
        $mkt = strtoupper(trim((string) ($seg['carrier'] ?? $seg['airline_code'] ?? '')));
        $op = strtoupper(trim((string) ($seg['operating_airline_code'] ?? '')));
        $flightNumberRaw = trim((string) ($seg['flight_number'] ?? $seg['flight_no'] ?? ''));
        $bookingClass = strtoupper(trim((string) ($seg['booking_class'] ?? '')));

        $number = null;
        if ($flightNumberRaw !== '') {
            $number = is_numeric($flightNumberRaw) ? (int) $flightNumberRaw : $flightNumberRaw;
        }

        $airline = array_filter([
            'Marketing' => $mkt !== '' ? ['Code' => $mkt] : null,
            'Operating' => $op !== '' ? ['Code' => $op] : null,
        ], static fn ($v) => $v !== null && $v !== []);

        return array_filter([
            'Number' => $number,
            'DepartureDateTime' => $depAt !== '' ? $depAt : null,
            'ArrivalDateTime' => $arrAt !== '' ? $arrAt : null,
            'Type' => 'A',
            'ClassOfService' => $bookingClass !== '' ? $bookingClass : null,
            'OriginLocation' => $origin !== '' ? ['LocationCode' => $origin] : null,
            'DestinationLocation' => $dest !== '' ? ['LocationCode' => $dest] : null,
            'Airline' => $airline !== [] ? $airline : null,
        ], static fn ($v) => $v !== null && $v !== [] && $v !== '');
    }

    /**
     * Group consecutive flight nodes into IATI-style ODI legs (gap {@code >24h} or broken route starts new leg).
     *
     * @param  list<array<string, mixed>>  $flightNodes
     * @return array{odis: list<array<string, mixed>>, meta: array<string, mixed>}
     */
    protected function groupIatiLikeOriginDestinationInformation(array $flightNodes): array
    {
        $odis = [];
        $currentFlights = [];
        $maxGapMinutes = 0;
        $routeContinuityOk = true;
        $rph = 0;

        $flushLeg = function () use (&$odis, &$currentFlights, &$rph): void {
            if ($currentFlights === []) {
                return;
            }
            $rph++;
            $odis[] = $this->buildIatiLikeOdiRowFromFlights($currentFlights, $rph);
            $currentFlights = [];
        };

        foreach ($flightNodes as $flightNode) {
            if ($currentFlights !== []) {
                $previous = $currentFlights[count($currentFlights) - 1];
                $prevArr = strtotime((string) ($previous['ArrivalDateTime'] ?? ''));
                $nextDep = strtotime((string) ($flightNode['DepartureDateTime'] ?? ''));
                $prevDest = strtoupper(trim((string) data_get($previous, 'DestinationLocation.LocationCode', '')));
                $nextOrig = strtoupper(trim((string) data_get($flightNode, 'OriginLocation.LocationCode', '')));

                $gapExceeds24h = $prevArr !== false && $nextDep !== false
                    && (($nextDep - $prevArr) > self::IATI_ODI_CONNECTION_GAP_SECONDS);
                $continuityBroken = $prevDest !== '' && $nextOrig !== '' && $prevDest !== $nextOrig;

                if ($continuityBroken) {
                    $routeContinuityOk = false;
                }

                if ($prevArr !== false && $nextDep !== false && $nextDep >= $prevArr) {
                    $gapMinutes = (int) round(($nextDep - $prevArr) / 60);
                    $maxGapMinutes = max($maxGapMinutes, min($gapMinutes, 9999));
                }

                if ($gapExceeds24h || $continuityBroken) {
                    $flushLeg();
                }
            }

            $currentFlights[] = $flightNode;
        }

        $flushLeg();

        $perOdi = [];
        foreach ($odis as $row) {
            $nodes = data_get($row, 'TPA_Extensions.Flight');
            $perOdi[] = is_array($nodes) ? count($nodes) : 0;
        }
        $flightNodeCount = count($flightNodes);
        $odiCount = count($odis);
        $grouped = $flightNodeCount > $odiCount
            || in_array(true, array_map(static fn (int $n): bool => $n > 1, $perOdi), true);

        return [
            'odis' => $odis,
            'meta' => [
                'odi_count' => $odiCount,
                'flight_node_count' => $flightNodeCount,
                'grouped_flight_nodes_per_odi' => implode('|', $perOdi),
                'iati_like_segments_grouped' => $grouped,
                'route_continuity_ok' => $routeContinuityOk,
                'max_connection_gap_minutes_sanitized' => $maxGapMinutes > 0 ? $maxGapMinutes : null,
                'max_connection_gap_bucket' => $this->connectionGapBucket($maxGapMinutes),
            ],
        ];
    }

    /**
     * @param  list<array<string, mixed>>  $flights
     * @return array<string, mixed>
     */
    protected function buildIatiLikeOdiRowFromFlights(array $flights, int $rph): array
    {
        $first = $flights[0];
        $last = $flights[count($flights) - 1];

        return [
            'RPH' => (string) $rph,
            'DepartureDateTime' => $first['DepartureDateTime'] ?? null,
            'OriginLocation' => $first['OriginLocation'] ?? null,
            'DestinationLocation' => $last['DestinationLocation'] ?? null,
            'TPA_Extensions' => [
                'SegmentType' => ['Code' => 'O'],
                'Flight' => $flights,
            ],
        ];
    }

    protected function connectionGapBucket(int $maxGapMinutes): string
    {
        if ($maxGapMinutes <= 0) {
            return 'unknown';
        }

        return $maxGapMinutes > (self::IATI_ODI_CONNECTION_GAP_SECONDS / 60) ? 'over_24h' : 'under_24h';
    }

    /**
     * @param  array<string, mixed>  $internalDraft
     * @return array{ADT: int, CNN: int, INF: int}
     */
    protected function passengerTypeCountsForIatiRevalidate(array $internalDraft): array
    {
        $passengers = is_array($internalDraft['passengers'] ?? null) ? $internalDraft['passengers'] : [];
        $counts = ['ADT' => 0, 'CNN' => 0, 'INF' => 0];
        foreach ($passengers as $p) {
            if (! is_array($p)) {
                continue;
            }
            $code = strtoupper(trim((string) ($p['type'] ?? 'ADT')));
            $bucket = match ($code) {
                'CHD', 'CH', 'CNN' => 'CNN',
                'INF', 'IN', 'INS' => 'INF',
                default => 'ADT',
            };
            $counts[$bucket]++;
        }
        if (array_sum($counts) === 0) {
            $counts['ADT'] = max(1, count($passengers));
        }

        return $counts;
    }

    /**
     * PCC for revalidate POS — same resolution order as {@see SabreBookingPayloadBuilder::resolveSabrePseudoCityCodeForTripOrdersWire()}
     * (draft explicit key, shop context, then {@code supplier_connection_id} credentials/settings). Never logged here.
     *
     * @param  array<string, mixed>  $internalDraft
     */
    protected function resolvePseudoCityCodeFromDraft(array $internalDraft): string
    {
        $explicit = trim((string) ($internalDraft['_sabre_pseudo_city_code'] ?? ''));
        if ($explicit !== '') {
            return strtoupper(substr($explicit, 0, 16));
        }
        $shop = $this->sanitizeShopContext($this->mergeDraftShopSources($internalDraft));
        foreach (['pcc', 'PCC', 'pseudo_city_code', 'pseudoCityCode'] as $key) {
            $v = trim((string) ($shop[$key] ?? ''));
            if ($v !== '') {
                return strtoupper(substr($v, 0, 16));
            }
        }

        $cid = (int) ($internalDraft['supplier_connection_id'] ?? 0);
        if ($cid <= 0) {
            return '';
        }
        $conn = SupplierConnection::query()->find($cid);
        if ($conn === null) {
            return '';
        }
        $cred = is_array($conn->credentials) ? $conn->credentials : [];
        $settings = is_array($conn->settings) ? $conn->settings : [];
        foreach (['pcc', 'PCC', 'pseudo_city_code', 'pseudoCityCode'] as $key) {
            $v = trim((string) ($cred[$key] ?? ''));
            if ($v !== '') {
                return strtoupper(substr($v, 0, 16));
            }
            $v = trim((string) data_get($settings, $key));
            if ($v !== '') {
                return strtoupper(substr($v, 0, 16));
            }
        }

        return '';
    }

    /**
     * B22: Minimal {@code OTA_AirLowFareSearchRQ} “shop replay” constrained to route / marketing carriers / classes
     * (no shop_context / pricingInformation / itinerary mirror blocks).
     *
     * @param  array<string, mixed>  $internalDraft
     * @return array<string, mixed>
     */
    protected function buildShopReplaySelectedItineraryV1Envelope(array $internalDraft): array
    {
        $segments = is_array($internalDraft['segments'] ?? null) ? $internalDraft['segments'] : [];
        usort($segments, static function (array $a, array $b): int {
            return strcmp(
                (string) ($a['departure_at'] ?? $a['depart_at'] ?? ''),
                (string) ($b['departure_at'] ?? $b['depart_at'] ?? '')
            );
        });

        $passengers = is_array($internalDraft['passengers'] ?? null) ? $internalDraft['passengers'] : [];
        $ptcCounts = ['ADT' => 0, 'CHD' => 0, 'INF' => 0];
        foreach ($passengers as $p) {
            if (! is_array($p)) {
                continue;
            }
            $code = strtoupper(trim((string) ($p['type'] ?? 'ADT')));
            $code = match ($code) {
                'CHD', 'CH', 'CNN' => 'CHD',
                'INF', 'IN', 'INS' => 'INF',
                default => 'ADT',
            };
            $ptcCounts[$code]++;
        }
        if (array_sum($ptcCounts) === 0) {
            $ptcCounts['ADT'] = max(1, count($passengers));
        }

        $fare = is_array($internalDraft['fare'] ?? null) ? $internalDraft['fare'] : [];
        $currency = trim((string) ($fare['currency'] ?? ''));
        $validatingCarrier = strtoupper(trim((string) ($internalDraft['validating_carrier'] ?? '')));

        $odis = [];
        $vendorCarriers = [];
        foreach ($segments as $idx => $seg) {
            if (! is_array($seg)) {
                continue;
            }
            $depAt = (string) ($seg['departure_at'] ?? $seg['depart_at'] ?? '');
            $arrAt = (string) ($seg['arrival_at'] ?? $seg['arrive_at'] ?? '');
            $origin = strtoupper(trim((string) ($seg['origin'] ?? '')));
            $dest = strtoupper(trim((string) ($seg['destination'] ?? '')));
            $mkt = strtoupper(trim((string) ($seg['carrier'] ?? $seg['airline_code'] ?? '')));
            $op = strtoupper(trim((string) ($seg['operating_airline_code'] ?? '')));
            $flightNumber = trim((string) ($seg['flight_number'] ?? $seg['flight_no'] ?? ''));
            $bookingClass = strtoupper(trim((string) ($seg['booking_class'] ?? '')));
            $fareBasis = strtoupper(trim((string) ($seg['fare_basis_code'] ?? '')));
            $cabin = strtoupper(trim((string) ($seg['segment_cabin_code'] ?? '')));

            if ($mkt !== '' && ! isset($vendorCarriers[$mkt])) {
                $vendorCarriers[$mkt] = true;
            }

            $flightSegment = array_filter([
                'Number' => $idx + 1,
                'SegmentNumber' => $idx + 1,
                'DepartureDateTime' => $depAt !== '' ? $depAt : null,
                'ArrivalDateTime' => $arrAt !== '' ? $arrAt : null,
                'OriginLocation' => $origin !== '' ? ['LocationCode' => $origin] : null,
                'DestinationLocation' => $dest !== '' ? ['LocationCode' => $dest] : null,
                'MarketingAirline' => $mkt !== '' ? array_filter([
                    'Code' => $mkt,
                    'FlightNumber' => $flightNumber !== '' ? $flightNumber : null,
                ]) : null,
                'OperatingAirline' => $op !== '' ? ['Code' => $op] : null,
                'FlightNumber' => $flightNumber !== '' ? $flightNumber : null,
                'ResBookDesigCode' => $bookingClass !== '' ? $bookingClass : null,
                'ClassOfService' => $bookingClass !== '' ? $bookingClass : null,
                'CabinCode' => $cabin !== '' ? $cabin : null,
                'FareBasisCode' => $fareBasis !== '' ? $fareBasis : null,
            ], static fn ($v) => $v !== null && $v !== [] && $v !== '');

            $odis[] = [
                'FlightSegment' => $flightSegment,
            ];
        }

        $vendorPrefs = [];
        foreach (array_keys($vendorCarriers) as $code) {
            $vendorPrefs[] = ['Code' => $code, 'PreferLevel' => 'Preferred'];
        }
        if ($validatingCarrier !== '' && $vendorPrefs === []) {
            $vendorPrefs[] = ['Code' => $validatingCarrier, 'PreferLevel' => 'Preferred'];
        }

        $travelerInfoSummary = [
            'AirTravelerAvail' => [
                array_filter([
                    'PassengerTypeQuantity' => array_values(array_filter([
                        $ptcCounts['ADT'] > 0 ? ['Code' => 'ADT', 'Quantity' => $ptcCounts['ADT']] : null,
                        $ptcCounts['CHD'] > 0 ? ['Code' => 'CHD', 'Quantity' => $ptcCounts['CHD']] : null,
                        $ptcCounts['INF'] > 0 ? ['Code' => 'INF', 'Quantity' => $ptcCounts['INF']] : null,
                    ])),
                ], static fn ($v) => $v !== null && $v !== []),
            ],
        ];

        return [
            '_ota_provider' => SupplierProvider::Sabre->value,
            '_ota_payload_schema' => 'sabre_shop_replay_selected_itinerary_v1',
            '_ota_revalidate_payload_style' => 'shop_replay_selected_itinerary_v1',
            'OTA_AirLowFareSearchRQ' => [
                'POS' => [
                    'Source' => [
                        ['RequestorID' => array_filter([
                            'Type' => '1',
                            'CompanyName' => ['Code' => 'TN'],
                        ])],
                    ],
                ],
                'OriginDestinationInformation' => $odis,
                'TravelPreferences' => array_filter([
                    'ValidInterlineTicket' => true,
                    'VendorPref' => $vendorPrefs !== [] ? $vendorPrefs : null,
                ], static fn ($v) => $v !== null && $v !== []),
                'TravelerInfoSummary' => array_filter([
                    'PriceRequestInformation' => array_filter([
                        'CurrencyCode' => $currency !== '' ? $currency : null,
                    ], static fn ($v) => $v !== null && $v !== ''),
                    'AirTravelerAvail' => $travelerInfoSummary['AirTravelerAvail'],
                ], static fn ($v) => $v !== null && $v !== []),
            ],
        ];
    }

    /**
     * @param  array<string, mixed>  $node
     * @param  list<string>  $blockedKeysLower
     * @return array<string, mixed>|list<mixed>|string
     */
    protected function sanitizeTree(array $node, array $blockedKeysLower): array
    {
        if ($node === []) {
            return [];
        }
        if (array_is_list($node)) {
            $out = [];
            foreach ($node as $v) {
                $out[] = is_array($v) ? $this->sanitizeTree($v, $blockedKeysLower) : $v;
            }

            return $out;
        }

        $out = [];
        foreach ($node as $k => $v) {
            if (! is_string($k)) {
                continue;
            }
            if (in_array(strtolower($k), $blockedKeysLower, true)) {
                continue;
            }
            if (is_array($v)) {
                $out[$k] = $this->sanitizeTree($v, $blockedKeysLower);
            } else {
                $out[$k] = $v;
            }
        }

        return $out;
    }

    /**
     * Build a safe summary of the revalidation payload for diagnostics/inspect (no PII, no token).
     *
     * @param  array<string, mixed>  $payload
     * @return array<string, mixed>
     */
    public function safePayloadSummary(array $payload): array
    {
        $clientRq = is_array($payload['RevalidateItineraryRQ'] ?? null) ? $payload['RevalidateItineraryRQ'] : null;
        if ($clientRq !== null) {
            $fss = is_array($clientRq['FlightSegments'] ?? null) ? $clientRq['FlightSegments'] : [];
            $hasBookingClass = false;
            $hasFareBasis = false;
            $hasClassOfService = false;
            $hasSegmentNumbers = false;
            $segRoutes = [];
            foreach ($fss as $fs) {
                if (! is_array($fs)) {
                    continue;
                }
                if (trim((string) ($fs['ClassOfService'] ?? '')) !== '') {
                    $hasClassOfService = true;
                    $hasBookingClass = true;
                }
                if (trim((string) ($fs['FareBasisCode'] ?? '')) !== '') {
                    $hasFareBasis = true;
                }
                if (trim((string) ($fs['Number'] ?? '')) !== '') {
                    $hasSegmentNumbers = true;
                }
                $orig = (string) data_get($fs, 'OriginLocation.LocationCode', '');
                $dest = (string) data_get($fs, 'DestinationLocation.LocationCode', '');
                if ($orig !== '' && $dest !== '') {
                    $segRoutes[] = $orig.'→'.$dest;
                }
            }
            $currency = trim((string) ($clientRq['CurrencyCode'] ?? ''));
            $vc = trim((string) ($clientRq['ValidatingCarrier'] ?? ''));
            $counts = is_array($clientRq['PassengerCounts'] ?? null) ? $clientRq['PassengerCounts'] : [];
            $fcRefs = is_array($clientRq['FareComponentRefs'] ?? null) ? $clientRq['FareComponentRefs'] : [];

            return [
                'payload_style' => (string) ($payload['_ota_revalidate_payload_style'] ?? 'client_gds_revalidate_v1'),
                'payload_schema' => (string) ($payload['_ota_payload_schema'] ?? ''),
                'segment_count' => count($fss),
                'segment_routes' => array_slice($segRoutes, 0, 6),
                'passenger_type_counts' => $counts,
                'currency' => $currency,
                'validating_carrier' => $vc,
                'has_shop_context' => false,
                'has_leg_refs' => false,
                'has_schedule_refs' => false,
                'has_pricing_information_ref' => false,
                'has_reconstructed_pricing_context' => false,
                'has_fare_component_refs' => $fcRefs !== [],
                'has_booking_class' => $hasBookingClass,
                'has_fare_basis' => $hasFareBasis,
                'has_validating_carrier' => $vc !== '',
                'has_class_of_service' => $hasClassOfService,
                'has_segment_numbers' => $hasSegmentNumbers,
                'has_offer_reference' => false,
                'has_itinerary_reference' => false,
                'carrier_chain' => [],
                'shop_context_keys' => [],
            ];
        }

        $odis = is_array(data_get($payload, 'OTA_AirLowFareSearchRQ.OriginDestinationInformation')) ? data_get($payload, 'OTA_AirLowFareSearchRQ.OriginDestinationInformation') : [];
        $segments = is_array(data_get($payload, 'itinerary.segments')) ? data_get($payload, 'itinerary.segments') : [];
        $iatiFlights = $this->collectIatiExtensionFlightsFromPayload($payload);
        $hasBookingClass = false;
        $hasFareBasis = false;
        $hasClassOfService = false;
        $hasSegmentNumbers = false;
        $segRoutes = [];
        if ($iatiFlights !== []) {
            foreach ($iatiFlights as $fs) {
                if (! is_array($fs)) {
                    continue;
                }
                if (trim((string) ($fs['ClassOfService'] ?? '')) !== '') {
                    $hasBookingClass = true;
                    $hasClassOfService = true;
                }
                if (trim((string) ($fs['Number'] ?? '')) !== '') {
                    $hasSegmentNumbers = true;
                }
                $orig = (string) data_get($fs, 'OriginLocation.LocationCode', '');
                $dest = (string) data_get($fs, 'DestinationLocation.LocationCode', '');
                if ($orig !== '' && $dest !== '') {
                    $segRoutes[] = $orig.'→'.$dest;
                }
            }
        }
        foreach ($odis as $row) {
            if (! is_array($row)) {
                continue;
            }
            $fs = is_array($row['FlightSegment'] ?? null) ? $row['FlightSegment'] : [];
            if (trim((string) ($fs['ResBookDesigCode'] ?? '')) !== '') {
                $hasBookingClass = true;
            }
            if (trim((string) ($fs['ClassOfService'] ?? '')) !== '') {
                $hasClassOfService = true;
            }
            if (trim((string) ($fs['Number'] ?? $fs['SegmentNumber'] ?? '')) !== '') {
                $hasSegmentNumbers = true;
            }
            if (trim((string) ($fs['FareBasisCode'] ?? '')) !== '') {
                $hasFareBasis = true;
            }
            $orig = (string) data_get($fs, 'OriginLocation.LocationCode', '');
            $dest = (string) data_get($fs, 'DestinationLocation.LocationCode', '');
            if ($orig !== '' && $dest !== '') {
                $segRoutes[] = $orig.'→'.$dest;
            }
        }
        $shopCtx = is_array($payload['shop_context'] ?? null) ? $payload['shop_context'] : [];
        $fareCtx = is_array($payload['fare_context'] ?? null) ? $payload['fare_context'] : [];
        $hasOfferReference = false;
        foreach (array_merge($shopCtx, $fareCtx) as $k => $v) {
            if (! is_string($k)) {
                continue;
            }
            $kl = strtolower($k);
            if (is_scalar($v) && trim((string) $v) !== '' && (
                str_contains($kl, 'offeritem')
                || str_contains($kl, 'offer_item')
                || str_contains($kl, 'offer_reference')
                || $kl === 'offer_ref'
                || $kl === 'offer_id'
                || $kl === 'pricing_information_ref'
                || $kl === 'pricing_information_id'
            )) {
                $hasOfferReference = true;
                break;
            }
        }
        $piRoot = is_array($payload['pricingInformation'][0] ?? null) ? $payload['pricingInformation'][0] : [];
        $hasPricingFromEnvelope = $piRoot !== [] && (
            trim((string) ($piRoot['ref'] ?? '')) !== ''
            || trim((string) ($piRoot['id'] ?? '')) !== ''
            || trim((string) ($piRoot['offerItemId'] ?? '')) !== ''
        );
        $hasReconstructedPricingContext = $piRoot !== [] && (
            array_key_exists('itineraryGroupIndex', $piRoot)
            || array_key_exists('pricingInformationIndex', $piRoot)
            || array_key_exists('fareBasisCodes', $piRoot)
        );
        $hasPricingInformationRef = trim((string) (
            $shopCtx['pricing_information_ref']
                ?? $shopCtx['pricing_information_id']
                ?? data_get($payload, 'fare_context.pricing_information_ref')
                ?? data_get($payload, 'fare_context.pricing_information_id')
                ?? ''
        )) !== '' || $hasPricingFromEnvelope
            || trim((string) ($shopCtx['pricing_0_ref'] ?? '')) !== '';

        $itinProbe = trim((string) (
            $shopCtx['itinerary_ref'] ?? $shopCtx['itinerary_id'] ?? data_get($payload, 'fare_context.itinerary_reference') ?? data_get($payload, 'itinerary.id') ?? ''
        ));

        $counts = is_array($payload['passenger_counts'] ?? null) ? $payload['passenger_counts'] : [];
        $carrierChain = is_array($shopCtx['carrier_chain'] ?? null) ? $shopCtx['carrier_chain'] : [];
        if ($carrierChain === []) {
            $carrierChain = is_array(data_get($payload, 'fare_context.carrier_chain')) ? data_get($payload, 'fare_context.carrier_chain') : [];
        }

        return [
            'payload_style' => (string) ($payload['_ota_revalidate_payload_style'] ?? 'bfm_revalidate_v1'),
            'payload_schema' => (string) ($payload['_ota_payload_schema'] ?? ''),
            'segment_count' => $iatiFlights !== [] ? count($iatiFlights) : count($odis !== [] ? $odis : $segments),
            'segment_routes' => array_slice($segRoutes, 0, 6),
            'passenger_type_counts' => $counts,
            'currency' => (string) (data_get($payload, 'fare_context.currency') ?? ''),
            'validating_carrier' => (string) (data_get($payload, 'fare_context.validating_carrier') ?? ''),
            'has_shop_context' => $shopCtx !== [],
            'has_leg_refs' => is_array($shopCtx['leg_refs'] ?? null) && $shopCtx['leg_refs'] !== [],
            'has_schedule_refs' => is_array($shopCtx['schedule_refs'] ?? null) && $shopCtx['schedule_refs'] !== [],
            'has_pricing_information_ref' => $hasPricingInformationRef,
            'has_reconstructed_pricing_context' => $hasReconstructedPricingContext,
            'has_fare_component_refs' => is_array($shopCtx['fare_component_refs'] ?? null) && $shopCtx['fare_component_refs'] !== [],
            'has_booking_class' => $hasBookingClass,
            'has_fare_basis' => $hasFareBasis,
            'has_validating_carrier' => trim((string) (data_get($payload, 'fare_context.validating_carrier') ?? '')) !== '',
            'has_class_of_service' => $hasClassOfService,
            'has_segment_numbers' => $hasSegmentNumbers,
            'has_offer_reference' => $hasOfferReference,
            'has_itinerary_reference' => $itinProbe !== '',
            'carrier_chain' => array_slice($carrierChain, 0, 8),
            'shop_context_keys' => array_slice(array_keys($shopCtx), 0, 24),
        ];
    }

    /**
     * Sprint 11K-J: Scalar-only revalidation payload coverage (no raw JSON, PCC values, or PII).
     *
     * @param  array<string, mixed>  $payload
     * @return array{
     *     payload_style: string,
     *     has_pos: bool,
     *     has_pcc: bool,
     *     has_data_sources: bool,
     *     has_request_type: bool,
     *     has_50itins: bool,
     *     has_seats_requested: bool,
     *     has_price_request_information: bool,
     *     has_vendor_pref: bool,
     *     has_origin_destination_information: bool,
     *     segment_count: int,
     *     passenger_count: int,
     *     selected_offer_context_present: bool,
     *     pricing_context_present: bool
     * }
     */
    public function normalizedPayloadCoverageSummary(array $payload): array
    {
        $style = (string) ($payload['_ota_revalidate_payload_style'] ?? 'bfm_revalidate_v1');
        $hasOta = array_key_exists('OTA_AirLowFareSearchRQ', $payload);
        $clientRq = is_array($payload['RevalidateItineraryRQ'] ?? null) ? $payload['RevalidateItineraryRQ'] : null;

        $hasPos = false;
        $hasOdi = false;
        $segmentCount = 0;

        if ($hasOta) {
            $ota = is_array($payload['OTA_AirLowFareSearchRQ']) ? $payload['OTA_AirLowFareSearchRQ'] : [];
            $hasPos = is_array($ota['POS'] ?? null) && $ota['POS'] !== [];
            $odis = is_array($ota['OriginDestinationInformation'] ?? null) ? $ota['OriginDestinationInformation'] : [];
            $hasOdi = $odis !== [];
            $iatiFlights = $this->collectIatiExtensionFlightsFromPayload($payload);
            $segmentCount = $iatiFlights !== [] ? count($iatiFlights) : count($odis);
        } elseif ($clientRq !== null) {
            $hasPos = is_array($clientRq['POS'] ?? null) && $clientRq['POS'] !== [];
            $fss = is_array($clientRq['FlightSegments'] ?? null) ? $clientRq['FlightSegments'] : [];
            $hasOdi = $fss !== [];
            $segmentCount = count($fss);
        } else {
            $segments = is_array($payload['flightSegments'] ?? null) ? $payload['flightSegments'] : [];
            $hasOdi = $segments !== [];
            $segmentCount = count($segments);
        }

        $dataSources = data_get($payload, 'OTA_AirLowFareSearchRQ.TravelPreferences.TPA_Extensions.DataSources');
        $hasDataSources = is_array($dataSources)
            && ($dataSources['NDC'] ?? null) === 'Disable'
            && ($dataSources['ATPCO'] ?? null) === 'Enable'
            && ($dataSources['LCC'] ?? null) === 'Enable';

        $intelliRoot = trim((string) data_get(
            $payload,
            'OTA_AirLowFareSearchRQ.TPA_Extensions.IntelliSellTransaction.RequestType.Name',
            ''
        ));
        $intelliTraveler = trim((string) data_get(
            $payload,
            'OTA_AirLowFareSearchRQ.TravelerInfoSummary.TPA_Extensions.IntelliSellTransaction.RequestType.Name',
            ''
        ));
        $intelliName = $intelliRoot !== '' ? $intelliRoot : $intelliTraveler;

        $seatsRaw = data_get($payload, 'OTA_AirLowFareSearchRQ.TravelerInfoSummary.SeatsRequested');
        $hasSeatsRequested = match (true) {
            is_array($seatsRaw) => $seatsRaw !== [],
            is_int($seatsRaw), is_float($seatsRaw) => true,
            is_string($seatsRaw) => trim($seatsRaw) !== '',
            default => false,
        };

        $priceReq = data_get($payload, 'OTA_AirLowFareSearchRQ.TravelerInfoSummary.PriceRequestInformation');
        $hasPriceRequestInformation = is_array($priceReq) && $priceReq !== [];

        $vendorPref = data_get($payload, 'OTA_AirLowFareSearchRQ.TravelPreferences.VendorPref');
        $hasVendorPref = is_array($vendorPref) && $vendorPref !== [];

        $passengerCount = $this->passengerCountFromPayload($payload);

        $shopCtx = is_array($payload['shop_context'] ?? null) ? $payload['shop_context'] : [];
        $fareCtx = is_array($payload['fare_context'] ?? null) ? $payload['fare_context'] : [];
        $selectedOfferContextPresent = trim((string) ($fareCtx['selected_offer_id'] ?? '')) !== ''
            || trim((string) ($fareCtx['supplier_offer_id'] ?? '')) !== ''
            || trim((string) ($shopCtx['itinerary_ref'] ?? $shopCtx['itinerary_id'] ?? '')) !== ''
            || (is_array($shopCtx['leg_refs'] ?? null) && $shopCtx['leg_refs'] !== [])
            || trim((string) data_get($payload, 'itinerary.id', '')) !== '';

        $piRoot = is_array($payload['pricingInformation'][0] ?? null) ? $payload['pricingInformation'][0] : [];
        $hasReconstructedPricing = $piRoot !== [] && (
            array_key_exists('itineraryGroupIndex', $piRoot)
            || array_key_exists('pricingInformationIndex', $piRoot)
            || array_key_exists('fareBasisCodes', $piRoot)
        );
        $hasPricingRef = trim((string) (
            $shopCtx['pricing_information_ref']
                ?? $shopCtx['pricing_information_id']
                ?? $fareCtx['pricing_information_ref']
                ?? $fareCtx['pricing_information_id']
                ?? ''
        )) !== '';
        $pricingContextPresent = $hasReconstructedPricing
            || $hasPricingRef
            || (is_array($shopCtx['fare_component_refs'] ?? null) && $shopCtx['fare_component_refs'] !== []);

        return [
            'payload_style' => $style,
            'has_pos' => $hasPos,
            'has_pcc' => $this->payloadHasPseudoCityCode($payload),
            'has_data_sources' => $hasDataSources,
            'has_request_type' => $intelliName !== '',
            'has_50itins' => $intelliName === '50ITINS',
            'has_seats_requested' => $hasSeatsRequested,
            'has_price_request_information' => $hasPriceRequestInformation,
            'has_vendor_pref' => $hasVendorPref,
            'has_origin_destination_information' => $hasOdi,
            'segment_count' => $segmentCount,
            'passenger_count' => $passengerCount,
            'selected_offer_context_present' => $selectedOfferContextPresent,
            'pricing_context_present' => $pricingContextPresent,
        ];
    }

    /**
     * Sprint 11K-L: Scalar launch-style probe row (baseline vs pricing-context); no raw JSON or PII.
     *
     * @param  array<string, mixed>  $payload
     * @param  array<string, mixed>|null  $httpOutcome  Optional {@see SabreBookingService::runRevalidationBeforeBooking()} result
     * @return array<string, bool|int|string|null>
     */
    public function launchStyleProbeSummary(array $payload, ?array $httpOutcome = null): array
    {
        $coverage = $this->normalizedPayloadCoverageSummary($payload);
        $safe = $this->safePayloadSummary($payload);
        $shopCtx = is_array($payload['shop_context'] ?? null) ? $payload['shop_context'] : [];
        $fareCtx = is_array($payload['fare_context'] ?? null) ? $payload['fare_context'] : [];
        $piRoot = is_array($payload['pricingInformation'][0] ?? null) ? $payload['pricingInformation'][0] : [];
        $priceReq = data_get($payload, 'OTA_AirLowFareSearchRQ.TravelerInfoSummary.PriceRequestInformation');

        $pricingIndexPresent = (isset($shopCtx['pricing_information_index']) && (int) $shopCtx['pricing_information_index'] > 0)
            || (array_key_exists('pricingInformationIndex', $piRoot) && (int) ($piRoot['pricingInformationIndex'] ?? 0) > 0);

        $amount = $fareCtx['amount'] ?? $fareCtx['total'] ?? null;
        $totalPresent = (is_numeric($amount) && (float) $amount > 0)
            || data_get($priceReq, 'TotalAmount') !== null
            || data_get($priceReq, 'Fare.TotalFare.Amount') !== null;

        $currency = trim((string) ($fareCtx['currency'] ?? ''));
        $currencyPresent = $currency !== ''
            || trim((string) data_get($priceReq, 'CurrencyCode', '')) !== '';

        $row = [
            'style' => (string) ($coverage['payload_style'] ?? 'bfm_revalidate_v1'),
            'payload_coverage_summary' => $this->buildPayloadCoverageSummaryDigest($coverage, $safe),
            'segment_count' => (int) ($coverage['segment_count'] ?? 0),
            'passenger_count' => (int) ($coverage['passenger_count'] ?? 0),
            'has_price_request_information' => ($coverage['has_price_request_information'] ?? false) === true,
            'has_vendor_pref' => ($coverage['has_vendor_pref'] ?? false) === true,
            'has_origin_destination_information' => ($coverage['has_origin_destination_information'] ?? false) === true,
            'selected_offer_context_present' => ($coverage['selected_offer_context_present'] ?? false) === true,
            'pricing_context_present' => ($coverage['pricing_context_present'] ?? false) === true,
            'itinerary_ref_present' => ($safe['has_itinerary_reference'] ?? false) === true,
            'pricing_information_index_present' => $pricingIndexPresent,
            'leg_refs_present' => ($safe['has_leg_refs'] ?? false) === true,
            'schedule_refs_present' => ($safe['has_schedule_refs'] ?? false) === true,
            'booking_classes_present' => ($safe['has_booking_class'] ?? false) === true,
            'fare_basis_present' => ($safe['has_fare_basis'] ?? false) === true,
            'validating_carrier_present' => ($safe['has_validating_carrier'] ?? false) === true,
            'currency_present' => $currencyPresent,
            'total_present' => $totalPresent,
            'revalidation_http_status' => null,
            'revalidation_success' => null,
            'safe_error_family' => null,
            'safe_reason_code' => null,
        ];

        if ($httpOutcome !== null) {
            $httpStatus = isset($httpOutcome['http_status']) ? (int) $httpOutcome['http_status'] : null;
            $row['revalidation_http_status'] = $httpStatus;
            $row['revalidation_success'] = ($httpOutcome['success'] ?? false) === true;
            $row['safe_reason_code'] = $this->capSafeScalarString($httpOutcome['reason_code'] ?? null, 64);
            $row['safe_error_family'] = $this->resolveSafeRevalidationErrorFamily($httpOutcome, $httpStatus);
        }

        return $row;
    }

    /**
     * @param  array<string, mixed>  $coverage
     * @param  array<string, mixed>  $safe
     */
    protected function buildPayloadCoverageSummaryDigest(array $coverage, array $safe): string
    {
        $tags = [];
        foreach ([
            'pos' => ($coverage['has_pos'] ?? false) === true,
            'pcc' => ($coverage['has_pcc'] ?? false) === true,
            'vendor' => ($coverage['has_vendor_pref'] ?? false) === true,
            'price_req' => ($coverage['has_price_request_information'] ?? false) === true,
            'odi' => ($coverage['has_origin_destination_information'] ?? false) === true,
            'sel_offer' => ($coverage['selected_offer_context_present'] ?? false) === true,
            'pricing_ctx' => ($coverage['pricing_context_present'] ?? false) === true,
            'itin_ref' => ($safe['has_itinerary_reference'] ?? false) === true,
            'leg_refs' => ($safe['has_leg_refs'] ?? false) === true,
            'sched_refs' => ($safe['has_schedule_refs'] ?? false) === true,
            'booking_class' => ($safe['has_booking_class'] ?? false) === true,
            'fare_basis' => ($safe['has_fare_basis'] ?? false) === true,
            'validating_vc' => ($safe['has_validating_carrier'] ?? false) === true,
            'recon_pricing' => ($safe['has_reconstructed_pricing_context'] ?? false) === true,
        ] as $tag => $present) {
            if ($present) {
                $tags[] = $tag;
            }
        }

        return $tags !== [] ? implode('+', $tags) : 'minimal';
    }

    /**
     * @param  array<string, mixed>  $outcome
     */
    protected function resolveSafeRevalidationErrorFamily(array $outcome, ?int $httpStatus): ?string
    {
        if (($outcome['success'] ?? false) === true) {
            return 'success';
        }

        $err = is_array($outcome['error_digest'] ?? null) ? $outcome['error_digest'] : [];
        $codes = array_map(static fn ($c): string => strtolower(trim((string) $c)), (array) ($err['response_error_codes'] ?? []));
        $reason = strtolower(trim((string) ($outcome['reason_code'] ?? '')));

        if (in_array('27131', $codes, true) || ($outcome['includes_sabre_error_27131'] ?? false) === true) {
            return 'no_fares_rbd_carrier';
        }
        if (str_contains($reason, 'host_segment') || str_contains($reason, 'halt_on_status')) {
            return 'host_segment_status';
        }
        if ($httpStatus !== null && in_array($httpStatus, [401, 403], true)) {
            return 'not_authorized';
        }
        if ($httpStatus !== null && $httpStatus >= 500) {
            return 'unknown_host_error';
        }
        if ($httpStatus !== null && in_array($httpStatus, [400, 422], true)) {
            return 'validation_error';
        }
        if ($httpStatus !== null && $httpStatus >= 200 && $httpStatus < 300) {
            return 'empty_or_unusable_response';
        }

        return 'revalidation_failed';
    }

    protected function capSafeScalarString(mixed $value, int $max): ?string
    {
        if (! is_scalar($value)) {
            return null;
        }
        $s = trim((string) $value);

        return $s !== '' ? substr($s, 0, $max) : null;
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    protected function passengerCountFromPayload(array $payload): int
    {
        $counts = is_array($payload['passenger_counts'] ?? null) ? $payload['passenger_counts'] : [];
        if ($counts !== []) {
            return max(0, (int) array_sum(array_map(static fn ($n): int => (int) $n, $counts)));
        }

        $ptq = data_get($payload, 'OTA_AirLowFareSearchRQ.TravelerInfoSummary.AirTravelerAvail.0.PassengerTypeQuantity');
        if (is_array($ptq)) {
            $sum = 0;
            foreach ($ptq as $row) {
                if (! is_array($row)) {
                    continue;
                }
                $sum += max(0, (int) ($row['Quantity'] ?? 0));
            }
            if ($sum > 0) {
                return $sum;
            }
        }

        $clientRq = is_array($payload['RevalidateItineraryRQ'] ?? null) ? $payload['RevalidateItineraryRQ'] : null;
        if ($clientRq !== null) {
            $clientCounts = is_array($clientRq['PassengerCounts'] ?? null) ? $clientRq['PassengerCounts'] : [];
            if ($clientCounts !== []) {
                return max(0, (int) array_sum(array_map(static fn ($n): int => (int) $n, $clientCounts)));
            }
        }

        $seatsRaw = data_get($payload, 'OTA_AirLowFareSearchRQ.TravelerInfoSummary.SeatsRequested');
        if (is_array($seatsRaw) && isset($seatsRaw[0]) && is_numeric($seatsRaw[0])) {
            return max(1, (int) $seatsRaw[0]);
        }
        if (is_numeric($seatsRaw)) {
            return max(1, (int) $seatsRaw);
        }

        return 0;
    }

    /**
     * B20: Safe structural digest of a Sabre revalidate HTTP body (no raw JSON, no PII fields, capped scalars).
     *
     * @param  array<string, mixed>|null  $json  Prefer decoded array when available
     * @return array<string, mixed>
     */
    public function digestRevalidateResponseStructure(string $rawBody, ?array $json): array
    {
        $trimmed = trim($rawBody);
        $bodyEmpty = $trimmed === '';
        $decoded = null;
        if (! $bodyEmpty) {
            $decoded = json_decode($rawBody, true);
        }
        $jsonValid = ! $bodyEmpty && json_last_error() === JSON_ERROR_NONE && $decoded !== null;

        $tree = [];
        if (is_array($json) && $json !== []) {
            $tree = $json;
        } elseif (is_array($decoded)) {
            $tree = $decoded;
        }

        $topLevelKeys = is_array($tree) ? array_values(array_filter(array_keys($tree), static fn ($k): bool => is_string($k))) : [];
        $keyPaths = $this->collectNestedKeyPathsDepth3($tree);
        $candidates = $this->collectRevalidateScalarCandidates($tree);
        $candidateCount = count($candidates);

        return [
            'response_json_valid' => $jsonValid,
            'response_body_empty' => $bodyEmpty,
            'response_top_level_keys' => array_slice($topLevelKeys, 0, 40),
            'response_nested_key_paths' => array_slice($keyPaths, 0, self::DIGEST_MAX_KEY_PATHS),
            'response_scalar_candidates' => array_slice($candidates, 0, self::DIGEST_MAX_CANDIDATES),
            'candidate_count' => $candidateCount,
        ];
    }

    /**
     * B20: Extract capped codes/messages from {@code warnings}, {@code messages}, or {@code errors} subtrees on HTTP 200.
     *
     * @param  array<string, mixed>|null  $json
     * @return array<string, mixed>
     */
    public function extractHttp200ApplicationWarningDigest(?array $json): array
    {
        if (! is_array($json) || $json === []) {
            return [];
        }

        $codes = [];
        $messages = [];
        $roots = [
            'warnings', 'messages', 'errors',
            'error.warnings', 'error.messages', 'error.errors',
            'result.warnings', 'result.messages', 'result.errors',
            'data.warnings', 'data.messages', 'data.errors',
            'groupedItineraryResponse.messages',
            'groupedItineraryResponse.itineraryGroups.0.itineraries.0.messages',
        ];
        foreach ($roots as $dot) {
            $v = data_get($json, $dot);
            $this->appendWarningRowsFromNode($v, $codes, $messages);
        }

        return array_filter([
            'response_error_codes' => array_slice(array_values(array_unique($codes)), 0, 12),
            'response_error_messages' => array_slice(array_values(array_unique($messages)), 0, 12),
        ], static fn ($x): bool => $x !== null && $x !== []);
    }

    /**
     * @param  array<string, mixed>  $digest  Output of {@see extractHttp200ApplicationWarningDigest()}
     */
    public function http200ApplicationWarningDigestNonEmpty(array $digest): bool
    {
        $c = $digest['response_error_codes'] ?? [];
        $m = $digest['response_error_messages'] ?? [];

        return (is_array($c) && $c !== []) || (is_array($m) && $m !== []);
    }

    /**
     * Extract safe fare/offer linkage scalars from a Sabre revalidation response (no raw JSON).
     *
     * @param  array<string, mixed>|null  $json
     * @return array<string, mixed>
     */
    public function extractFareLinkage(?array $json): array
    {
        if (! is_array($json) || $json === []) {
            return [];
        }

        $selectedGirItinerary = $this->selectGroupedItineraryForRevalidation($json);
        $perSegment = $this->collectPerSegmentFareBasis($json);
        $fareBasisFirst = $perSegment !== [] ? (string) ($perSegment[0]['fare_basis_code'] ?? '') : '';

        $fareReference = $this->firstScalarFromPaths($selectedGirItinerary, [
            'pricingInformation.0.fareReference',
            'pricingInformation.0.fareRef',
        ]);
        if ($fareReference === '') {
            $fareReference = $this->firstScalarFromPaths($json, [
                'fareReference', 'fareRef', 'fare.reference', 'fare.fareReference', 'fare.fareBasisCode', 'fareBasisCode',
                'pricedItineraries.0.airItineraryPricingInfo.fareInfos.0.fareReference',
                'fareInfos.0.fareReference',
                'pricingInformation.0.fareReference',
                'pricingInformation.0.fareRef',
                'groupedItineraryResponse.itineraryGroups.0.itineraries.0.pricingInformation.0.fareReference',
                'groupedItineraryResponse.itineraryGroups.0.itineraries.0.pricingInformation.0.fareRef',
                'data.pricingInformation.0.fareReference',
                'result.pricingInformation.0.fareReference',
                'pricedItinerary.pricingInformation.0.fareReference',
            ]);
        }

        $priceQuoteReference = $this->firstScalarFromPaths($selectedGirItinerary, [
            'pricingInformation.0.priceQuoteReference',
            'pricingInformation.0.priceQuoteRef',
        ]);
        if ($priceQuoteReference === '') {
            $priceQuoteReference = $this->firstScalarFromPaths($json, [
                'priceQuoteReference', 'priceQuoteId', 'pricingInformation.0.priceQuoteReference',
                'pricedItineraries.0.priceQuoteReference',
                'priceQuote.reference',
                'groupedItineraryResponse.itineraryGroups.0.itineraries.0.pricingInformation.0.priceQuoteReference',
                'groupedItineraryResponse.itineraryGroups.0.itineraries.0.pricingInformation.0.priceQuoteRef',
                'data.priceQuoteReference',
                'result.priceQuoteReference',
            ]);
        }

        $offerReference = $this->firstScalarFromPaths($selectedGirItinerary, [
            'pricingInformation.0.offerItemId',
            'pricingInformation.0.offerItemRef',
            'pricingInformation.0.offer.id',
            'pricingInformation.0.offer.ref',
        ]);
        if ($offerReference === '') {
            $offerReference = $this->firstScalarFromPaths($json, [
                'offerReference', 'offerId', 'offerItemId', 'offerRef',
                'pricedItineraries.0.offerItemId',
                'pricingInformation.0.offerItemId',
                'pricingInformation.0.offerItemRef',
                'pricingInformation.0.offer.id',
                'pricingInformation.0.offer.ref',
                'pricingInformation.0.offerItem.id',
                'pricingInformation.0.offerItem.ref',
                'groupedItineraryResponse.itineraryGroups.0.itineraries.0.pricingInformation.0.offerItemId',
                'groupedItineraryResponse.itineraryGroups.0.itineraries.0.pricingInformation.0.offerItemRef',
                'groupedItineraryResponse.itineraryGroups.0.itineraries.0.pricingInformation.0.offer.id',
                'groupedItineraryResponse.itineraryGroups.0.itineraries.0.pricingInformation.0.offer.ref',
                'data.offerId',
                'data.offer.id',
                'result.offerId',
                'result.offer.id',
                'offers.0.id',
                'offers.0.offerId',
                'revalidatedOffer.id',
                'revalidatedOffer.offerId',
                'revalidatedOffer.offerItemId',
                'data.offers.0.id',
                'data.offers.0.offerId',
            ]);
        }

        $orderReference = $this->firstScalarFromPaths($json, [
            'orderId', 'orderRef', 'order.id', 'order.reference',
            'data.orderId', 'data.order.id', 'result.orderId', 'result.order.id',
            'booking.id', 'booking.reference', 'reservation.id',
        ]);

        $itineraryReference = $this->firstScalarFromPaths($selectedGirItinerary, ['id', 'ref']);
        if ($itineraryReference === '') {
            $itineraryReference = $this->firstScalarFromPaths($json, [
                'itineraryReference', 'itineraryId', 'itineraryRef',
                'pricedItineraries.0.id',
                'groupedItineraryResponse.itineraryGroups.0.itineraries.0.id',
                'groupedItineraryResponse.itineraryGroups.0.itineraries.0.ref',
                'data.itineraryId',
                'result.itineraryId',
                'itineraries.0.id',
                'pricedItinerary.id',
            ]);
        }

        $validatingCarrier = $this->firstScalarFromPaths($selectedGirItinerary, [
            'pricingInformation.0.fare.validatingCarrierCode',
        ]);
        if ($validatingCarrier === '') {
            $validatingCarrier = $this->firstScalarFromPaths($json, [
                'validatingCarrier', 'validatingCarrierCode',
                'pricedItineraries.0.airItineraryPricingInfo.validatingCarrier',
                'pricedItineraries.0.validatingCarrier',
                'pricedItineraries.0.airItineraryPricingInfo.validatingCarrierCode',
                'pricingInformation.0.fare.validatingCarrierCode',
                'data.validatingCarrier',
                'revalidatedOffer.validatingCarrier',
            ]);
        }

        $totalAmount = $this->firstNumericFromPaths($selectedGirItinerary, [
            'pricingInformation.0.fare.totalFare.totalPrice',
            'pricingInformation.0.fare.totalFare.amount',
        ]);
        if ($totalAmount === null) {
            $totalAmount = $this->firstNumericFromPaths($json, [
                'totalFare.amount', 'totalFare.totalPrice', 'totalFare.amountIncludingMarkup',
                'pricedItineraries.0.airItineraryPricingInfo.itinTotalFare.totalFare.amount',
                'pricedItineraries.0.airItineraryPricingInfo.itinTotalFare.totalFare.totalPrice',
                'pricingInformation.0.fare.totalFare.totalPrice',
                'pricingInformation.0.fare.totalFare.amount',
                'fare.totalFare.totalPrice',
                'fare.totalFare.amount',
                'data.totalFare.totalPrice',
                'result.totalFare.totalPrice',
                'revalidatedOffer.totalFare.totalPrice',
                'revalidatedOffer.totalPrice',
                'pricedItinerary.airItineraryPricingInfo.itinTotalFare.totalFare.totalPrice',
                'groupedItineraryResponse.itineraryGroups.0.itineraries.0.pricingInformation.0.fare.totalFare.totalPrice',
            ]);
        }

        $totalCurrency = $this->firstScalarFromPaths($selectedGirItinerary, [
            'pricingInformation.0.fare.totalFare.currency',
            'pricingInformation.0.fare.totalFare.currencyCode',
        ]);
        if ($totalCurrency === '') {
            $totalCurrency = $this->firstScalarFromPaths($json, [
                'totalFare.currencyCode', 'totalFare.currency',
                'pricedItineraries.0.airItineraryPricingInfo.itinTotalFare.totalFare.currencyCode',
                'pricedItineraries.0.airItineraryPricingInfo.itinTotalFare.totalFare.currency',
                'pricingInformation.0.fare.totalFare.currency',
                'pricingInformation.0.fare.totalFare.currencyCode',
                'fare.totalFare.currency',
                'fare.totalFare.currencyCode',
                'data.totalFare.currency',
                'result.totalFare.currency',
                'revalidatedOffer.totalFare.currency',
                'revalidatedOffer.currency',
            ]);
        }

        $pair = $this->findBoundedAmountCurrencyPair($json);
        if ($pair !== null) {
            if ($totalAmount === null && $pair['amount'] > 0) {
                $totalAmount = $pair['amount'];
            }
            if ($totalCurrency === '' && $pair['currency'] !== '') {
                $totalCurrency = $pair['currency'];
            }
        }

        $ticketingTimeLimit = $this->firstScalarFromPaths($json, [
            'ticketingTimeLimit', 'lastTicketDate',
            'pricedItineraries.0.airItineraryPricingInfo.fareInfos.0.lastTicketDate',
            'pricingInformation.0.fare.lastTicketDate',
            'paymentRules.ticketTimeLimit',
        ]);

        $bookingCode = $this->firstScalarFromPaths($json, [
            'bookingCode', 'classOfService',
        ]);

        $baggage = $this->collectBaggageSummary($json);

        $bookingClass = '';
        if ($perSegment !== []) {
            foreach ($perSegment as $row) {
                $c = (string) ($row['class_of_service'] ?? '');
                if ($c !== '') {
                    $bookingClass = $c;
                    break;
                }
            }
        }

        $revalidationRef = $this->firstScalarFromPaths($json, [
            'revalidationReference', 'revalidationId', 'revalidationRef',
            'data.revalidationId',
            'data.revalidationReference',
            'result.revalidationReference',
            'result.revalidationId',
            'revalidatedOffer.revalidationReference',
        ]);

        return array_filter([
            'per_segment' => $perSegment !== [] ? $perSegment : null,
            'fare_basis_codes' => $perSegment !== []
                ? array_values(array_unique(array_filter(array_map(
                    static fn (array $r): string => (string) ($r['fare_basis_code'] ?? ''),
                    $perSegment
                ), static fn (string $v): bool => $v !== '')))
                : ($fareBasisFirst !== '' ? [$fareBasisFirst] : []),
            'fare_basis_first' => $fareBasisFirst !== '' ? $fareBasisFirst : null,
            'fare_reference' => $fareReference !== '' ? $fareReference : null,
            'price_quote_reference' => $priceQuoteReference !== '' ? $priceQuoteReference : null,
            'offer_reference' => $offerReference !== '' ? $offerReference : null,
            'order_reference' => $orderReference !== '' ? $orderReference : null,
            'itinerary_reference' => $itineraryReference !== '' ? $itineraryReference : null,
            'revalidation_reference' => $revalidationRef !== '' ? $revalidationRef : null,
            'validating_carrier' => $validatingCarrier !== '' ? strtoupper($validatingCarrier) : null,
            'booking_code' => $bookingCode !== '' ? strtoupper(substr($bookingCode, 0, 8)) : null,
            'class_of_service_first' => $bookingClass !== '' ? $bookingClass : null,
            'revalidated_total' => $totalAmount !== null && $totalAmount > 0 ? $totalAmount : null,
            'revalidated_currency' => $totalCurrency !== '' ? strtoupper(substr($totalCurrency, 0, 6)) : null,
            'ticketing_time_limit' => $ticketingTimeLimit !== '' ? $ticketingTimeLimit : null,
            'baggage_summary' => $baggage !== '' ? $baggage : null,
        ], static fn ($v) => $v !== null && $v !== '' && $v !== []);
    }

    /**
     * Compact summary of a fare-linkage map for inspect commands / safe_summary persistence.
     *
     * @param  array<string, mixed>  $linkage
     * @return array<string, mixed>
     */
    public function linkageDigest(array $linkage): array
    {
        $fbc = isset($linkage['fare_basis_codes']) && is_array($linkage['fare_basis_codes'])
            ? array_values(array_filter(array_map(static fn ($v): string => (string) $v, $linkage['fare_basis_codes']), static fn (string $v): bool => $v !== ''))
            : [];

        $perSeg = is_array($linkage['per_segment_fare_basis'] ?? null)
            ? $linkage['per_segment_fare_basis']
            : (is_array($linkage['per_segment'] ?? null) ? $linkage['per_segment'] : []);
        $expected = isset($linkage['expected_segment_count']) && is_numeric($linkage['expected_segment_count'])
            ? (int) $linkage['expected_segment_count']
            : count($perSeg);
        $completeCount = 0;
        foreach ($perSeg as $row) {
            if (! is_array($row)) {
                continue;
            }
            if (trim((string) ($row['fare_basis_code'] ?? '')) !== '') {
                $completeCount++;
            }
        }

        return [
            'has_fare_basis' => $fbc !== [],
            'fare_basis_codes' => array_slice($fbc, 0, 12),
            'per_segment_fare_basis_count' => $completeCount,
            'expected_segment_count' => $expected > 0 ? $expected : null,
            'per_segment_fare_basis_complete' => $expected > 0 && $completeCount >= $expected && $completeCount === count($perSeg),
            'fare_basis_gaps' => $expected > 0 && $completeCount < $expected ? max(0, $expected - $completeCount) : 0,
            'has_fare_reference' => isset($linkage['fare_reference']) && trim((string) $linkage['fare_reference']) !== '',
            'has_price_quote_reference' => isset($linkage['price_quote_reference']) && trim((string) $linkage['price_quote_reference']) !== '',
            'has_offer_reference' => isset($linkage['offer_reference']) && trim((string) $linkage['offer_reference']) !== '',
            'has_order_reference' => isset($linkage['order_reference']) && trim((string) $linkage['order_reference']) !== '',
            'has_revalidation_reference' => isset($linkage['revalidation_reference']) && trim((string) $linkage['revalidation_reference']) !== '',
            'has_itinerary_reference' => isset($linkage['itinerary_reference']) && trim((string) $linkage['itinerary_reference']) !== '',
            'has_validating_carrier' => isset($linkage['validating_carrier']) && trim((string) $linkage['validating_carrier']) !== '',
            'has_revalidated_fare' => isset($linkage['revalidated_total']) && is_numeric($linkage['revalidated_total']) && (float) $linkage['revalidated_total'] > 0,
            'has_revalidated_currency' => isset($linkage['revalidated_currency']) && trim((string) $linkage['revalidated_currency']) !== '',
            'has_baggage_summary' => isset($linkage['baggage_summary']) && trim((string) $linkage['baggage_summary']) !== '',
            'has_ticketing_time_limit' => isset($linkage['ticketing_time_limit']) && trim((string) $linkage['ticketing_time_limit']) !== '',
        ];
    }

    /**
     * Safe 4xx revalidation digest for inspect output and booking short-circuit metadata. No raw body.
     *
     * @param  array<string, mixed>|null  $json
     * @return array<string, mixed>
     */
    public function extractSafeErrorDigest(?array $json): array
    {
        if (! is_array($json) || $json === []) {
            return [];
        }

        $codes = [];
        $messages = [];
        $missing = [];
        $paths = [];
        foreach ($this->errorRows($json) as $row) {
            $code = $this->firstScalarFromPaths($row, ['code', 'errorCode', 'type', 'status', 'Number', 'number']);
            if ($code !== '') {
                $codes[] = $code;
            }
            foreach (['message', 'detail', 'title', 'description', 'Message', 'Description'] as $k) {
                $msg = trim((string) ($row[$k] ?? ''));
                if ($msg !== '') {
                    $messages[] = self::safe($msg, 180);
                    break;
                }
            }
            foreach (['missingField', 'missingFields', 'field', 'fields', 'parameter', 'parameters'] as $k) {
                $v = $row[$k] ?? null;
                foreach (is_array($v) ? $v : [$v] as $item) {
                    if (is_string($item) || is_numeric($item)) {
                        $missing[] = self::safe((string) $item, 120);
                    }
                }
            }
            foreach (['path', 'source.pointer', 'source.parameter', 'validationPath'] as $p) {
                $path = data_get($row, $p);
                if (is_string($path) && trim($path) !== '') {
                    $paths[] = self::safe($path, 160);
                }
            }
        }

        foreach (['message', 'error.message', 'error_description', 'errors.0.message'] as $p) {
            $msg = data_get($json, $p);
            if (is_string($msg) && trim($msg) !== '') {
                $messages[] = self::safe($msg, 180);
            }
        }

        $requestId = $this->firstScalarFromPaths($json, [
            'requestId', 'requestID', 'request.id', 'request.correlationId',
            'correlationId', 'traceId', 'diagnostics.requestId',
        ]);

        return $this->appendHeuristicSabreRevalidateHints(array_filter([
            'response_error_codes' => array_slice(array_values(array_unique($codes)), 0, 12),
            'response_error_messages' => array_slice(array_values(array_unique($messages)), 0, 12),
            'response_missing_fields' => array_slice(array_values(array_unique($missing)), 0, 12),
            'response_validation_paths' => array_slice(array_values(array_unique($paths)), 0, 12),
            'request_id' => $requestId !== '' ? $requestId : null,
        ], static fn ($v): bool => $v !== null && $v !== [] && $v !== ''));
    }

    /**
     * @param  array<string, mixed>  $json
     * @return list<array<string, string>>
     */
    protected function collectPerSegmentFareBasis(array $json): array
    {
        $out = [];
        $selectedGirItinerary = $this->selectGroupedItineraryForRevalidation($json);

        $tryRows = [
            data_get($selectedGirItinerary, 'pricingInformation.0.fare.fareInfos'),
            data_get($selectedGirItinerary, 'pricingInformation.0.fare.fareComponents'),
            data_get($json, 'data.pricedItineraries.0.airItineraryPricingInfo.fareInfos'),
            data_get($json, 'result.pricedItineraries.0.airItineraryPricingInfo.fareInfos'),
            data_get($json, 'revalidatedOffer.fareInfos'),
            data_get($json, 'pricedItineraries.0.airItineraryPricingInfo.fareInfos'),
            data_get($json, 'fareInfos'),
            data_get($json, 'pricingInformation.0.fare.fareInfos'),
            data_get($json, 'pricingInformation.0.fareComponents'),
            data_get($json, 'groupedItineraryResponse.fareComponentDescs'),
        ];
        foreach ($tryRows as $rows) {
            if (! is_array($rows)) {
                continue;
            }
            foreach ($rows as $row) {
                if (! is_array($row)) {
                    continue;
                }
                $code = trim((string) ($row['fareBasisCode'] ?? $row['fareBasis'] ?? ''));
                if ($code === '') {
                    continue;
                }
                $out[] = [
                    'fare_basis_code' => strtoupper(substr($code, 0, 32)),
                    'class_of_service' => strtoupper(trim((string) ($row['bookingCode'] ?? $row['resBookDesigCode'] ?? $row['classOfService'] ?? ''))),
                    'origin' => strtoupper(trim((string) ($row['departureAirport'] ?? data_get($row, 'departure.locationCode') ?? ''))),
                    'destination' => strtoupper(trim((string) ($row['arrivalAirport'] ?? data_get($row, 'arrival.locationCode') ?? ''))),
                ];
            }
            if ($out !== []) {
                return $out;
            }
        }

        foreach ([
            data_get($selectedGirItinerary, 'pricingInformation.0.fare.passengerInfoList'),
            data_get($json, 'passengerInfoList'),
            data_get($json, 'pricingInformation.0.fare.passengerInfoList'),
            data_get($json, 'groupedItineraryResponse.itineraryGroups.0.itineraries.0.pricingInformation.0.fare.passengerInfoList'),
        ] as $list) {
            if (! is_array($list)) {
                continue;
            }
            $out = $this->collectPerSegmentFareBasisFromPassengerInfoList($list);
            if ($out !== []) {
                return $out;
            }
        }

        return $out;
    }

    /**
     * @param  array<string, mixed>  $json
     * @return array<string, mixed>
     */
    protected function selectGroupedItineraryForRevalidation(array $json): array
    {
        $groups = data_get($json, 'groupedItineraryResponse.itineraryGroups');
        if (! is_array($groups)) {
            return [];
        }

        $fallback = [];
        foreach ($groups as $group) {
            $itineraries = is_array($group) && is_array($group['itineraries'] ?? null) ? $group['itineraries'] : [];
            foreach ($itineraries as $itinerary) {
                if (! is_array($itinerary)) {
                    continue;
                }
                if ($fallback === []) {
                    $fallback = $itinerary;
                }
                if ($this->isTruthyCurrentItineraryFlag($itinerary['currentItinerary'] ?? null)) {
                    return $itinerary;
                }
            }
        }

        return $fallback;
    }

    protected function isTruthyCurrentItineraryFlag(mixed $value): bool
    {
        if ($value === true || $value === 1) {
            return true;
        }
        if (is_string($value)) {
            return in_array(strtolower(trim($value)), ['true', '1', 'yes'], true);
        }

        return false;
    }

    /**
     * @param  array<int, mixed>  $list
     * @return list<array<string, string>>
     */
    protected function collectPerSegmentFareBasisFromPassengerInfoList(array $list): array
    {
        $out = [];
        foreach ($list as $wrap) {
            if (! is_array($wrap)) {
                continue;
            }
            $pi = is_array($wrap['passengerInfo'] ?? null) ? $wrap['passengerInfo'] : [];
            foreach ($pi['fareComponents'] ?? [] as $fc) {
                if (! is_array($fc)) {
                    continue;
                }
                $fcFareBasis = trim((string) ($fc['fareBasisCode'] ?? $fc['fareBasis'] ?? ''));
                foreach ($fc['segments'] ?? [] as $segWrap) {
                    $seg = is_array($segWrap['segment'] ?? null) ? $segWrap['segment'] : (is_array($segWrap) ? $segWrap : []);
                    if ($seg === []) {
                        continue;
                    }
                    $code = trim((string) ($seg['fareBasisCode'] ?? $seg['fareBasis'] ?? $fcFareBasis));
                    if ($code === '') {
                        $code = trim((string) ($seg['bookingCode'] ?? data_get($seg, 'segment.bookingCode') ?? ''));
                    }
                    if ($code === '') {
                        continue;
                    }
                    $out[] = [
                        'fare_basis_code' => strtoupper(substr($code, 0, 32)),
                        'class_of_service' => strtoupper(trim((string) ($seg['resBookDesigCode'] ?? $seg['bookingCode'] ?? $seg['classOfService'] ?? ''))),
                        'origin' => strtoupper(trim((string) (data_get($seg, 'departure.locationCode') ?? data_get($seg, 'departure.airport') ?? ''))),
                        'destination' => strtoupper(trim((string) (data_get($seg, 'arrival.locationCode') ?? data_get($seg, 'arrival.airport') ?? ''))),
                    ];
                }
            }
            if ($out !== []) {
                break;
            }
        }

        return $out;
    }

    /**
     * @param  array<string, mixed>  $json
     * @param  list<string>  $paths
     */
    protected function firstScalarFromPaths(array $json, array $paths): string
    {
        foreach ($paths as $p) {
            $v = data_get($json, $p);
            if (is_string($v) && trim($v) !== '') {
                return substr(trim($v), 0, self::SAFE_MAX);
            }
            if (is_numeric($v) && (string) $v !== '') {
                return substr((string) $v, 0, self::SAFE_MAX);
            }
        }

        return '';
    }

    /**
     * @param  array<string, mixed>  $json
     * @param  list<string>  $paths
     */
    protected function firstNumericFromPaths(array $json, array $paths): ?float
    {
        foreach ($paths as $p) {
            $v = data_get($json, $p);
            if (is_numeric($v) && (float) $v > 0) {
                return (float) $v;
            }
        }

        return null;
    }

    /**
     * @param  array<string, mixed>  $json
     */
    protected function collectBaggageSummary(array $json): string
    {
        $candidates = [
            data_get($json, 'pricedItineraries.0.airItineraryPricingInfo.fareInfos.0.bagDetails'),
            data_get($json, 'pricingInformation.0.fare.bagDetails'),
            data_get($json, 'baggage'),
            data_get($json, 'baggageAllowance'),
        ];
        $parts = [];
        foreach ($candidates as $c) {
            if (is_string($c) && trim($c) !== '') {
                $parts[] = self::safe(trim($c));

                continue;
            }
            if (! is_array($c)) {
                continue;
            }
            foreach ($c as $row) {
                if (is_string($row) && trim($row) !== '') {
                    $parts[] = self::safe(trim($row));
                } elseif (is_array($row)) {
                    foreach (['description', 'summary', 'text', 'pieces', 'pieceCount', 'weight'] as $k) {
                        if (isset($row[$k]) && (is_string($row[$k]) || is_numeric($row[$k]))) {
                            $parts[] = self::safe((string) $row[$k]);
                            break;
                        }
                    }
                }
            }
            if ($parts !== []) {
                break;
            }
        }

        return self::safe(implode(' · ', array_slice(array_unique(array_filter($parts)), 0, 4)));
    }

    /**
     * @param  array<string, mixed>  $draft
     * @return array<string, mixed>
     */
    protected function mergeDraftShopSources(array $draft): array
    {
        $ctx = is_array($draft['_sabre_shop_context'] ?? null) ? $draft['_sabre_shop_context'] : [];
        $ids = is_array($draft['_sabre_shop_identifiers'] ?? null) ? $draft['_sabre_shop_identifiers'] : [];
        if ($ctx === [] && $ids === []) {
            return [];
        }
        if ($ids === []) {
            return $ctx;
        }
        if ($ctx === []) {
            return $ids;
        }
        $out = $ctx;
        foreach ($ids as $k => $v) {
            if (! is_string($k) || trim($k) === '') {
                continue;
            }
            $cur = $out[$k] ?? null;
            $empty = $cur === null || $cur === '' || $cur === [];
            if (! array_key_exists($k, $out) || $empty) {
                $out[$k] = $v;
            }
        }

        return $out;
    }

    /**
     * @param  array<string, mixed>  $shopContext  Sanitized shop context
     * @return array<string, mixed>
     */
    protected function buildPricingInformationLinkageEnvelope(array $shopContext): array
    {
        $row = array_filter([
            'ref' => $this->firstContextScalar($shopContext, [
                'pricing_information_ref', 'pricing_0_ref', 'pricing_0_pricingRef', 'pricing_0_pricingInformationRef',
            ]),
            'id' => $this->firstContextScalar($shopContext, ['pricing_information_id', 'pricing_0_id']),
            'pricingSubsource' => $this->firstContextScalar($shopContext, [
                'pricing_subsource', 'pricing_0_pricingSubSource', 'pricing_0_pricingSubsource',
            ]),
            'fareSource' => $this->firstContextScalar($shopContext, ['fare_source', 'pricing_0_fare_source']),
            'offerItemId' => $this->firstContextScalar($shopContext, ['pricing_0_offerItemId']),
            'offerItemRef' => $this->firstContextScalar($shopContext, ['pricing_0_offerItemRef']),
            'offerId' => $this->firstContextScalar($shopContext, ['offer_id', 'pricing_0_offer_id']),
            'offerRef' => $this->firstContextScalar($shopContext, ['offer_ref', 'pricing_0_offer_ref']),
            'orderRef' => $this->firstContextScalar($shopContext, ['order_ref', 'pricing_0_order_ref', 'pricing_0_order_id']),
            'validatingCarrierCode' => $this->firstContextScalar($shopContext, ['validating_carrier', 'validating_carrier_code']),
        ], static fn ($v) => $v !== null && $v !== '');

        if (($row['offerItemId'] ?? null) === null || $row['offerItemId'] === '') {
            $fallbackOffer = $this->firstContextScalar($shopContext, ['offer_id', 'offer_ref', 'pricing_0_offerItemId']);
            if ($fallbackOffer !== null && $fallbackOffer !== '') {
                $row['offerItemId'] = $fallbackOffer;
            }
        }

        return array_filter($row, static fn ($v) => $v !== null && $v !== '');
    }

    /**
     * @param  array<string, mixed>  $digest  Output of {@see extractSafeErrorDigest()} before hints
     * @return array<string, mixed>
     */
    protected function appendHeuristicSabreRevalidateHints(array $digest): array
    {
        $codes = isset($digest['response_error_codes']) && is_array($digest['response_error_codes'])
            ? $digest['response_error_codes'] : [];
        $messages = isset($digest['response_error_messages']) && is_array($digest['response_error_messages'])
            ? $digest['response_error_messages'] : [];
        $tokens = [];
        foreach (array_merge($codes, $messages) as $t) {
            $s = trim((string) $t);
            if ($s === '') {
                continue;
            }
            if (preg_match('/^\d{3,8}$/', $s) === 1) {
                $tokens[$s] = true;
            }
            if (str_contains($s, '27131')) {
                $tokens['27131'] = true;
            }
        }
        /** @var array<string, string> $map */
        $map = [
            '27131' => 'Heuristic (not certain): Sabre code 27131 sometimes appears alongside incomplete /v4/shop/flights/revalidate linkage (for example missing pricingInformation or offer references).',
        ];
        $hints = [];
        foreach (array_keys($tokens) as $tok) {
            if (isset($map[$tok])) {
                $hints[] = $map[$tok];
            }
        }
        if ($hints !== []) {
            $digest['response_error_hints'] = array_values(array_unique($hints));
        }

        return $digest;
    }

    /**
     * @param  array<string, mixed>  $shopIds
     * @return array<string, mixed>
     */
    protected function sanitizeShopContext(array $shopIds): array
    {
        $out = [];
        foreach ($shopIds as $k => $v) {
            if (! is_string($k) || trim($k) === '') {
                continue;
            }
            if (is_string($v) || is_numeric($v) || is_bool($v)) {
                $safe = substr(trim((string) $v), 0, self::SAFE_MAX);
                if ($safe !== '') {
                    $out[substr($k, 0, 64)] = $safe;
                }

                continue;
            }
            if (is_array($v)) {
                $clean = [];
                foreach ($v as $nk => $nv) {
                    if (is_string($nv) || is_numeric($nv) || is_bool($nv)) {
                        $safe = substr(trim((string) $nv), 0, self::SAFE_MAX);
                        if ($safe !== '') {
                            $clean[$nk] = $safe;
                        }
                    } elseif (is_array($nv)) {
                        $nested = array_values(array_filter(array_map(static function ($item): ?string {
                            if (! is_string($item) && ! is_numeric($item) && ! is_bool($item)) {
                                return null;
                            }

                            return substr(trim((string) $item), 0, self::SAFE_MAX);
                        }, $nv), static fn (?string $item): bool => $item !== null && $item !== ''));
                        if ($nested !== []) {
                            $clean[$nk] = array_slice($nested, 0, 24);
                        }
                    }
                }
                if ($clean !== []) {
                    $out[substr($k, 0, 64)] = array_is_list($v) ? array_slice(array_values($clean), 0, 48) : $clean;
                }
            }
        }

        return $out;
    }

    /**
     * @param  list<array<string, mixed>>  $segments
     * @param  array<string, mixed>  $shopContext
     * @return list<string>
     */
    protected function fareBasisCodesFromSegmentsAndContext(array $segments, array $shopContext): array
    {
        $codes = [];
        foreach ($segments as $seg) {
            $code = strtoupper(trim((string) ($seg['fare_basis_code'] ?? '')));
            if ($code !== '') {
                $codes[] = substr($code, 0, 32);
            }
        }
        foreach ($shopContext['fare_basis_codes'] ?? [] as $code) {
            if (is_string($code) || is_numeric($code)) {
                $codes[] = strtoupper(substr(trim((string) $code), 0, 32));
            }
        }
        $csv = trim((string) ($shopContext['fare_basis_codes_csv'] ?? ''));
        if ($csv !== '') {
            foreach (explode(',', $csv) as $code) {
                $code = strtoupper(substr(trim($code), 0, 32));
                if ($code !== '') {
                    $codes[] = $code;
                }
            }
        }

        return array_slice(array_values(array_unique(array_filter($codes))), 0, 12);
    }

    /**
     * @param  list<string>  $keys
     */
    protected function firstContextScalar(array $context, array $keys): ?string
    {
        foreach ($keys as $key) {
            $value = $context[$key] ?? null;
            if (is_string($value) || is_numeric($value)) {
                $value = substr(trim((string) $value), 0, self::SAFE_MAX);
                if ($value !== '') {
                    return $value;
                }
            }
        }

        return null;
    }

    /**
     * @param  array<string, mixed>  $json
     * @return list<array<string, mixed>>
     */
    protected function errorRows(array $json): array
    {
        $rows = [];
        foreach (['errors', 'error.errors', 'error', 'Error', 'response.errors'] as $p) {
            $v = data_get($json, $p);
            if (is_array($v) && array_is_list($v)) {
                foreach ($v as $row) {
                    if (is_array($row)) {
                        $rows[] = $row;
                    }
                }
            } elseif (is_array($v)) {
                $rows[] = $v;
            }
        }

        return $rows !== [] ? $rows : [$json];
    }

    /**
     * @param  list<array<string, mixed>>  $segments
     * @return list<array<string, mixed>>
     */
    protected function buildClientItinerarySegments(array $segments): array
    {
        $out = [];
        foreach ($segments as $idx => $seg) {
            if (! is_array($seg)) {
                continue;
            }
            $mkt = strtoupper(trim((string) ($seg['carrier'] ?? $seg['airline_code'] ?? '')));
            $op = strtoupper(trim((string) ($seg['operating_airline_code'] ?? '')));
            $out[] = array_filter([
                'segment_number' => $idx + 1,
                'origin' => strtoupper(trim((string) ($seg['origin'] ?? ''))),
                'destination' => strtoupper(trim((string) ($seg['destination'] ?? ''))),
                'departure_at' => (string) ($seg['departure_at'] ?? $seg['depart_at'] ?? ''),
                'arrival_at' => (string) ($seg['arrival_at'] ?? $seg['arrive_at'] ?? ''),
                'marketing_carrier' => $mkt !== '' ? $mkt : null,
                'operating_carrier' => $op !== '' ? $op : null,
                'flight_number' => trim((string) ($seg['flight_number'] ?? $seg['flight_no'] ?? '')),
                'class_of_service' => isset($seg['booking_class']) ? strtoupper(trim((string) $seg['booking_class'])) : null,
                'cabin' => isset($seg['segment_cabin_code']) ? strtoupper(trim((string) $seg['segment_cabin_code'])) : null,
                'fare_basis_code' => isset($seg['fare_basis_code']) ? strtoupper(trim((string) $seg['fare_basis_code'])) : null,
            ], static fn ($v) => $v !== null && $v !== '');
        }

        return $out;
    }

    /**
     * @param  list<array<string, mixed>>  $segments
     * @return list<array<string, string>>
     */
    protected function collectCabinPrefs(array $segments): array
    {
        $out = [];
        $seen = [];
        foreach ($segments as $seg) {
            if (! is_array($seg)) {
                continue;
            }
            $cab = strtoupper(trim((string) ($seg['segment_cabin_code'] ?? '')));
            if ($cab === '' || isset($seen[$cab])) {
                continue;
            }
            $seen[$cab] = true;
            $out[] = ['Cabin' => $cab, 'PreferLevel' => 'Preferred'];
        }

        return $out;
    }

    /**
     * @param  array<string, mixed>  $tree
     * @return list<string>
     */
    protected function collectNestedKeyPathsDepth3(array $tree): array
    {
        $out = [];
        $seen = [];
        $walker = null;
        $walker = function (array $node, string $prefix, int $depth) use (&$out, &$seen, &$walker): void {
            if ($depth > 3 || count($out) >= self::DIGEST_MAX_KEY_PATHS) {
                return;
            }
            if (array_is_list($node)) {
                $path = $prefix === '' ? '[]' : $prefix.'[]';
                if (! isset($seen[$path])) {
                    $seen[$path] = true;
                    $out[] = $path.' (n='.count($node).')';
                }
                if (isset($node[0]) && is_array($node[0]) && $depth < 3) {
                    $walker($node[0], $prefix === '' ? '[0]' : $prefix.'[0]', $depth + 1);
                }

                return;
            }
            foreach ($node as $k => $v) {
                if (! is_string($k) || $k === '') {
                    continue;
                }
                if ($this->isRiskyDigestKey($k)) {
                    continue;
                }
                $path = $prefix === '' ? $k : $prefix.'.'.$k;
                if (! isset($seen[$path])) {
                    $seen[$path] = true;
                    $out[] = $path;
                }
                if (count($out) >= self::DIGEST_MAX_KEY_PATHS) {
                    return;
                }
                if (is_array($v) && $depth < 3) {
                    $walker($v, $path, $depth + 1);
                }
            }
        };
        $walker($tree, '', 0);

        return $out;
    }

    /**
     * @param  array<string, mixed>  $tree
     * @return list<string>
     */
    protected function collectRevalidateScalarCandidates(array $tree): array
    {
        $interest = [
            'amount', 'currency', 'currencycode', 'totalprice', 'totalfare', 'farebasis', 'farebasiscode',
            'revalidationreference', 'revalidationid', 'offerid', 'offerref', 'orderid', 'orderref',
            'itineraryid', 'itineraryref', 'pricequotereference', 'validatingcarrier', 'ticketingtimelimit',
        ];
        $out = [];
        $seen = [];
        $walk = null;
        $walk = function (mixed $node, string $prefix, int $depth) use (&$out, &$seen, &$walk, $interest): void {
            if ($depth > 4 || count($out) >= self::DIGEST_MAX_CANDIDATES) {
                return;
            }
            if (! is_array($node)) {
                return;
            }
            if (array_is_list($node)) {
                foreach (array_slice($node, 0, 3) as $idx => $item) {
                    if (is_array($item)) {
                        $p = $prefix === '' ? '['.(string) $idx.']' : $prefix.'['.(string) $idx.']';
                        $walk($item, $p, $depth + 1);
                    }
                }

                return;
            }
            foreach ($node as $k => $v) {
                if (! is_string($k)) {
                    continue;
                }
                if ($this->isRiskyDigestKey($k)) {
                    continue;
                }
                $path = $prefix === '' ? $k : $prefix.'.'.$k;
                $kl = strtolower($k);
                if (is_scalar($v) && $v !== null && $v !== '') {
                    foreach ($interest as $frag) {
                        if (str_contains($kl, $frag)) {
                            $cap = self::DIGEST_SCALAR_CAP;
                            $snippet = substr(trim((string) $v), 0, $cap);
                            $line = $path.'='.$snippet;
                            if (! isset($seen[$line])) {
                                $seen[$line] = true;
                                $out[] = $line;
                            }
                            break;
                        }
                    }
                } elseif (is_array($v) && in_array($kl, ['warnings', 'messages', 'errors'], true)) {
                    $cnt = array_is_list($v) ? count($v) : count($v);
                    $line = $path.'=(n='.$cnt.')';
                    if (! isset($seen[$line])) {
                        $seen[$line] = true;
                        $out[] = $line;
                    }
                } elseif (is_array($v)) {
                    $walk($v, $path, $depth + 1);
                }
            }
        };
        $walk($tree, '', 0);

        return $out;
    }

    protected function isRiskyDigestKey(string $key): bool
    {
        $kl = strtolower($key);
        foreach (self::DIGEST_RISKY_KEY_FRAGMENTS as $frag) {
            if (str_contains($kl, $frag)) {
                return true;
            }
        }

        return false;
    }

    /**
     * @param  list<string>  $codes
     * @param  list<string>  $messages
     */
    protected function appendWarningRowsFromNode(mixed $node, array &$codes, array &$messages): void
    {
        if ($node === null) {
            return;
        }
        if (is_string($node) && trim($node) !== '') {
            $messages[] = self::safe($node, 180);

            return;
        }
        if (! is_array($node)) {
            return;
        }
        if (array_is_list($node)) {
            foreach (array_slice($node, 0, 24) as $row) {
                $this->appendWarningRowsFromNode($row, $codes, $messages);
            }

            return;
        }
        $code = $this->firstScalarFromPaths($node, ['code', 'errorCode', 'type', 'status', 'Number', 'number']);
        if ($code !== '') {
            $codes[] = substr($code, 0, 48);
        }
        foreach (['message', 'detail', 'title', 'description', 'Message', 'Description'] as $k) {
            $msg = trim((string) ($node[$k] ?? ''));
            if ($msg !== '') {
                $messages[] = self::safe($msg, 180);
                break;
            }
        }
    }

    /**
     * @return array{amount: float, currency: string}|null
     */
    protected function findBoundedAmountCurrencyPair(array $json): ?array
    {
        $queue = [$json];
        $visited = 0;
        while ($queue !== [] && $visited < 520) {
            $node = array_shift($queue);
            $visited++;
            if (! is_array($node)) {
                continue;
            }
            $hit = $this->tryExtractAmountCurrencyFromAssoc($node);
            if ($hit !== null) {
                return $hit;
            }
            foreach ($node as $k => $child) {
                if (! is_string($k) || $this->isRiskyDigestKey($k)) {
                    continue;
                }
                if (! is_array($child)) {
                    continue;
                }
                if (count($queue) < 400) {
                    $queue[] = $child;
                }
            }
        }

        return null;
    }

    /**
     * @param  array<string, mixed>  $node
     * @return array{amount: float, currency: string}|null
     */
    protected function tryExtractAmountCurrencyFromAssoc(array $node): ?array
    {
        if (isset($node['totalFare']) && is_array($node['totalFare'])) {
            $tf = $node['totalFare'];
            if (isset($tf['totalPrice']) && is_numeric($tf['totalPrice']) && (float) $tf['totalPrice'] > 0) {
                $amount = (float) $tf['totalPrice'];
                $cur = trim((string) ($tf['currency'] ?? $tf['currencyCode'] ?? ''));
                if ($cur !== '') {
                    return ['amount' => $amount, 'currency' => strtoupper(substr($cur, 0, 6))];
                }
            }
        }

        $amount = null;
        foreach (['totalPrice', 'amount'] as $ak) {
            if (isset($node[$ak]) && is_numeric($node[$ak]) && (float) $node[$ak] > 0) {
                $amount = (float) $node[$ak];

                break;
            }
        }
        if ($amount === null) {
            return null;
        }
        $cur = '';
        foreach (['currency', 'currencyCode'] as $ck) {
            if (isset($node[$ck]) && is_string($node[$ck]) && trim($node[$ck]) !== '') {
                $cur = trim($node[$ck]);

                break;
            }
        }
        if ($cur === '') {
            return null;
        }

        return ['amount' => $amount, 'currency' => strtoupper(substr($cur, 0, 6))];
    }

    protected static function safe(string $v, int $max = self::SAFE_MAX): string
    {
        $v = trim($v);
        if ($max < 1) {
            return '';
        }
        if (strlen($v) <= $max) {
            return $v;
        }

        return substr($v, 0, $max);
    }

    /**
     * Pre-flight production gatekeeper — blocks Sabre HTTP when payload is structurally unsafe.
     *
     * @param  array<string, mixed>  $payload  Full builder envelope (may include {@code _ota_*})
     * @param  array<string, mixed>  $internalDraft
     *
     * @throws SabreRevalidateGatekeeperException
     */
    public function assertGatekeeperOrThrow(array $payload, array $internalDraft): void
    {
        $violations = [];
        $style = (string) ($payload['_ota_revalidate_payload_style'] ?? 'bfm_revalidate_v1');

        if (app()->environment('production') && in_array($style, self::PRODUCTION_BLOCKED_REVALIDATE_STYLES, true)) {
            $violations[] = 'production_blocked_payload_style:'.$style;
        }

        $draftSegments = is_array($internalDraft['segments'] ?? null) ? $internalDraft['segments'] : [];
        $expectedSegmentCount = count($draftSegments);
        if ($expectedSegmentCount < 1) {
            $violations[] = 'missing_draft_segments';
        }

        $wireNodes = $this->collectWireFlightNodesForGatekeeper($payload);
        if ($wireNodes === []) {
            $violations[] = 'no_wire_flight_nodes';
        }
        if ($expectedSegmentCount > 0 && count($wireNodes) !== $expectedSegmentCount) {
            $violations[] = 'wire_segment_count_mismatch:wire='.count($wireNodes).':draft='.$expectedSegmentCount;
        }

        foreach ($wireNodes as $idx => $node) {
            $cos = strtoupper(trim((string) ($node['class_of_service'] ?? '')));
            if ($cos === '') {
                $violations[] = 'missing_class_of_service:segment='.($idx + 1);
            }
            foreach ([
                'origin' => 'missing_origin',
                'destination' => 'missing_destination',
                'departure_at' => 'missing_departure_datetime',
                'arrival_at' => 'missing_arrival_datetime',
                'marketing_carrier' => 'missing_marketing_carrier',
                'flight_number' => 'missing_flight_number',
            ] as $field => $code) {
                if (trim((string) ($node[$field] ?? '')) === '') {
                    $violations[] = $code.':segment='.($idx + 1);
                }
            }
        }

        $shop = $this->mergeDraftShopSources($internalDraft);
        $requested = is_array($shop['requested']['passenger_counts'] ?? null)
            ? $shop['requested']['passenger_counts']
            : (is_array($shop['requested'] ?? null) && isset($shop['requested']['adults'])
                ? [
                    'adults' => (int) ($shop['requested']['adults'] ?? 0),
                    'children' => (int) ($shop['requested']['children'] ?? 0),
                    'infants' => (int) ($shop['requested']['infants'] ?? 0),
                ]
                : []);
        if ($requested !== []) {
            $wirePtc = $this->passengerTypeCountsFromPayload($payload, $internalDraft);
            foreach (['adults' => 'ADT', 'children' => 'CHD', 'infants' => 'INF'] as $k => $ptc) {
                $expected = (int) ($requested[$k] ?? 0);
                $actual = (int) ($wirePtc[$ptc] ?? 0);
                if ($k === 'adults' && $expected < 1 && $actual >= 1) {
                    continue;
                }
                if ($expected !== $actual) {
                    $violations[] = 'passenger_count_mismatch:'.$ptc.':expected='.$expected.':wire='.$actual;
                }
            }
        }

        if ($violations !== []) {
            throw new SabreRevalidateGatekeeperException('gatekeeper_failed', $violations);
        }
    }

    /**
     * @param  array<string, mixed>  $json
     * @return array{failed: bool, failure_class: string, codes: list<string>, messages: list<string>}|null
     */
    public function evaluateGroupedItineraryMessages(?array $json): ?array
    {
        if (! is_array($json) || $json === []) {
            return null;
        }

        $codes = [];
        $messages = [];
        $selectedGirItinerary = $this->selectGroupedItineraryForRevalidation($json);
        $roots = [
            'groupedItineraryResponse.messages',
        ];
        foreach ($roots as $dot) {
            $v = data_get($json, $dot);
            $this->appendGroupedItineraryMessageRows($v, $codes, $messages);
        }
        $this->appendGroupedItineraryMessageRows(data_get($selectedGirItinerary, 'messages'), $codes, $messages);
        $this->appendGroupedItineraryMessageRows(data_get($selectedGirItinerary, 'pricingInformation.0.messages'), $codes, $messages);

        if ($codes === [] && $messages === []) {
            return null;
        }

        $failed = false;
        $failureClass = 'gir_message_warning';
        foreach ($this->groupedItineraryMessageRowsFlat($json) as $row) {
            if (! is_array($row)) {
                continue;
            }
            $severity = strtoupper(trim((string) ($row['severity'] ?? '')));
            $type = strtoupper(trim((string) ($row['type'] ?? '')));
            $code = trim((string) ($row['code'] ?? ''));
            $text = strtoupper(trim((string) ($row['text'] ?? $row['message'] ?? $row['shortText'] ?? '')));

            if ($severity === 'ERROR' && $type === 'MIP' && $code === '5053') {
                $failed = true;
                $failureClass = 'mip_5053';

                continue;
            }
            foreach (self::GIR_FATAL_MESSAGE_FRAGMENTS as $frag) {
                if ($text !== '' && str_contains($text, strtoupper($frag))) {
                    $failed = true;
                    $failureClass = 'gir_fatal_text:'.str_replace(' ', '_', strtolower($frag));
                }
            }
        }

        if (! $failed) {
            return null;
        }

        return [
            'failed' => true,
            'failure_class' => $failureClass,
            'codes' => array_slice(array_values(array_unique($codes)), 0, 12),
            'messages' => array_slice(array_values(array_unique($messages)), 0, 12),
        ];
    }

    /**
     * @param  array<string, mixed>  $linkage
     * @param  array<string, mixed>  $internalDraft
     * @return array{failed: bool, failure_class: string, message: string}|null
     */
    public function evaluateRevalidationPricingTripwire(array $linkage, array $internalDraft, ?float $tolerance = null): ?array
    {
        $tol = $tolerance ?? self::DEFAULT_REVALIDATE_FARE_TOLERANCE;
        $fare = is_array($internalDraft['fare'] ?? null) ? $internalDraft['fare'] : [];
        $expectedTotal = (float) ($fare['amount'] ?? 0);
        $expectedCurrency = strtoupper(trim((string) ($fare['currency'] ?? '')));
        $actualTotal = is_numeric($linkage['revalidated_total'] ?? null) ? (float) $linkage['revalidated_total'] : 0.0;
        $actualCurrency = strtoupper(trim((string) ($linkage['revalidated_currency'] ?? '')));

        if ($actualTotal <= 0 || $actualCurrency === '') {
            return [
                'failed' => true,
                'failure_class' => 'missing_revalidated_pricing',
                'message' => 'Revalidation response missing revalidated total or currency.',
            ];
        }

        if ($expectedTotal > 0 && $expectedCurrency !== '' && $actualCurrency === $expectedCurrency) {
            $delta = abs($actualTotal - $expectedTotal);
            $allowed = max($tol, $expectedTotal * 0.01);
            if ($delta > $allowed) {
                return [
                    'failed' => true,
                    'failure_class' => 'fare_tolerance_exceeded',
                    'message' => 'Revalidated fare exceeds accepted tolerance vs selected offer.',
                ];
            }
        }

        return null;
    }

    /**
     * @param  array<string, mixed>  $json
     * @return array{failed: bool, failure_class: string, expected: int, actual: int}|null
     */
    public function assertPerSegmentFareBasisComplete(?array $json, int $expectedSegmentCount): ?array
    {
        if ($expectedSegmentCount < 1 || ! is_array($json) || $json === []) {
            return [
                'failed' => true,
                'failure_class' => 'invalid_segment_expectation',
                'expected' => $expectedSegmentCount,
                'actual' => 0,
            ];
        }

        $perSeg = $this->collectPerSegmentFareBasis($json);
        $complete = 0;
        foreach ($perSeg as $row) {
            if (trim((string) ($row['fare_basis_code'] ?? '')) !== '') {
                $complete++;
            }
        }

        if ($complete < $expectedSegmentCount || count($perSeg) < $expectedSegmentCount) {
            return [
                'failed' => true,
                'failure_class' => 'fare_basis_incomplete',
                'expected' => $expectedSegmentCount,
                'actual' => $complete,
            ];
        }

        return null;
    }

    /**
     * Safe hash of wire + draft segment sell fields for failure freeze / retry detection.
     *
     * @param  array<string, mixed>  $payload
     * @param  array<string, mixed>  $internalDraft
     */
    public function revalidationPayloadFreezeFingerprint(array $payload, array $internalDraft): string
    {
        $wire = $this->wireableRequestPayload($payload);
        $style = (string) ($payload['_ota_revalidate_payload_style'] ?? '');
        $segments = is_array($internalDraft['segments'] ?? null) ? $internalDraft['segments'] : [];
        $sell = [];
        foreach ($segments as $seg) {
            if (! is_array($seg)) {
                continue;
            }
            $sell[] = implode('|', [
                strtoupper(trim((string) ($seg['origin'] ?? ''))),
                strtoupper(trim((string) ($seg['destination'] ?? ''))),
                trim((string) ($seg['booking_class'] ?? '')),
                trim((string) ($seg['fare_basis_code'] ?? '')),
            ]);
        }

        return substr(hash('sha256', $style.'::'.json_encode($sell).'::'.json_encode(array_keys($wire))), 0, 24);
    }

    /**
     * @param  array<string, mixed>  $internalDraft
     * @return array<string, mixed>
     */
    public function enrichInternalDraftFromGirArchive(array $internalDraft): array
    {
        $archive = is_array($internalDraft['_sabre_bfm_gir_archive'] ?? null)
            ? $internalDraft['_sabre_bfm_gir_archive']
            : (is_array(data_get($internalDraft, 'raw_payload.sabre_bfm_gir_archive'))
                ? data_get($internalDraft, 'raw_payload.sabre_bfm_gir_archive')
                : []);
        if ($archive === []) {
            return $internalDraft;
        }

        $sellRows = is_array($archive['segment_sell_rows'] ?? null) ? $archive['segment_sell_rows'] : [];
        if ($sellRows === []) {
            return $internalDraft;
        }

        $segments = is_array($internalDraft['segments'] ?? null) ? $internalDraft['segments'] : [];
        foreach ($segments as $idx => $seg) {
            if (! is_array($seg)) {
                continue;
            }
            $row = is_array($sellRows[$idx] ?? null) ? $sellRows[$idx] : null;
            if ($row === null) {
                continue;
            }
            $bc = strtoupper(trim((string) ($row['booking_class'] ?? '')));
            if ($bc !== '') {
                $segments[$idx]['booking_class'] = $bc;
                $segments[$idx]['class_of_service'] = $bc;
            }
            $fb = strtoupper(trim((string) ($row['fare_basis_code'] ?? '')));
            if ($fb !== '') {
                $segments[$idx]['fare_basis_code'] = $fb;
            }
        }
        $internalDraft['segments'] = $segments;

        return $internalDraft;
    }

    /**
     * @param  array<string, mixed>  $payload
     * @return list<array{origin: string, destination: string, departure_at: string, arrival_at: string, marketing_carrier: string, operating_carrier: string, flight_number: string, class_of_service: string}>
     */
    protected function collectWireFlightNodesForGatekeeper(array $payload): array
    {
        $iati = $this->collectIatiExtensionFlightsFromPayload($payload);
        if ($iati !== []) {
            return $this->normalizeWireFlightNodesForGatekeeper($iati, true);
        }

        $clientRq = is_array($payload['RevalidateItineraryRQ'] ?? null) ? $payload['RevalidateItineraryRQ'] : null;
        if ($clientRq !== null) {
            $rows = is_array($clientRq['FlightSegments'] ?? null) ? $clientRq['FlightSegments'] : [];

            return $this->normalizeWireFlightNodesForGatekeeper($rows, false);
        }

        $odis = is_array(data_get($payload, 'OTA_AirLowFareSearchRQ.OriginDestinationInformation'))
            ? data_get($payload, 'OTA_AirLowFareSearchRQ.OriginDestinationInformation') : [];
        $nodes = [];
        foreach ($odis as $row) {
            if (! is_array($row)) {
                continue;
            }
            $fs = is_array($row['FlightSegment'] ?? null) ? $row['FlightSegment'] : [];
            if ($fs !== []) {
                $nodes[] = $fs;
            }
        }

        return $this->normalizeWireFlightNodesForGatekeeper($nodes, false);
    }

    /**
     * @param  list<array<string, mixed>>  $nodes
     * @return list<array{origin: string, destination: string, departure_at: string, arrival_at: string, marketing_carrier: string, operating_carrier: string, flight_number: string, class_of_service: string}>
     */
    protected function normalizeWireFlightNodesForGatekeeper(array $nodes, bool $iatiShape): array
    {
        $out = [];
        foreach ($nodes as $node) {
            if (! is_array($node)) {
                continue;
            }
            $mkt = '';
            if ($iatiShape) {
                $mkt = strtoupper(trim((string) data_get($node, 'Airline.Marketing.Code', '')));
                $op = strtoupper(trim((string) data_get($node, 'Airline.Operating.Code', '')));
                $fn = trim((string) ($node['Number'] ?? ''));
            } else {
                $mkt = strtoupper(trim((string) data_get($node, 'MarketingAirline.Code', data_get($node, 'MarketingAirline.code', ''))));
                $op = strtoupper(trim((string) data_get($node, 'OperatingAirline.Code', data_get($node, 'OperatingAirline.code', ''))));
                $fn = trim((string) ($node['FlightNumber'] ?? data_get($node, 'MarketingAirline.FlightNumber', '')));
            }
            $cos = strtoupper(trim((string) ($node['ClassOfService'] ?? $node['ResBookDesigCode'] ?? '')));
            $out[] = [
                'origin' => strtoupper(trim((string) data_get($node, 'OriginLocation.LocationCode', ''))),
                'destination' => strtoupper(trim((string) data_get($node, 'DestinationLocation.LocationCode', ''))),
                'departure_at' => trim((string) ($node['DepartureDateTime'] ?? '')),
                'arrival_at' => trim((string) ($node['ArrivalDateTime'] ?? '')),
                'marketing_carrier' => $mkt,
                'operating_carrier' => $op !== '' ? $op : $mkt,
                'flight_number' => $fn,
                'class_of_service' => $cos,
            ];
        }

        return $out;
    }

    /**
     * @param  array<string, mixed>  $payload
     * @param  array<string, mixed>  $internalDraft
     * @return array{ADT: int, CHD: int, INF: int}
     */
    protected function passengerTypeCountsFromPayload(array $payload, array $internalDraft): array
    {
        $counts = ['ADT' => 0, 'CHD' => 0, 'INF' => 0];
        $ptq = data_get($payload, 'OTA_AirLowFareSearchRQ.TravelerInfoSummary.AirTravelerAvail.0.PassengerTypeQuantity');
        if (is_array($ptq)) {
            foreach ($ptq as $row) {
                if (! is_array($row)) {
                    continue;
                }
                $code = strtoupper(trim((string) ($row['Code'] ?? '')));
                $code = match ($code) {
                    'CNN', 'CH', 'CHD' => 'CHD',
                    'INF', 'IN', 'INS' => 'INF',
                    default => 'ADT',
                };
                $counts[$code] += max(0, (int) ($row['Quantity'] ?? 0));
            }
        }
        $client = is_array($payload['RevalidateItineraryRQ']['PassengerCounts'] ?? null)
            ? $payload['RevalidateItineraryRQ']['PassengerCounts']
            : [];
        if (is_array($client) && $client !== []) {
            $counts['ADT'] = max($counts['ADT'], (int) ($client['ADT'] ?? $client['adults'] ?? 0));
            $counts['CHD'] = max($counts['CHD'], (int) ($client['CHD'] ?? $client['children'] ?? 0));
            $counts['INF'] = max($counts['INF'], (int) ($client['INF'] ?? $client['infants'] ?? 0));
        }
        if (array_sum($counts) === 0) {
            foreach (is_array($internalDraft['passengers'] ?? null) ? $internalDraft['passengers'] : [] as $p) {
                if (! is_array($p)) {
                    continue;
                }
                $code = strtoupper(trim((string) ($p['type'] ?? 'ADT')));
                $code = match ($code) {
                    'CHD', 'CH', 'CNN' => 'CHD',
                    'INF', 'IN', 'INS' => 'INF',
                    default => 'ADT',
                };
                $counts[$code]++;
            }
        }

        return $counts;
    }

    /**
     * @param  array<string, mixed>  $json
     * @return list<array<string, mixed>>
     */
    protected function groupedItineraryMessageRowsFlat(array $json): array
    {
        $rows = [];
        $selectedGirItinerary = $this->selectGroupedItineraryForRevalidation($json);
        foreach (['groupedItineraryResponse.messages'] as $dot) {
            $v = data_get($json, $dot);
            if (! is_array($v)) {
                continue;
            }
            foreach ($v as $row) {
                if (is_array($row)) {
                    $rows[] = $row;
                }
            }
        }
        foreach ([
            data_get($selectedGirItinerary, 'messages'),
            data_get($selectedGirItinerary, 'pricingInformation.0.messages'),
        ] as $v) {
            if (! is_array($v)) {
                continue;
            }
            foreach ($v as $row) {
                if (is_array($row)) {
                    $rows[] = $row;
                }
            }
        }

        return $rows;
    }

    /**
     * @param  list<string>  $codes
     * @param  list<string>  $messages
     */
    protected function appendGroupedItineraryMessageRows(mixed $node, array &$codes, array &$messages): void
    {
        if ($node === null) {
            return;
        }
        if (is_string($node) && trim($node) !== '') {
            $messages[] = self::safe($node, 180);

            return;
        }
        if (! is_array($node)) {
            return;
        }
        if (array_is_list($node)) {
            foreach (array_slice($node, 0, 24) as $row) {
                $this->appendGroupedItineraryMessageRows($row, $codes, $messages);
            }

            return;
        }
        $code = trim((string) ($node['code'] ?? ''));
        if ($code !== '') {
            $codes[] = substr($code, 0, 48);
        }
        foreach (['text', 'message', 'shortText', 'description'] as $k) {
            $msg = trim((string) ($node[$k] ?? ''));
            if ($msg !== '') {
                $messages[] = self::safe($msg, 180);
                break;
            }
        }
    }
}
