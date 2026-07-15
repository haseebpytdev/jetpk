<?php

namespace App\Services\Suppliers\Sabre\Gds;

use App\Data\FlightSearchRequestData;
use App\Models\SupplierConnection;
use Illuminate\Support\Facades\Log;

class SabreFlightSearchRequestBuilder
{
    public const DEFAULT_BRANDED_FARE_REQUEST_VARIANT = 'current_tis_tpa';

    public const IATI_FULL_BRANDED_FARE_REQUEST_VARIANT = 'iati_full_tis_tpa';

    public const IATI_EXACT_GDS_V4_BRANDED_FARE_REQUEST_VARIANT = 'iati_exact_gds_v4';

    public const DEFAULT_BRANDED_FARE_INTELLISELL_REQUEST_TYPE = '100ITINS';

    public const DEFAULT_IATI_EXACT_GDS_V4_INTELLISELL_REQUEST_TYPE = '200ITINS';

    /** @var list<string> */
    public const VALID_BRANDED_FARE_REQUEST_VARIANTS = [
        'current_tis_tpa',
        'root_price_tpa',
        'root_optional_qualifiers',
        'iati_full_tis_tpa',
        'iati_exact_gds_v4',
    ];

    /** @var list<string> */
    private const VALID_BRANDED_FARE_INTELLISELL_REQUEST_TYPES = [
        '100ITINS',
        '200ITINS',
    ];

    /** @var array<string, string> */
    private const BRANDED_FARE_QUALIFIER_PATHS = [
        'current_tis_tpa' => 'OTA_AirLowFareSearchRQ.TravelerInfoSummary.PriceRequestInformation.TPA_Extensions.BrandedFareIndicators',
        'iati_full_tis_tpa' => 'OTA_AirLowFareSearchRQ.TravelerInfoSummary.PriceRequestInformation.TPA_Extensions.BrandedFareIndicators',
        'iati_exact_gds_v4' => 'OTA_AirLowFareSearchRQ.TravelerInfoSummary.PriceRequestInformation.TPA_Extensions.BrandedFareIndicators',
        'root_price_tpa' => 'OTA_AirLowFareSearchRQ.PriceRequestInformation.TPA_Extensions.BrandedFareIndicators',
        'root_optional_qualifiers' => 'OTA_AirLowFareSearchRQ.PriceRequestInformation.OptionalQualifiers.PricingQualifiers.BrandedFareIndicators',
    ];

    /**
     * True when the shop payload would include POS.PseudoCityCode for this connection.
     */
    public function includesPccInShopPayload(?SupplierConnection $connection): bool
    {
        if ($connection === null) {
            return false;
        }

        return $this->extractPcc($connection) !== null;
    }

    /**
     * Inspector payloads: `current` = enhanced legacy (DataSources, cabin prefs, currency); `minimal` = same shape as production `build()`.
     *
     * @param  'current'|'minimal'  $variant
     * @return array<string, mixed>
     */
    public function buildInspectShopPayload(FlightSearchRequestData $request, SupplierConnection $connection, string $variant): array
    {
        if ($this->shouldUseIatiExactGdsV4BrandedFareShopPayload()) {
            return $this->buildIatiExactGdsV4BrandedFareShopPayload($request, $connection);
        }

        if ($this->shouldUseIatiFullBrandedFareShopPayload()) {
            return $this->buildIatiFullBrandedFareShopPayload($request, $connection);
        }

        $v = strtolower(trim($variant));

        return match ($v) {
            'current' => $this->buildEnhancedInspectShopPayload($request, $connection),
            'minimal' => $this->buildMinimalShopPayload($request, $connection),
            default => throw new \InvalidArgumentException('Inspect shop payload variant must be "current" or "minimal".'),
        };
    }

    /**
     * BFM v4 minimal shop body used by production shop requests and inspector `--variant=minimal`.
     *
     * @return array<string, mixed>
     */
    protected function buildMinimalShopPayload(FlightSearchRequestData $request, SupplierConnection $connection): array
    {
        $otaBody = [
            'Version' => '4',
            'OriginDestinationInformation' => $this->minimalOriginDestinationInformation($request),
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

        $pcc = $this->extractPcc($connection);
        if ($pcc !== null) {
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
        }

<<<<<<< HEAD
        if ($request->direct_only) {
            $otaBody['TravelPreferences'] = [
                'DirectFlightsOnly' => true,
            ];
        }

=======
>>>>>>> jetpk/main
        return $this->applyBrandedFareSearchQualifiers([
            'OTA_AirLowFareSearchRQ' => $otaBody,
        ]);
    }

    /**
     * @return list<array<string, mixed>>
     */
    protected function minimalOriginDestinationInformation(FlightSearchRequestData $request): array
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
<<<<<<< HEAD
                    $request->returnOrigin(),
=======
                    $request->origin,
>>>>>>> jetpk/main
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
    protected function buildMinimalOriginDestinationSegment(string $rph, string $originCode, string $destCode, string $departureDateTime): array
    {
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
     * Production Sabre Offers shop payload (minimal BFM v4 shape).
     *
     * @return array<string, mixed>
     */
    public function build(FlightSearchRequestData $request, SupplierConnection $connection): array
    {
        if ($this->shouldUseIatiExactGdsV4BrandedFareShopPayload()) {
            return $this->buildIatiExactGdsV4BrandedFareShopPayload($request, $connection);
        }

        if ($this->shouldUseIatiFullBrandedFareShopPayload()) {
            return $this->buildIatiFullBrandedFareShopPayload($request, $connection);
        }

        return $this->buildMinimalShopPayload($request, $connection);
    }

    /**
     * Legacy/enhanced shop JSON for `sabre:inspect-shop-payload --variant=current` only.
     *
     * @return array<string, mixed>
     */
    protected function buildEnhancedInspectShopPayload(FlightSearchRequestData $request, SupplierConnection $connection): array
    {
        $currencyCode = $this->sabreShopCurrencyCode($request);

        $otaBody = [
            'Version' => $this->otaAirLowFareSearchVersion(),
            'OriginDestinationInformation' => $this->originDestinationInformation($request),
            'TravelPreferences' => [
                'TPA_Extensions' => [
                    'DataSources' => [
                        'ATPCO' => 'Enable',
                        'LCC' => 'Disable',
                        'NDC' => 'Disable',
                    ],
                    'NumTrips' => [
                        'Number' => $this->requestedItineraryCount(),
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

        $pcc = $this->extractPcc($connection);
        if ($pcc !== null) {
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
        }

        return $this->applyBrandedFareSearchQualifiers([
            'OTA_AirLowFareSearchRQ' => $otaBody,
        ]);
    }

    /**
     * BF3-D: IATI/Binham-like full BFM shop envelope with TIS/PRI/TPA BrandedFareIndicators (probe only).
     *
     * @return array<string, mixed>
     */
    protected function buildIatiFullBrandedFareShopPayload(FlightSearchRequestData $request, SupplierConnection $connection): array
    {
        $currencyCode = $this->sabreShopCurrencyCode($request);
        $sabreCabin = $this->mapAppCabinToSabreCode($request->cabin);
        $intelliSell = $this->brandedFaresIntelliSellRequestType();
        $requestedItins = $this->intellisellItineraryCount($intelliSell);

        $otaBody = [
            'Version' => $this->otaAirLowFareSearchVersion(),
            'OriginDestinationInformation' => $this->originDestinationInformation($request),
            'TravelPreferences' => [
                'CabinPref' => [
                    'Cabin' => $sabreCabin,
                    'PreferLevel' => 'Preferred',
                ],
<<<<<<< HEAD
                'DirectFlightsOnly' => $request->direct_only,
=======
                'DirectFlightsOnly' => false,
>>>>>>> jetpk/main
                'TPA_Extensions' => [
                    'DataSources' => [
                        'ATPCO' => 'Enable',
                        'LCC' => 'Disable',
                        'NDC' => 'Disable',
                    ],
                    'NumTrips' => [
                        'Number' => $requestedItins,
                    ],
                ],
            ],
            'TravelerInfoSummary' => [
                'SeatsRequested' => [$this->seatsRequestedCount($request)],
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
                    'RequestType' => ['Name' => $intelliSell],
                ],
            ],
        ];

        $pcc = $this->extractPcc($connection);
        if ($pcc !== null) {
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
        }

        return $this->applyBrandedFareSearchQualifiers([
            'OTA_AirLowFareSearchRQ' => $otaBody,
        ]);
    }

    /**
     * BF3-F: IATI GDS v4 exact shop envelope (probe only; mirrors captured Binham/IATI skeleton).
     *
     * @return array<string, mixed>
     */
    protected function buildIatiExactGdsV4BrandedFareShopPayload(FlightSearchRequestData $request, SupplierConnection $connection): array
    {
        $currencyCode = $this->sabreShopCurrencyCode($request);
        $sabreCabin = $this->mapAppCabinToSabreCode($request->cabin);
        $intelliSell = $this->brandedFaresExactGdsV4IntelliSellRequestType();

        $otaBody = [
<<<<<<< HEAD
            'DirectFlightsOnly' => $request->direct_only,
=======
            'DirectFlightsOnly' => false,
>>>>>>> jetpk/main
            'Version' => $this->otaAirLowFareSearchVersion(),
            'OriginDestinationInformation' => $this->iatiExactOriginDestinationInformation($request),
            'TravelPreferences' => [
                'CabinPref' => [[
                    'Cabin' => $sabreCabin,
                    'PreferLevel' => 'Preferred',
                ]],
                'TPA_Extensions' => [
                    'DataSources' => [
                        'NDC' => 'Disable',
                        'ATPCO' => 'Enable',
                        'LCC' => 'Enable',
                    ],
                    'XOFares' => [
                        'Value' => true,
                    ],
                    'JumpCabinLogic' => [
                        'Disabled' => true,
                    ],
                    'KeepSameCabin' => [
                        'Enabled' => true,
                    ],
                ],
            ],
            'TravelerInfoSummary' => [
                'SeatsRequested' => [$this->seatsRequestedCount($request)],
                'AirTravelerAvail' => [[
                    'PassengerTypeQuantity' => $this->passengerTypeQuantities($request),
                ]],
                'PriceRequestInformation' => [
                    'CurrencyCode' => $currencyCode,
                ],
            ],
            'TPA_Extensions' => [
                'IntelliSellTransaction' => [
                    'RequestType' => ['Name' => $intelliSell],
                ],
            ],
        ];

        $pcc = $this->extractPcc($connection);
        if ($pcc !== null) {
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
        }

        return $this->applyBrandedFareSearchQualifiers([
            'OTA_AirLowFareSearchRQ' => $otaBody,
        ]);
    }

    protected function shouldUseIatiExactGdsV4BrandedFareShopPayload(): bool
    {
        return $this->brandedFareSearchQualifiersEnabled()
            && $this->brandedFareRequestVariant() === self::IATI_EXACT_GDS_V4_BRANDED_FARE_REQUEST_VARIANT;
    }

    protected function shouldUseIatiFullBrandedFareShopPayload(): bool
    {
        return $this->brandedFareSearchQualifiersEnabled()
            && $this->brandedFareRequestVariant() === self::IATI_FULL_BRANDED_FARE_REQUEST_VARIANT;
    }

    /**
     * True when the active branded-fare variant uses the BF3-F IATI GDS v4 alignment profile.
     */
    public function usesIatiAlignmentProfile(): bool
    {
        return $this->brandedFareRequestVariant() === self::IATI_EXACT_GDS_V4_BRANDED_FARE_REQUEST_VARIANT;
    }

    /**
     * Whether BF2 branded-fare shop request qualifiers are enabled via config.
     */
    public function brandedFareSearchQualifiersEnabled(): bool
    {
        return (bool) config('suppliers.sabre.branded_fares_search_enabled', false);
    }

    /**
     * Active branded-fare request placement variant (invalid config falls back to current_tis_tpa).
     */
    public function brandedFareRequestVariant(): string
    {
        $configured = strtolower(trim((string) config(
            'suppliers.sabre.branded_fares_request_variant',
            self::DEFAULT_BRANDED_FARE_REQUEST_VARIANT
        )));

        if (in_array($configured, self::VALID_BRANDED_FARE_REQUEST_VARIANTS, true)) {
            return $configured;
        }

        if ($configured !== '') {
            try {
                Log::warning('sabre.branded_fares_request_variant_invalid', [
                    'configured_variant' => $configured,
                    'fallback_variant' => self::DEFAULT_BRANDED_FARE_REQUEST_VARIANT,
                ]);
            } catch (\Throwable) {
                // metadata-only; must not break search
            }
        }

        return self::DEFAULT_BRANDED_FARE_REQUEST_VARIANT;
    }

    /**
     * Dot path where BrandedFareIndicators are expected for the given (or active) variant.
     */
    public function brandedFareQualifierPath(?string $variant = null): string
    {
        $resolved = $variant !== null
            ? strtolower(trim($variant))
            : $this->brandedFareRequestVariant();

        if (! in_array($resolved, self::VALID_BRANDED_FARE_REQUEST_VARIANTS, true)) {
            $resolved = self::DEFAULT_BRANDED_FARE_REQUEST_VARIANT;
        }

        return self::BRANDED_FARE_QUALIFIER_PATHS[$resolved];
    }

    /**
     * True when the payload includes BFM v4 BrandedFareIndicators at the active variant path.
     *
     * @param  array<string, mixed>  $payload
     */
    public function payloadIncludesBrandedFareSearchQualifiers(array $payload): bool
    {
        $variant = $this->brandedFareRequestVariant();
        $indicators = data_get($payload, $this->brandedFareQualifierPath($variant));

        return $this->isValidBrandedFareIndicatorsBlock($indicators, $variant);
    }

    /**
     * Sorted indicator keys present at the active variant's BrandedFareIndicators path.
     *
     * @param  array<string, mixed>  $payload
     * @return list<string>
     */
    public function brandedFareIndicatorKeys(array $payload): array
    {
        $indicators = data_get($payload, $this->brandedFareQualifierPath());
        if (! is_array($indicators)) {
            return [];
        }

        $keys = array_keys($indicators);
        sort($keys);

        return $keys;
    }

    /**
     * @param  mixed  $indicators
     */
    protected function isValidBrandedFareIndicatorsBlock($indicators, ?string $variant = null): bool
    {
        $variant = $variant ?? $this->brandedFareRequestVariant();

        if (! is_array($indicators)
            || ($indicators['SingleBrandedFare'] ?? false) !== true
            || ($indicators['MultipleBrandedFares'] ?? false) !== true) {
            return false;
        }

        if ($variant === self::IATI_FULL_BRANDED_FARE_REQUEST_VARIANT
            || $variant === self::IATI_EXACT_GDS_V4_BRANDED_FARE_REQUEST_VARIANT) {
            return ($indicators['ReturnBrandAncillaries'] ?? false) === true;
        }

        return true;
    }

    /**
     * @return array<string, true>
     */
    protected function brandedFareIndicatorsBlock(?string $variant = null): array
    {
        $variant = $variant ?? $this->brandedFareRequestVariant();

        $block = [
            'SingleBrandedFare' => true,
            'MultipleBrandedFares' => true,
        ];

        if ($variant === self::IATI_FULL_BRANDED_FARE_REQUEST_VARIANT
            || $variant === self::IATI_EXACT_GDS_V4_BRANDED_FARE_REQUEST_VARIANT) {
            $block['ReturnBrandAncillaries'] = true;
        }

        return $block;
    }

    /**
     * BF2/BF3: optionally inject BFM v4 BrandedFareIndicators for branded-fare search probe (default off).
     *
     * @param  array<string, mixed>  $payload
     * @return array<string, mixed>
     */
    protected function applyBrandedFareSearchQualifiers(array $payload): array
    {
        if (! $this->brandedFareSearchQualifiersEnabled()) {
            return $payload;
        }

        $ota = $payload['OTA_AirLowFareSearchRQ'] ?? null;
        if (! is_array($ota)) {
            return $payload;
        }

        $variant = $this->brandedFareRequestVariant();
        $brandedIndicators = $this->brandedFareIndicatorsBlock($variant);

        $ota = match ($variant) {
            'root_price_tpa' => $this->applyBrandedFareIndicatorsAtRootPriceTpa($ota, $brandedIndicators),
            'root_optional_qualifiers' => $this->applyBrandedFareIndicatorsAtRootOptionalQualifiers($ota, $brandedIndicators),
            default => $this->applyBrandedFareIndicatorsAtTisPriceTpa($ota, $brandedIndicators),
        };

        $payload['OTA_AirLowFareSearchRQ'] = $ota;

        return $payload;
    }

    /**
     * @param  array<string, mixed>  $ota
     * @param  array{SingleBrandedFare: true, MultipleBrandedFares: true}  $brandedIndicators
     * @return array<string, mixed>
     */
    protected function applyBrandedFareIndicatorsAtTisPriceTpa(array $ota, array $brandedIndicators): array
    {
        $tis = $ota['TravelerInfoSummary'] ?? null;
        if (! is_array($tis)) {
            return $ota;
        }

        $pri = $tis['PriceRequestInformation'] ?? null;
        if (is_array($pri)) {
            $priTpa = $pri['TPA_Extensions'] ?? null;
            $priTpa = is_array($priTpa) ? $priTpa : [];
            $priTpa['BrandedFareIndicators'] = $brandedIndicators;
            $pri['TPA_Extensions'] = $priTpa;
        } else {
            $pri = [
                'TPA_Extensions' => [
                    'BrandedFareIndicators' => $brandedIndicators,
                ],
            ];
        }

        $tis['PriceRequestInformation'] = $pri;
        $ota['TravelerInfoSummary'] = $tis;

        return $ota;
    }

    /**
     * @param  array<string, mixed>  $ota
     * @param  array{SingleBrandedFare: true, MultipleBrandedFares: true}  $brandedIndicators
     * @return array<string, mixed>
     */
    protected function applyBrandedFareIndicatorsAtRootPriceTpa(array $ota, array $brandedIndicators): array
    {
        $pri = $ota['PriceRequestInformation'] ?? null;
        if (is_array($pri)) {
            $priTpa = $pri['TPA_Extensions'] ?? null;
            $priTpa = is_array($priTpa) ? $priTpa : [];
            $priTpa['BrandedFareIndicators'] = $brandedIndicators;
            $pri['TPA_Extensions'] = $priTpa;
        } else {
            $pri = [
                'TPA_Extensions' => [
                    'BrandedFareIndicators' => $brandedIndicators,
                ],
            ];
        }

        $ota['PriceRequestInformation'] = $pri;

        return $ota;
    }

    /**
     * @param  array<string, mixed>  $ota
     * @param  array{SingleBrandedFare: true, MultipleBrandedFares: true}  $brandedIndicators
     * @return array<string, mixed>
     */
    protected function applyBrandedFareIndicatorsAtRootOptionalQualifiers(array $ota, array $brandedIndicators): array
    {
        $pri = $ota['PriceRequestInformation'] ?? null;
        $pri = is_array($pri) ? $pri : [];

        $optionalQualifiers = $pri['OptionalQualifiers'] ?? null;
        $optionalQualifiers = is_array($optionalQualifiers) ? $optionalQualifiers : [];

        $pricingQualifiers = $optionalQualifiers['PricingQualifiers'] ?? null;
        $pricingQualifiers = is_array($pricingQualifiers) ? $pricingQualifiers : [];
        $pricingQualifiers['BrandedFareIndicators'] = $brandedIndicators;
        $optionalQualifiers['PricingQualifiers'] = $pricingQualifiers;
        $pri['OptionalQualifiers'] = $optionalQualifiers;
        $ota['PriceRequestInformation'] = $pri;

        return $ota;
    }

    protected function otaAirLowFareSearchVersion(): string
    {
        $path = trim((string) config('suppliers.sabre.shop_path', '/v4/offers/shop'));
        if ($path === '') {
            return '4';
        }

        $normalized = str_starts_with($path, '/') ? $path : '/'.$path;

        if (str_starts_with($normalized, '/v4/')) {
            return '4';
        }

        if (str_starts_with($normalized, '/v5/')) {
            return '5';
        }

        return '4';
    }

    /**
     * Matches IntelliSell RequestType itinerary cap (e.g. 50ITINS → 50).
     */
    protected function requestedItineraryCount(): int
    {
        return 50;
    }

    /**
     * BF3-D: IntelliSell RequestType for iati_full_tis_tpa (100ITINS | 200ITINS).
     */
    protected function brandedFaresIntelliSellRequestType(): string
    {
        return $this->resolveBrandedFaresIntelliSellRequestType(self::DEFAULT_BRANDED_FARE_INTELLISELL_REQUEST_TYPE);
    }

    /**
     * BF3-F: IntelliSell RequestType for iati_exact_gds_v4 (default 200ITINS per IATI debug; override via config).
     */
    protected function brandedFaresExactGdsV4IntelliSellRequestType(): string
    {
        return $this->resolveBrandedFaresIntelliSellRequestType(self::DEFAULT_IATI_EXACT_GDS_V4_INTELLISELL_REQUEST_TYPE);
    }

    protected function resolveBrandedFaresIntelliSellRequestType(string $fallback): string
    {
        $envValue = env('SABRE_BRANDED_FARES_INTELLISELL_REQUEST_TYPE');
        if (is_string($envValue) && trim($envValue) !== '') {
            $configured = strtoupper(trim($envValue));
        } else {
            $configured = $fallback;
        }

        if (in_array($configured, self::VALID_BRANDED_FARE_INTELLISELL_REQUEST_TYPES, true)) {
            return $configured;
        }

        if ($configured !== '') {
            try {
                Log::warning('sabre.branded_fares_intellisell_request_type_invalid', [
                    'configured_request_type' => $configured,
                    'fallback_request_type' => $fallback,
                ]);
            } catch (\Throwable) {
                // metadata-only; must not break search
            }
        }

        return $fallback;
    }

    protected function intellisellItineraryCount(string $requestTypeName): int
    {
        if (preg_match('/(\d+)/', $requestTypeName, $matches)) {
            return max(1, (int) $matches[1]);
        }

        return 100;
    }

    protected function seatsRequestedCount(FlightSearchRequestData $request): int
    {
        $total = $request->adults + $request->children + $request->infants;

        return max(1, $total);
    }

    protected function sabreShopCurrencyCode(FlightSearchRequestData $request): string
    {
        $override = config('suppliers.sabre.shop_currency_code');
        if (is_string($override) && trim($override) !== '') {
            return strtoupper(trim($override));
        }

        $c = strtoupper(trim($request->currency));

        return $c !== '' ? $c : 'USD';
    }

    /**
     * Ensures Sabre-friendly departure datetime (date portion + T00:00:00 when no time supplied).
     */
    protected function formatSabreDepartureDateTime(string $datePortion): string
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

    /**
     * Safe structural summary for diagnostics — booleans, counts, non-identifying enums only.
     * Never returns PCC, airport codes, dates/times, credentials, or raw nested payloads.
     *
     * @param  array<string, mixed>  $payload
     * @return array<string, mixed>
     */
    public function payloadStructureSummary(array $payload): array
    {
        $ota = $payload['OTA_AirLowFareSearchRQ'] ?? null;
        $otaArr = is_array($ota) ? $ota : [];

        $hasOta = $otaArr !== [];

        $hasVersion = array_key_exists('Version', $otaArr)
            && is_string($otaArr['Version'])
            && trim($otaArr['Version']) !== '';

        $pos = $otaArr['POS'] ?? null;
        $hasPos = is_array($pos);
        $sources = ($hasPos && isset($pos['Source']) && is_array($pos['Source'])) ? $pos['Source'] : [];
        $posSourceCount = count($sources);
        $firstSource = $sources[0] ?? null;

        $hasPseudoCityCode = is_array($firstSource)
            && array_key_exists('PseudoCityCode', $firstSource)
            && is_string($firstSource['PseudoCityCode'])
            && trim($firstSource['PseudoCityCode']) !== '';

        $requestorId = is_array($firstSource) ? ($firstSource['RequestorID'] ?? null) : null;
        $hasRequestorId = is_array($requestorId);
        $hasCompanyNameCode = is_array($requestorId)
            && isset($requestorId['CompanyName'])
            && is_array($requestorId['CompanyName'])
            && array_key_exists('Code', $requestorId['CompanyName'])
            && is_string($requestorId['CompanyName']['Code'])
            && trim((string) $requestorId['CompanyName']['Code']) !== '';

        $originDestinationInformation = $otaArr['OriginDestinationInformation'] ?? null;
        $originDestinations = $otaArr['OriginDestinations'] ?? null;
        if (is_array($originDestinationInformation) && $originDestinationInformation !== []) {
            $odList = $originDestinationInformation;
        } elseif (is_array($originDestinations) && $originDestinations !== []) {
            $odList = $originDestinations;
        } else {
            $odList = [];
        }

        $originDestinationCount = count($odList);
        $firstOd = $odList[0] ?? null;

        $originDestinationHasRph = is_array($firstOd)
            && array_key_exists('RPH', $firstOd)
            && trim((string) $firstOd['RPH']) !== '';

        $hasOriginLocation = is_array($firstOd)
            && isset($firstOd['OriginLocation'])
            && is_array($firstOd['OriginLocation'])
            && array_key_exists('LocationCode', $firstOd['OriginLocation']);

        $hasDestinationLocation = is_array($firstOd)
            && isset($firstOd['DestinationLocation'])
            && is_array($firstOd['DestinationLocation'])
            && array_key_exists('LocationCode', $firstOd['DestinationLocation']);

        $originLoc = is_array($firstOd) ? ($firstOd['OriginLocation'] ?? null) : null;
        $destinationLoc = is_array($firstOd) ? ($firstOd['DestinationLocation'] ?? null) : null;

        $originLocationHasCodeContext = is_array($originLoc)
            && isset($originLoc['CodeContext'])
            && is_string($originLoc['CodeContext'])
            && trim($originLoc['CodeContext']) !== '';

        $destinationLocationHasCodeContext = is_array($destinationLoc)
            && isset($destinationLoc['CodeContext'])
            && is_string($destinationLoc['CodeContext'])
            && trim($destinationLoc['CodeContext']) !== '';

        $originLocationHasLocationType = is_array($originLoc)
            && isset($originLoc['LocationType'])
            && is_string($originLoc['LocationType'])
            && trim($originLoc['LocationType']) !== '';

        $destinationLocationHasLocationType = is_array($destinationLoc)
            && isset($destinationLoc['LocationType'])
            && is_string($destinationLoc['LocationType'])
            && trim($destinationLoc['LocationType']) !== '';

        $hasDepartureWindow = is_array($firstOd)
            && isset($firstOd['DepartureWindow'])
            && is_string($firstOd['DepartureWindow'])
            && trim($firstOd['DepartureWindow']) !== '';

        $odTpa = is_array($firstOd) ? ($firstOd['TPA_Extensions'] ?? null) : null;
        $segmentType = is_array($odTpa) ? ($odTpa['SegmentType'] ?? null) : null;
        $hasSegmentType = is_array($segmentType)
            && isset($segmentType['Code'])
            && is_string($segmentType['Code'])
            && trim($segmentType['Code']) !== '';

        $odCabinPref = is_array($odTpa) ? ($odTpa['CabinPref'] ?? null) : null;
        $odiHasCabinPref = is_array($odCabinPref)
            && isset($odCabinPref['Cabin'])
            && is_string($odCabinPref['Cabin'])
            && trim($odCabinPref['Cabin']) !== '';

        $travelPrefsRoot = $otaArr['TravelPreferences'] ?? null;
        $travelPrefsRootCabinPref = is_array($travelPrefsRoot) ? ($travelPrefsRoot['CabinPref'] ?? null) : null;
        $travelPreferencesHasRootCabinPref = is_array($travelPrefsRootCabinPref) && $travelPrefsRootCabinPref !== [];

        $departureDt = is_array($firstOd) && isset($firstOd['DepartureDateTime']) && is_string($firstOd['DepartureDateTime'])
            ? $firstOd['DepartureDateTime']
            : '';

        $hasDepartureDateTime = trim($departureDt) !== '';

        $departureDatetimeHasTimeComponent = $departureDt !== ''
            && str_contains($departureDt, 'T')
            && (bool) preg_match('/T\d{2}:\d{2}/', $departureDt);

        $hasTravelPreferences = isset($otaArr['TravelPreferences']) && is_array($otaArr['TravelPreferences']);

        $travelPrefs = $otaArr['TravelPreferences'] ?? null;
        $travelPrefsTpa = is_array($travelPrefs) ? ($travelPrefs['TPA_Extensions'] ?? null) : null;
        $dataSources = is_array($travelPrefsTpa) ? ($travelPrefsTpa['DataSources'] ?? null) : null;
        $hasDataSources = is_array($dataSources) && $dataSources !== [];
        $dataSourcesAtpcoEnabled = is_array($dataSources)
            && isset($dataSources['ATPCO'])
            && strcasecmp((string) $dataSources['ATPCO'], 'Enable') === 0;
        $dataSourcesLccPresent = is_array($dataSources) && array_key_exists('LCC', $dataSources);
        $dataSourcesNdcPresent = is_array($dataSources) && array_key_exists('NDC', $dataSources);

        $tis = $otaArr['TravelerInfoSummary'] ?? null;
        $hasTravelerInfoSummary = is_array($tis);

        $airTravelerAvail = is_array($tis) && isset($tis['AirTravelerAvail']) && is_array($tis['AirTravelerAvail'])
            ? $tis['AirTravelerAvail']
            : [];
        $airTravelerCount = count($airTravelerAvail);

        $pri = is_array($tis) ? ($tis['PriceRequestInformation'] ?? null) : null;
        $hasPriceRequestInformation = is_array($pri);
        $priceRequestCurrencyPresent = is_array($pri)
            && isset($pri['CurrencyCode'])
            && is_string($pri['CurrencyCode'])
            && trim($pri['CurrencyCode']) !== '';

        $tpa = $otaArr['TPA_Extensions'] ?? null;
        $hasTpaExtensions = is_array($tpa);
        $intelli = is_array($tpa) ? ($tpa['IntelliSellTransaction'] ?? null) : null;
        $hasIntellisellTransaction = is_array($intelli);

        $requestTypeName = '';
        if (is_array($intelli)) {
            $rt = $intelli['RequestType'] ?? null;
            if (is_array($rt) && isset($rt['Name']) && is_string($rt['Name'])) {
                $requestTypeName = substr($rt['Name'], 0, 32);
            }
        }

        $requestedItins = null;
        if ($requestTypeName !== '' && preg_match('/(\d+)/', $requestTypeName, $m)) {
            $requestedItins = (int) $m[1];
        }

        $rootCurrency = $otaArr['Currency'] ?? null;
        $hasRootCurrency = is_string($rootCurrency) && trim($rootCurrency) !== '';

        $payloadProfile = ($hasTravelPreferences || $hasRootCurrency || $hasPriceRequestInformation)
            ? 'enhanced_legacy'
            : 'minimal_bfm_v4';

        $brandedFareSearchEnabled = $this->brandedFareSearchQualifiersEnabled();
        $brandedFareRequestVariant = $this->brandedFareRequestVariant();
        $brandedFareQualifierPath = $this->brandedFareQualifierPath($brandedFareRequestVariant);
        $brandedFareQualifierAdded = $this->payloadIncludesBrandedFareSearchQualifiers($payload);

        return [
            'payload_profile' => $payloadProfile,
            'branded_fare_search_enabled' => $brandedFareSearchEnabled,
            'branded_fares_request_variant' => $brandedFareRequestVariant,
            'branded_fare_qualifier_path' => $brandedFareQualifierPath,
            'branded_fare_qualifier_added' => $brandedFareQualifierAdded,
            'has_ota_air_low_fare_search_rq' => $hasOta,
            'has_version' => $hasVersion,
            'has_pos' => $hasPos,
            'pos_source_count' => $posSourceCount,
            'has_pseudo_city_code' => $hasPseudoCityCode,
            'has_requestor_id' => $hasRequestorId,
            'has_company_name_code' => $hasCompanyNameCode,
            'origin_destination_count' => $originDestinationCount,
            'origin_destination_has_rph' => $originDestinationHasRph,
            'has_origin_location' => $hasOriginLocation,
            'has_destination_location' => $hasDestinationLocation,
            'origin_location_has_code_context' => $originLocationHasCodeContext,
            'destination_location_has_code_context' => $destinationLocationHasCodeContext,
            'origin_location_has_location_type' => $originLocationHasLocationType,
            'destination_location_has_location_type' => $destinationLocationHasLocationType,
            'has_departure_datetime' => $hasDepartureDateTime,
            'departure_datetime_has_time_component' => $departureDatetimeHasTimeComponent,
            'has_departure_window' => $hasDepartureWindow,
            'has_segment_type' => $hasSegmentType,
            'odi_has_cabin_pref' => $odiHasCabinPref,
            'travel_preferences_has_root_cabin_pref' => $travelPreferencesHasRootCabinPref,
            'has_travel_preferences' => $hasTravelPreferences,
            'has_data_sources' => $hasDataSources,
            'data_sources_atpco_enabled' => $dataSourcesAtpcoEnabled,
            'data_sources_lcc_present' => $dataSourcesLccPresent,
            'data_sources_ndc_present' => $dataSourcesNdcPresent,
            'has_tpa_extensions' => $hasTpaExtensions,
            'has_traveler_info_summary' => $hasTravelerInfoSummary,
            'air_traveler_count' => $airTravelerCount,
            'has_price_request_information' => $hasPriceRequestInformation,
            'price_request_currency_present' => $priceRequestCurrencyPresent,
            'has_intellisell_transaction' => $hasIntellisellTransaction,
            'requested_itins' => $requestedItins,
        ];
    }

    protected function extractPcc(SupplierConnection $connection): ?string
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

    /**
     * Map UI cabin strings to Sabre booking class codes (BFM).
     */
    protected function mapAppCabinToSabreCode(string $cabin): string
    {
        $k = strtolower(trim($cabin));

        return match ($k) {
            'economy' => 'Y',
            'premium_economy' => 'S',
            'business' => 'C',
            'first' => 'F',
            default => 'Y',
        };
    }

    /**
     * @return list<array<string, mixed>>
     */
    protected function iatiExactOriginDestinationInformation(FlightSearchRequestData $request): array
    {
        if ($request->trip_type === 'multi_city' && $request->segments !== null && $request->segments !== []) {
            $out = [];
            $rph = 1;
            foreach ($request->segments as $seg) {
                $out[] = $this->buildIatiExactOriginDestinationSegment(
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
                $this->buildIatiExactOriginDestinationSegment(
                    '1',
                    $request->origin,
                    $request->destination,
                    $this->formatSabreDepartureDateTime($request->departure_date),
                ),
                $this->buildIatiExactOriginDestinationSegment(
                    '2',
                    $request->destination,
<<<<<<< HEAD
                    $request->returnOrigin(),
=======
                    $request->origin,
>>>>>>> jetpk/main
                    $this->formatSabreDepartureDateTime(trim($request->return_date)),
                ),
            ];
        }

        return [
            $this->buildIatiExactOriginDestinationSegment(
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
    protected function buildIatiExactOriginDestinationSegment(string $rph, string $originCode, string $destCode, string $departureDateTime): array
    {
        return [
            'RPH' => $rph,
            'DepartureDateTime' => $departureDateTime,
            'OriginLocation' => [
                'LocationCode' => strtoupper($originCode),
            ],
            'DestinationLocation' => [
                'LocationCode' => strtoupper($destCode),
            ],
            'TPA_Extensions' => [
                'SegmentType' => [
                    'Code' => 'O',
                ],
            ],
        ];
    }

    /**
     * One OTA OriginDestinationInformation row with BFM location metadata and departure window.
     *
     * @return array<string, mixed>
     */
    protected function buildOriginDestinationSegment(string $rph, string $originCode, string $destCode, string $departureDateTime, string $sabreCabinCode): array
    {
        return [
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
                'CabinPref' => [
                    'Cabin' => $sabreCabinCode,
                    'PreferLevel' => 'Preferred',
                ],
            ],
        ];
    }

    /**
     * BFM-style OriginDestinationInformation segments (RPH + locations + DepartureDateTime).
     *
     * @return list<array<string, mixed>>
     */
    protected function originDestinationInformation(FlightSearchRequestData $request): array
    {
        $sabreCabin = $this->mapAppCabinToSabreCode($request->cabin);

        if ($request->trip_type === 'multi_city' && $request->segments !== null && $request->segments !== []) {
            $out = [];
            $rph = 1;
            foreach ($request->segments as $seg) {
                $out[] = $this->buildOriginDestinationSegment(
                    (string) $rph,
                    (string) $seg['origin'],
                    (string) $seg['destination'],
                    $this->formatSabreDepartureDateTime((string) $seg['departure_date']),
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
                    $sabreCabin,
                ),
                $this->buildOriginDestinationSegment(
                    '2',
                    $request->destination,
<<<<<<< HEAD
                    $request->returnOrigin(),
=======
                    $request->origin,
>>>>>>> jetpk/main
                    $this->formatSabreDepartureDateTime(trim($request->return_date)),
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
                $sabreCabin,
            ),
        ];
    }

    /**
     * @return list<array<string, int|string>>
     */
    protected function passengerTypeQuantities(FlightSearchRequestData $request): array
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
}
