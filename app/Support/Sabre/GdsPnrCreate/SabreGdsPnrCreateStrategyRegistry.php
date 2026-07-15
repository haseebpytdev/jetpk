<?php

namespace App\Support\Sabre\GdsPnrCreate;

use App\Services\Suppliers\Sabre\Booking\SabreBookingPayloadBuilder;
use App\Support\Bookings\SabreCertifiedRouteSelector;

/**
 * Registry of supported Sabre GDS Passenger Records / CPNR PNR create strategies.
 */
final class SabreGdsPnrCreateStrategyRegistry
{
    public const STRATEGY_IATI_LIKE_CPNR_V2_4_GDS = SabreBookingPayloadBuilder::IATI_LIKE_CPNR_V2_4_GDS;

    public const STRATEGY_TRADITIONAL_PNR_CREATE_V1 = SabreBookingPayloadBuilder::TRADITIONAL_PNR_CREATE_PASSENGER_NAME_RECORD_V1;

    public const STRATEGY_PASSENGER_RECORDS_V2_5_GDS = 'passenger_records_v2_5_gds';

    public const STRATEGY_MINIMAL_AIRBOOK_AIRPRICE_ENDTRANSACTION_GDS = 'minimal_airbook_airprice_endtransaction_gds';

    /** @var list<string> */
    public const SUPPORTED_STRATEGY_CODES = [
        self::STRATEGY_IATI_LIKE_CPNR_V2_4_GDS,
        self::STRATEGY_TRADITIONAL_PNR_CREATE_V1,
        self::STRATEGY_PASSENGER_RECORDS_V2_5_GDS,
        self::STRATEGY_MINIMAL_AIRBOOK_AIRPRICE_ENDTRANSACTION_GDS,
    ];

    /** @var list<string> */
    private const ALL_TRIP_TYPES = [
        'one_way_direct',
        'one_way_connecting',
        'one_way_single_connection_same_carrier',
        'one_way_single_connection_mixed_carrier',
        'one_way_multistop_same_carrier',
        'one_way_three_stop_same_carrier',
        'one_way_four_stop_same_carrier',
        'one_way_multistop_mixed_carrier',
        'return_same_carrier',
        'return_mixed_carrier',
        'round_trip',
        'multi_city',
    ];

    /** @var list<string> */
    private const DIRECT_AND_CONNECTING_PATTERNS = [
        SabreCertifiedRouteSelector::CATEGORY_ONE_WAY_DIRECT_SAME_CARRIER,
        SabreCertifiedRouteSelector::CATEGORY_ONE_WAY_CONNECTING_SAME_CARRIER_GDS,
        SabreCertifiedRouteSelector::CATEGORY_ONE_WAY_MULTISTOP_SAME_CARRIER_GDS,
        SabreCertifiedRouteSelector::CATEGORY_RETURN,
        SabreCertifiedRouteSelector::CATEGORY_MULTI_CITY,
    ];

    /**
     * @return list<array<string, mixed>>
     */
    public function all(): array
    {
        return array_values(array_map(
            fn (string $code): array => $this->get($code),
            self::SUPPORTED_STRATEGY_CODES,
        ));
    }

    /**
     * @return list<string>
     */
    public function supportedCodes(): array
    {
        return self::SUPPORTED_STRATEGY_CODES;
    }

    /**
     * @return list<string>
     */
    public function automaticStrategyCodes(): array
    {
        $codes = [];
        foreach (self::SUPPORTED_STRATEGY_CODES as $code) {
            if (($this->get($code)['automatic_allowed'] ?? false) === true) {
                $codes[] = $code;
            }
        }

        return $codes;
    }

    public function isSupported(string $strategyCode): bool
    {
        return in_array(trim($strategyCode), self::SUPPORTED_STRATEGY_CODES, true);
    }

    /**
     * @return array<string, mixed>
     */
    public function get(string $strategyCode): array
    {
        $code = trim($strategyCode);

        return match ($code) {
            self::STRATEGY_IATI_LIKE_CPNR_V2_4_GDS => $this->iatiLikeDefinition(),
            self::STRATEGY_TRADITIONAL_PNR_CREATE_V1 => $this->traditionalDefinition(),
            self::STRATEGY_PASSENGER_RECORDS_V2_5_GDS => $this->passengerRecordsV25Definition(),
            self::STRATEGY_MINIMAL_AIRBOOK_AIRPRICE_ENDTRANSACTION_GDS => $this->minimalDefinition(),
            default => throw new \InvalidArgumentException('Unsupported Sabre GDS PNR create strategy: '.$code),
        };
    }

    public function wireStyleForStrategy(string $strategyCode): string
    {
        return match (trim($strategyCode)) {
            self::STRATEGY_IATI_LIKE_CPNR_V2_4_GDS => SabreBookingPayloadBuilder::IATI_LIKE_CPNR_V2_4_GDS,
            self::STRATEGY_TRADITIONAL_PNR_CREATE_V1,
            self::STRATEGY_PASSENGER_RECORDS_V2_5_GDS,
            self::STRATEGY_MINIMAL_AIRBOOK_AIRPRICE_ENDTRANSACTION_GDS => SabreBookingPayloadBuilder::TRADITIONAL_PNR_CREATE_PASSENGER_NAME_RECORD_V1,
            default => SabreBookingPayloadBuilder::TRADITIONAL_PNR_CREATE_PASSENGER_NAME_RECORD_V1,
        };
    }

    public function endpointPathForStrategy(string $strategyCode): string
    {
        $builder = app(SabreBookingPayloadBuilder::class);

        return match (trim($strategyCode)) {
            self::STRATEGY_IATI_LIKE_CPNR_V2_4_GDS => $builder->resolvePassengerRecordsCreateEndpointPath(
                SabreBookingPayloadBuilder::IATI_LIKE_CPNR_V2_4_GDS,
            ),
            self::STRATEGY_PASSENGER_RECORDS_V2_5_GDS => '/v2.5.0/passenger/records?mode=create',
            self::STRATEGY_TRADITIONAL_PNR_CREATE_V1,
            self::STRATEGY_MINIMAL_AIRBOOK_AIRPRICE_ENDTRANSACTION_GDS => $builder->resolvePassengerRecordsCreateEndpointPath(
                SabreBookingPayloadBuilder::TRADITIONAL_PNR_CREATE_PASSENGER_NAME_RECORD_V1,
            ),
            default => $builder->resolvePassengerRecordsCreateEndpointPath(
                SabreBookingPayloadBuilder::TRADITIONAL_PNR_CREATE_PASSENGER_NAME_RECORD_V1,
            ),
        };
    }

    public function endpointVersionForStrategy(string $strategyCode): string
    {
        return match (trim($strategyCode)) {
            self::STRATEGY_IATI_LIKE_CPNR_V2_4_GDS => '2.4.0',
            default => '2.5.0',
        };
    }

    public function patternSupported(string $strategyCode, string $category, string $tripType = ''): bool
    {
        $definition = $this->get(trim($strategyCode));
        $patterns = is_array($definition['supported_segment_patterns'] ?? null)
            ? $definition['supported_segment_patterns']
            : [];
        $tripTypes = is_array($definition['supported_trip_types'] ?? null)
            ? $definition['supported_trip_types']
            : [];

        $categoryMatch = $patterns === [] || in_array($category, $patterns, true);
        $tripMatch = $tripType === '' || $tripTypes === [] || in_array($tripType, $tripTypes, true);

        return $categoryMatch && $tripMatch;
    }

    /**
     * @return array<string, mixed>
     */
    protected function iatiLikeDefinition(): array
    {
        return $this->baseDefinition(
            self::STRATEGY_IATI_LIKE_CPNR_V2_4_GDS,
            self::STRATEGY_IATI_LIKE_CPNR_V2_4_GDS,
            '2.4.0',
            [
                SabreCertifiedRouteSelector::CATEGORY_ONE_WAY_DIRECT_SAME_CARRIER,
                SabreCertifiedRouteSelector::CATEGORY_ONE_WAY_CONNECTING_SAME_CARRIER_GDS,
                SabreCertifiedRouteSelector::CATEGORY_ONE_WAY_MULTISTOP_SAME_CARRIER_GDS,
                SabreCertifiedRouteSelector::CATEGORY_RETURN,
                SabreCertifiedRouteSelector::CATEGORY_MIXED_INTERLINE,
            ],
            requiresRevalidationLinkage: false,
            supportsIatiRefreshWaiver: true,
            duplicateRiskLevel: 'medium',
            automaticAllowed: true,
        );
    }

    /**
     * @return array<string, mixed>
     */
    protected function traditionalDefinition(): array
    {
        return $this->baseDefinition(
            self::STRATEGY_TRADITIONAL_PNR_CREATE_V1,
            self::STRATEGY_TRADITIONAL_PNR_CREATE_V1,
            '2.5.0',
            self::DIRECT_AND_CONNECTING_PATTERNS,
            requiresRevalidationLinkage: false,
            supportsIatiRefreshWaiver: false,
            duplicateRiskLevel: 'medium',
            automaticAllowed: true,
        );
    }

    /**
     * @return array<string, mixed>
     */
    protected function passengerRecordsV25Definition(): array
    {
        return $this->baseDefinition(
            self::STRATEGY_PASSENGER_RECORDS_V2_5_GDS,
            self::STRATEGY_PASSENGER_RECORDS_V2_5_GDS,
            '2.5.0',
            self::DIRECT_AND_CONNECTING_PATTERNS,
            requiresRevalidationLinkage: false,
            supportsIatiRefreshWaiver: false,
            duplicateRiskLevel: 'medium',
            automaticAllowed: false,
        );
    }

    /**
     * @return array<string, mixed>
     */
    protected function minimalDefinition(): array
    {
        return $this->baseDefinition(
            self::STRATEGY_MINIMAL_AIRBOOK_AIRPRICE_ENDTRANSACTION_GDS,
            self::STRATEGY_MINIMAL_AIRBOOK_AIRPRICE_ENDTRANSACTION_GDS,
            '2.5.0',
            [
                SabreCertifiedRouteSelector::CATEGORY_ONE_WAY_DIRECT_SAME_CARRIER,
                SabreCertifiedRouteSelector::CATEGORY_ONE_WAY_CONNECTING_SAME_CARRIER_GDS,
            ],
            requiresRevalidationLinkage: false,
            supportsIatiRefreshWaiver: false,
            duplicateRiskLevel: 'low',
            automaticAllowed: false,
            requiredContextFields: [
                'supplier_connection_id',
                'validating_carrier',
                'segments',
            ],
            requiredSelectedFareFields: [
                'fare_basis_codes_by_segment',
                'booking_classes_by_segment',
            ],
        );
    }

    /**
     * @param  list<string>  $supportedSegmentPatterns
     * @param  list<string>|null  $requiredContextFields
     * @param  list<string>|null  $requiredSelectedFareFields
     * @return array<string, mixed>
     */
    protected function baseDefinition(
        string $strategyCode,
        string $payloadSchema,
        string $endpointVersion,
        array $supportedSegmentPatterns,
        bool $requiresRevalidationLinkage,
        bool $supportsIatiRefreshWaiver,
        string $duplicateRiskLevel,
        bool $automaticAllowed,
        ?array $requiredContextFields = null,
        ?array $requiredSelectedFareFields = null,
    ): array {
        return [
            'strategy_code' => $strategyCode,
            'endpoint_path' => $this->endpointPathForStrategy($strategyCode),
            'endpoint_version' => $endpointVersion,
            'payload_schema' => $payloadSchema,
            'supported_trip_types' => self::ALL_TRIP_TYPES,
            'supported_segment_patterns' => $supportedSegmentPatterns,
            'required_context_fields' => $requiredContextFields ?? [
                'supplier_connection_id',
                'validating_carrier',
                'segments',
                'sabre_booking_context',
            ],
            'required_selected_fare_fields' => $requiredSelectedFareFields ?? [
                'brand_code',
                'fare_basis_codes_by_segment',
                'booking_classes_by_segment',
            ],
            'requires_revalidation_linkage' => $requiresRevalidationLinkage,
            'supports_iati_refresh_waiver' => $supportsIatiRefreshWaiver,
            'ticketing_disabled_supported' => true,
            'duplicate_risk_level' => $duplicateRiskLevel,
            'automatic_allowed' => $automaticAllowed,
            'admin_confirmed_fallback_allowed' => true,
        ];
    }
}
