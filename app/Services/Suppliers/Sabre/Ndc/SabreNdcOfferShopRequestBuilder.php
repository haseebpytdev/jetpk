<?php

namespace App\Services\Suppliers\Sabre\Ndc;

use App\Data\FlightSearchRequestData;
use App\Models\SupplierConnection;

/**
 * Build Sabre NDC v5 {@code /v5/offers/shop} payloads from OTA search criteria (no passenger PII).
 */
final class SabreNdcOfferShopRequestBuilder
{
    public const VARIANT_GIR_DATASOURCES_ONLY = 'ndc_v5_gir_datasources_only';

    public const VARIANT_MINIMAL_SHOP = 'ndc_v5_minimal_shop';

    public const VARIANT_POS_PCC_SOURCE = 'ndc_v5_pos_pcc_source';

    public const VARIANT_NDC_ONLY = 'ndc_only';

    public const VARIANT_NDC_PLUS_ATPCO_DIAGNOSTIC = 'ndc_plus_atpco_diagnostic';

    public const VARIANT_ATPCO_ONLY_DIAGNOSTIC = 'atpco_only_diagnostic';

    public const DEFAULT_PUBLIC_VARIANT = self::VARIANT_POS_PCC_SOURCE;

    /** @var list<string> */
    public const DIAGNOSTIC_ONLY_VARIANTS = [
        self::VARIANT_GIR_DATASOURCES_ONLY,
        self::VARIANT_NDC_ONLY,
        self::VARIANT_NDC_PLUS_ATPCO_DIAGNOSTIC,
        self::VARIANT_ATPCO_ONLY_DIAGNOSTIC,
    ];

    /** @var list<string> */
    public const VALID_VARIANTS = [
        self::VARIANT_GIR_DATASOURCES_ONLY,
        self::VARIANT_MINIMAL_SHOP,
        self::VARIANT_POS_PCC_SOURCE,
        self::VARIANT_NDC_ONLY,
        self::VARIANT_NDC_PLUS_ATPCO_DIAGNOSTIC,
        self::VARIANT_ATPCO_ONLY_DIAGNOSTIC,
    ];

    /**
     * @param  array{
     *     carrier_code?: ?string,
     *     carrier_mode?: ?string,
     * }  $buildOptions
     * @return array<string, mixed>
     */
    public function build(
        FlightSearchRequestData $request,
        SupplierConnection $connection,
        ?string $variant = null,
        array $buildOptions = [],
    ): array {
        $variant = $this->resolveVariant($variant);
        $payload = match ($variant) {
            self::VARIANT_MINIMAL_SHOP => $this->buildMinimalShopPayload($request, $connection),
            self::VARIANT_POS_PCC_SOURCE => $this->buildPosPccSourcePayload($request, $connection, $this->ndcOnlyDataSources()),
            self::VARIANT_NDC_ONLY => $this->buildPosPccSourcePayload($request, $connection, $this->ndcOnlyDataSources()),
            self::VARIANT_NDC_PLUS_ATPCO_DIAGNOSTIC => $this->buildPosPccSourcePayload($request, $connection, $this->ndcPlusAtpcoDataSources()),
            self::VARIANT_ATPCO_ONLY_DIAGNOSTIC => $this->buildPosPccSourcePayload($request, $connection, $this->atpcoOnlyDataSources()),
            default => $this->buildGirDatasourcesOnlyPayload($request, $connection),
        };

        return $payload;
    }

    /**
     * @param  array{
     *     carrier_code?: ?string,
     *     carrier_mode?: ?string,
     * }  $buildOptions
     * @return array{
     *     payload: array<string, mixed>,
     *     carrier_filter_applied: bool,
     *     unsupported_carrier_filter: bool,
     *     carrier_mode: ?string,
     *     carrier_code: ?string
     * }
     */
    public function applyCarrierFilter(array $payload, array $buildOptions): array
    {
        $carrierCode = strtoupper(trim((string) ($buildOptions['carrier_code'] ?? '')));
        $carrierMode = strtolower(trim((string) ($buildOptions['carrier_mode'] ?? 'marketing')));
        if ($carrierCode === '') {
            return [
                'payload' => $payload,
                'carrier_filter_applied' => false,
                'unsupported_carrier_filter' => false,
                'carrier_mode' => null,
                'carrier_code' => null,
            ];
        }

        if ($carrierMode !== 'marketing') {
            return [
                'payload' => $payload,
                'carrier_filter_applied' => false,
                'unsupported_carrier_filter' => true,
                'carrier_mode' => $carrierMode,
                'carrier_code' => $carrierCode,
            ];
        }

        $ota = is_array($payload['OTA_AirLowFareSearchRQ'] ?? null) ? $payload['OTA_AirLowFareSearchRQ'] : [];
        $prefs = is_array($ota['TravelPreferences'] ?? null) ? $ota['TravelPreferences'] : [];
        $prefs['VendorPref'] = [[
            'Code' => $carrierCode,
            'PreferLevel' => 'Only',
        ]];
        $ota['TravelPreferences'] = $prefs;
        $payload['OTA_AirLowFareSearchRQ'] = $ota;

        return [
            'payload' => $payload,
            'carrier_filter_applied' => true,
            'unsupported_carrier_filter' => false,
            'carrier_mode' => 'marketing',
            'carrier_code' => $carrierCode,
        ];
    }

    public function isDataSourceDiagnosticVariant(string $variant): bool
    {
        return in_array($variant, [
            self::VARIANT_NDC_ONLY,
            self::VARIANT_NDC_PLUS_ATPCO_DIAGNOSTIC,
            self::VARIANT_ATPCO_ONLY_DIAGNOSTIC,
            self::VARIANT_GIR_DATASOURCES_ONLY,
        ], true);
    }

    public function selectedVariant(?string $variant = null): string
    {
        return $this->resolveVariant($variant);
    }

    public function resolvePublicSearchVariant(?string $variant = null): string
    {
        $resolved = $this->resolveVariant($variant);

        return $this->isDiagnosticOnlyVariant($resolved)
            ? self::DEFAULT_PUBLIC_VARIANT
            : $resolved;
    }

    public function isDiagnosticOnlyVariant(string $variant): bool
    {
        return in_array($variant, self::DIAGNOSTIC_ONLY_VARIANTS, true);
    }

    public function includesPccInPayload(?SupplierConnection $connection): bool
    {
        return $connection !== null && $this->extractPcc($connection) !== null;
    }

    /**
     * Safe structural summary for diagnostics — no PCC value, credentials, or full payload.
     *
     * @param  array<string, mixed>  $payload
     * @return array<string, mixed>
     */
    public function payloadStructureSummary(array $payload): array
    {
        $ota = is_array($payload['OTA_AirLowFareSearchRQ'] ?? null) ? $payload['OTA_AirLowFareSearchRQ'] : [];
        $od = is_array($ota['OriginDestinationInformation'] ?? null) ? $ota['OriginDestinationInformation'] : [];
        $ptq = data_get($ota, 'TravelerInfoSummary.AirTravelerAvail.0.PassengerTypeQuantity');
        $dataSources = data_get($ota, 'TravelPreferences.TPA_Extensions.DataSources');
        $cabinPref = data_get($ota, 'TravelPreferences.CabinPref');
        $segmentCabin = data_get($od, '0.TPA_Extensions.CabinPref.Cabin');

        return [
            'version' => (string) ($ota['Version'] ?? ''),
            'origin_destination_count' => count($od),
            'passenger_type_row_count' => is_array($ptq) ? count($ptq) : 0,
            'has_pos' => isset($ota['POS']),
            'pcc_present' => isset($ota['POS']),
            'has_currency' => trim((string) ($ota['Currency'] ?? '')) !== ''
                || trim((string) data_get($ota, 'TravelerInfoSummary.PriceRequestInformation.CurrencyCode')) !== '',
            'data_sources_ndc_enabled' => is_array($dataSources)
                && strcasecmp((string) ($dataSources['NDC'] ?? ''), 'Enable') === 0,
            'data_sources_atpco_disabled' => is_array($dataSources)
                && strcasecmp((string) ($dataSources['ATPCO'] ?? ''), 'Disable') === 0,
            'data_sources_lcc_disabled' => is_array($dataSources)
                && strcasecmp((string) ($dataSources['LCC'] ?? ''), 'Disable') === 0,
            'has_travel_preferences_cabin' => $cabinPref !== null,
            'has_segment_cabin_pref' => is_string($segmentCabin) && $segmentCabin !== '',
            'has_ndc_indicators' => is_array(data_get($ota, 'TPA_Extensions.NDCIndicators')),
            'intellisell_request_type' => (string) data_get($ota, 'TPA_Extensions.IntelliSellTransaction.RequestType.Name'),
            'num_trips' => data_get($ota, 'TravelPreferences.TPA_Extensions.NumTrips.Number'),
        ];
    }

    /**
     * @param  array<string, mixed>  $payload
     * @return array<string, mixed>
     */
    public function requestShapeSummary(
        FlightSearchRequestData $request,
        array $payload,
        SupplierConnection $connection,
        ?string $variant = null,
    ): array {
        $structure = $this->payloadStructureSummary($payload);
        $ptq = data_get($payload, 'OTA_AirLowFareSearchRQ.TravelerInfoSummary.AirTravelerAvail.0.PassengerTypeQuantity');
        $passengerCounts = [];
        if (is_array($ptq)) {
            foreach ($ptq as $row) {
                if (! is_array($row)) {
                    continue;
                }
                $code = trim((string) ($row['Code'] ?? ''));
                if ($code !== '') {
                    $passengerCounts[$code] = (int) ($row['Quantity'] ?? 0);
                }
            }
        }

        $cabinSent = trim($request->cabin) !== '';
        $normalizedCabin = $this->mapAppCabinToSabreCode($request->cabin);

        return array_merge($structure, [
            'selected_variant' => $this->resolveVariant($variant),
            'request_root_key' => 'OTA_AirLowFareSearchRQ',
            'top_level_keys' => array_slice(array_keys($payload), 0, 8),
            'origin' => $request->origin,
            'destination' => $request->destination,
            'departure_date' => $request->departure_date,
            'trip_type' => $request->trip_type,
            'itinerary_count' => (int) ($structure['origin_destination_count'] ?? 0),
            'passenger_counts' => $passengerCounts,
            'cabin_sent' => $cabinSent,
            'normalized_cabin' => $normalizedCabin,
            'pcc_present' => $this->includesPccInPayload($connection),
            'data_sources' => is_array(data_get($payload, 'OTA_AirLowFareSearchRQ.TravelPreferences.TPA_Extensions.DataSources'))
                ? data_get($payload, 'OTA_AirLowFareSearchRQ.TravelPreferences.TPA_Extensions.DataSources')
                : null,
        ]);
    }

    /**
     * Default: GIR v5 with NDC-only DataSources; no root NDCIndicators / PreferNDCSourceOnTie.
     *
     * @return array<string, mixed>
     */
    private function buildGirDatasourcesOnlyPayload(FlightSearchRequestData $request, SupplierConnection $connection): array
    {
        $currencyCode = $this->shopCurrencyCode($request);
        $sabreCabin = $this->mapAppCabinToSabreCode($request->cabin);

        $otaBody = [
            'Version' => '5',
            'OriginDestinationInformation' => $this->originDestinationInformation($request, includeSegmentCabin: false),
            'TravelPreferences' => [
                'CabinPref' => [
                    'Cabin' => $sabreCabin,
                    'PreferLevel' => 'Preferred',
                ],
                'TPA_Extensions' => [
                    'DataSources' => $this->ndcOnlyDataSources(),
                    'NumTrips' => [
                        'Number' => 50,
                    ],
                ],
            ],
            'TravelerInfoSummary' => [
                'AirTravelerAvail' => [[
                    'PassengerTypeQuantity' => $this->passengerTypeQuantities($request),
                ]],
                'PriceRequestInformation' => [
                    'CurrencyCode' => $currencyCode,
                    'TPA_Extensions' => [
                        'PublicFare' => [
                            'Ind' => false,
                        ],
                    ],
                ],
            ],
            'Currency' => $currencyCode,
            'TPA_Extensions' => [
                'IntelliSellTransaction' => [
                    'RequestType' => ['Name' => '50ITINS'],
                ],
            ],
        ];

        return [
            'OTA_AirLowFareSearchRQ' => $this->applyPos($otaBody, $connection),
        ];
    }

    /**
     * Minimal v5 shop + NDC DataSources (StackOverflow / Sabre cert style).
     *
     * @return array<string, mixed>
     */
    private function buildMinimalShopPayload(FlightSearchRequestData $request, SupplierConnection $connection): array
    {
        $otaBody = [
            'Version' => '5',
            'OriginDestinationInformation' => $this->minimalOriginDestinationInformation($request),
            'TravelPreferences' => [
                'TPA_Extensions' => [
                    'DataSources' => $this->ndcOnlyDataSources(),
                    'NumTrips' => [
                        'Number' => 10,
                    ],
                ],
            ],
            'TravelerInfoSummary' => [
                'AirTravelerAvail' => [[
                    'PassengerTypeQuantity' => $this->passengerTypeQuantities($request),
                ]],
            ],
            'TPA_Extensions' => [
                'IntelliSellTransaction' => [
                    'RequestType' => ['Name' => '50ITINS'],
                ],
            ],
        ];

        return [
            'OTA_AirLowFareSearchRQ' => $this->applyPos($otaBody, $connection),
        ];
    }

    /**
     * POS-forward variant with segment cabin prefs (legacy probe shape, no root NDCIndicators).
     *
     * @return array<string, mixed>
     */
    private function buildPosPccSourcePayload(
        FlightSearchRequestData $request,
        SupplierConnection $connection,
        array $dataSources = [],
    ): array {
        $dataSources = $dataSources !== [] ? $dataSources : $this->ndcOnlyDataSources();
        $currencyCode = $this->shopCurrencyCode($request);
        $sabreCabin = $this->mapAppCabinToSabreCode($request->cabin);

        $otaBody = [
            'Version' => '5',
            'OriginDestinationInformation' => $this->originDestinationInformation($request, includeSegmentCabin: true, sabreCabin: $sabreCabin),
            'TravelPreferences' => [
                'TPA_Extensions' => [
                    'DataSources' => $dataSources,
                    'NumTrips' => [
                        'Number' => 50,
                    ],
                ],
            ],
            'TravelerInfoSummary' => [
                'AirTravelerAvail' => [[
                    'PassengerTypeQuantity' => $this->passengerTypeQuantities($request),
                ]],
                'PriceRequestInformation' => [
                    'CurrencyCode' => $currencyCode,
                ],
            ],
            'TPA_Extensions' => [
                'IntelliSellTransaction' => [
                    'RequestType' => ['Name' => '50ITINS'],
                ],
            ],
        ];

        return [
            'OTA_AirLowFareSearchRQ' => $this->applyPos($otaBody, $connection),
        ];
    }

    /**
     * @return array{NDC: string, ATPCO: string, LCC: string}
     */
    private function ndcOnlyDataSources(): array
    {
        return [
            'NDC' => 'Enable',
            'ATPCO' => 'Disable',
            'LCC' => 'Disable',
        ];
    }

    /**
     * @return array{NDC: string, ATPCO: string, LCC: string}
     */
    private function ndcPlusAtpcoDataSources(): array
    {
        return [
            'NDC' => 'Enable',
            'ATPCO' => 'Enable',
            'LCC' => 'Disable',
        ];
    }

    /**
     * @return array{NDC: string, ATPCO: string, LCC: string}
     */
    private function atpcoOnlyDataSources(): array
    {
        return [
            'NDC' => 'Disable',
            'ATPCO' => 'Enable',
            'LCC' => 'Disable',
        ];
    }

    /**
     * @param  array{
     *     carrier_code?: ?string,
     *     carrier_mode?: ?string,
     * }  $buildOptions
     * @return array{
     *     payload: array<string, mixed>,
     *     carrier_filter_applied: bool,
     *     unsupported_carrier_filter: bool,
     *     carrier_mode: ?string,
     *     carrier_code: ?string
     * }
     */
    public function finalizePayload(array $payload, array $buildOptions = []): array
    {
        return $this->applyCarrierFilter($payload, $buildOptions);
    }

    /**
     * @param  array<string, mixed>  $otaBody
     * @return array<string, mixed>
     */
    private function applyPos(array $otaBody, SupplierConnection $connection): array
    {
        $pcc = $this->extractPcc($connection);
        if ($pcc === null) {
            return $otaBody;
        }

        $otaBody['POS'] = [
            'Source' => [[
                'PseudoCityCode' => strtoupper($pcc),
                'RequestorID' => [
                    'ID' => '1',
                    'Type' => '1',
                    'CompanyName' => [
                        'Code' => 'TN',
                    ],
                ],
            ]],
        ];

        return $otaBody;
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function originDestinationInformation(
        FlightSearchRequestData $request,
        bool $includeSegmentCabin,
        ?string $sabreCabin = null,
    ): array {
        $sabreCabin ??= $this->mapAppCabinToSabreCode($request->cabin);

        if ($request->trip_type === 'multi_city' && $request->segments !== null && $request->segments !== []) {
            $out = [];
            $rph = 1;
            foreach ($request->segments as $seg) {
                $out[] = $this->buildOriginDestinationSegment(
                    (string) $rph,
                    (string) $seg['origin'],
                    (string) $seg['destination'],
                    $this->formatSabreDepartureDateTime((string) $seg['departure_date']),
                    $includeSegmentCabin,
                    $sabreCabin,
                );
                $rph++;
            }

            return $out;
        }

        if ($request->trip_type === 'round_trip' && $request->return_date !== null && trim($request->return_date) !== '') {
            return [
                $this->buildOriginDestinationSegment(
                    '1',
                    $request->origin,
                    $request->destination,
                    $this->formatSabreDepartureDateTime($request->departure_date),
                    $includeSegmentCabin,
                    $sabreCabin,
                ),
                $this->buildOriginDestinationSegment(
                    '2',
                    $request->destination,
                    $request->origin,
                    $this->formatSabreDepartureDateTime(trim($request->return_date)),
                    $includeSegmentCabin,
                    $sabreCabin,
                ),
            ];
        }

        return [
            $this->buildOriginDestinationSegment(
                '1',
                $request->origin,
                $request->destination,
                $this->formatSabreDepartureDateTime($request->departure_date),
                $includeSegmentCabin,
                $sabreCabin,
            ),
        ];
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function minimalOriginDestinationInformation(FlightSearchRequestData $request): array
    {
        if ($request->trip_type === 'multi_city' && $request->segments !== null && $request->segments !== []) {
            $out = [];
            $rph = 1;
            foreach ($request->segments as $seg) {
                $out[] = $this->buildMinimalOriginDestinationSegment(
                    (string) $rph,
                    (string) $seg['origin'],
                    (string) $seg['destination'],
                    $this->formatSabreDepartureDateTime((string) $seg['departure_date']),
                );
                $rph++;
            }

            return $out;
        }

        if ($request->trip_type === 'round_trip' && $request->return_date !== null && trim($request->return_date) !== '') {
            return [
                $this->buildMinimalOriginDestinationSegment(
                    '1',
                    $request->origin,
                    $request->destination,
                    $this->formatSabreDepartureDateTime($request->departure_date),
                ),
                $this->buildMinimalOriginDestinationSegment(
                    '2',
                    $request->destination,
                    $request->origin,
                    $this->formatSabreDepartureDateTime(trim($request->return_date)),
                ),
            ];
        }

        return [
            $this->buildMinimalOriginDestinationSegment(
                '1',
                $request->origin,
                $request->destination,
                $this->formatSabreDepartureDateTime($request->departure_date),
            ),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function buildOriginDestinationSegment(
        string $rph,
        string $originCode,
        string $destCode,
        string $departureDateTime,
        bool $includeSegmentCabin,
        string $sabreCabin,
    ): array {
        $segment = [
            'RPH' => $rph,
            'OriginLocation' => [
                'LocationCode' => strtoupper($originCode),
                'CodeContext' => 'IATA',
                'LocationType' => 'A',
            ],
            'DestinationLocation' => [
                'LocationCode' => strtoupper($destCode),
                'CodeContext' => 'IATA',
                'LocationType' => 'A',
            ],
            'DepartureDateTime' => $departureDateTime,
            'DepartureWindow' => '00002359',
            'TPA_Extensions' => [
                'SegmentType' => [
                    'Code' => 'O',
                ],
            ],
        ];

        if ($includeSegmentCabin) {
            $segment['TPA_Extensions']['CabinPref'] = [
                'Cabin' => $sabreCabin,
                'PreferLevel' => 'Preferred',
            ];
        }

        return $segment;
    }

    /**
     * @return array<string, mixed>
     */
    private function buildMinimalOriginDestinationSegment(
        string $rph,
        string $originCode,
        string $destCode,
        string $departureDateTime,
    ): array {
        return [
            'RPH' => $rph,
            'OriginLocation' => [
                'LocationCode' => strtoupper($originCode),
            ],
            'DestinationLocation' => [
                'LocationCode' => strtoupper($destCode),
            ],
            'DepartureDateTime' => $departureDateTime,
        ];
    }

    /**
     * @return list<array<string, int|string>>
     */
    private function passengerTypeQuantities(FlightSearchRequestData $request): array
    {
        $quantities = [];

        if ($request->adults > 0) {
            $quantities[] = ['Code' => 'ADT', 'Quantity' => $request->adults];
        }
        if ($request->children > 0) {
            $quantities[] = ['Code' => 'CNN', 'Quantity' => $request->children];
        }
        if ($request->infants > 0) {
            $quantities[] = ['Code' => 'INF', 'Quantity' => $request->infants];
        }
        if ($quantities === []) {
            $quantities[] = ['Code' => 'ADT', 'Quantity' => 1];
        }

        return $quantities;
    }

    private function resolveVariant(?string $variant): string
    {
        $configured = is_string($variant) && trim($variant) !== ''
            ? trim($variant)
            : trim((string) config('suppliers.sabre.ndc.search_request_variant', self::DEFAULT_PUBLIC_VARIANT));

        return in_array($configured, self::VALID_VARIANTS, true)
            ? $configured
            : self::DEFAULT_PUBLIC_VARIANT;
    }

    private function shopCurrencyCode(FlightSearchRequestData $request): string
    {
        $override = config('suppliers.sabre.shop_currency_code');
        if (is_string($override) && trim($override) !== '') {
            return strtoupper(trim($override));
        }

        $c = strtoupper(trim($request->currency));

        return $c !== '' ? $c : 'USD';
    }

    private function formatSabreDepartureDateTime(string $datePortion): string
    {
        $d = trim($datePortion);
        if ($d === '') {
            return '';
        }

        if (str_contains($d, 'T')) {
            return $d;
        }

        return $d.'T00:00:00';
    }

    private function mapAppCabinToSabreCode(string $cabin): string
    {
        return match (strtolower(trim($cabin))) {
            'economy' => 'Y',
            'premium_economy' => 'S',
            'business' => 'C',
            'first' => 'F',
            default => 'Y',
        };
    }

    private function extractPcc(SupplierConnection $connection): ?string
    {
        $cred = is_array($connection->credentials) ? $connection->credentials : [];
        $settings = is_array($connection->settings) ? $connection->settings : [];

        foreach (['pcc', 'PCC', 'pseudo_city_code', 'pseudoCityCode'] as $key) {
            $v = trim((string) ($cred[$key] ?? ''));
            if ($v !== '') {
                return $v;
            }
            $v = trim((string) data_get($settings, $key));
            if ($v !== '') {
                return $v;
            }
        }

        return null;
    }
}
