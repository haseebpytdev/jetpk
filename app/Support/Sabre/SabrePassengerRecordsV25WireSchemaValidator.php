<?php

namespace App\Support\Sabre;

use App\Services\Suppliers\Sabre\Booking\SabreBookingPayloadBuilder;

/**
 * V25-CPNR: Local pre-HTTP schema validation for Passenger Records v2.5 GDS AirPrice optional qualifiers.
 * Production wire keeps minimal schema-safe PricingQualifiers (CommandPricing array, PassengerType).
 */
final class SabrePassengerRecordsV25WireSchemaValidator
{
    public const COMMAND_PRICING_POINTER = '/CreatePassengerNameRecordRQ/AirPrice/0/PriceRequestInformation/OptionalQualifiers/PricingQualifiers/CommandPricing';

    public const BRAND_POINTER = '/CreatePassengerNameRecordRQ/AirPrice/0/PriceRequestInformation/OptionalQualifiers/PricingQualifiers/Brand/0';

    public const ITINERARY_OPTIONS_POINTER = '/CreatePassengerNameRecordRQ/AirPrice/0/PriceRequestInformation/OptionalQualifiers/PricingQualifiers/ItineraryOptions';

    public const SEGMENT_SELECT_POINTER = '/CreatePassengerNameRecordRQ/AirPrice/0/PriceRequestInformation/OptionalQualifiers/PricingQualifiers/ItineraryOptions/SegmentSelect';

    public const PRICING_QUALIFIERS_POINTER = '/CreatePassengerNameRecordRQ/AirPrice/0/PriceRequestInformation/OptionalQualifiers/PricingQualifiers';

    public function __construct(
        protected SabreBookingPayloadBuilder $payloadBuilder,
    ) {}

    /**
     * @param  array<string, mixed>  $cpnr  CreatePassengerNameRecordRQ block (not envelope)
     * @return array<string, mixed>
     */
    public function validatePassengerRecordsV25GdsAirPrice(array $cpnr): array
    {
        $airPrice = is_array($cpnr['AirPrice'] ?? null) ? $cpnr['AirPrice'] : [];
        if ($airPrice === [] || ! array_is_list($airPrice)) {
            return $this->failSummary(
                self::PRICING_QUALIFIERS_POINTER,
                'AirPrice array missing or invalid for Passenger Records v2.5 schema validation.',
            );
        }

        $first = is_array($airPrice[0] ?? null) ? $airPrice[0] : [];
        $pri = is_array($first['PriceRequestInformation'] ?? null) ? $first['PriceRequestInformation'] : [];
        $oq = is_array($pri['OptionalQualifiers'] ?? null) ? $pri['OptionalQualifiers'] : [];
        $pq = is_array($oq['PricingQualifiers'] ?? null) ? $oq['PricingQualifiers'] : [];

        if (array_key_exists('ValidatingCarrier', $pq)) {
            return $this->failSummary(
                self::PRICING_QUALIFIERS_POINTER,
                'ValidatingCarrier must not appear under PricingQualifiers on Passenger Records v2.5 GDS wire.',
            );
        }

        if (array_key_exists('CommandPricing', $pq)) {
            $cp = $pq['CommandPricing'];
            if (! is_array($cp) || ! array_is_list($cp)) {
                return $this->failSummary(
                    self::COMMAND_PRICING_POINTER,
                    'CommandPricing must be a JSON array on Passenger Records v2.5 GDS wire.',
                );
            }
            foreach ($cp as $index => $row) {
                if (! is_array($row)) {
                    return $this->failSummary(
                        self::COMMAND_PRICING_POINTER.'/'.$index,
                        'CommandPricing row must be an object on Passenger Records v2.5 GDS wire.',
                    );
                }
            }
        }

        if (array_key_exists('Brand', $pq)) {
            return $this->failSummary(
                self::BRAND_POINTER,
                'Brand qualifier must be omitted on Passenger Records v2.5 GDS wire.',
            );
        }

        if (array_key_exists('ItineraryOptions', $pq)) {
            return $this->failSummary(
                self::ITINERARY_OPTIONS_POINTER,
                'ItineraryOptions must be omitted on Passenger Records v2.5 GDS wire.',
            );
        }

        foreach (array_keys($pq) as $pqKey) {
            if (! is_string($pqKey)) {
                continue;
            }
            if (! in_array($pqKey, SabreBookingPayloadBuilder::V25_GDS_ALLOWED_PRICING_QUALIFIER_KEYS, true)) {
                return $this->failSummary(
                    self::PRICING_QUALIFIERS_POINTER.'/'.$pqKey,
                    'PricingQualifiers key must be omitted on Passenger Records v2.5 GDS wire: '.$pqKey,
                );
            }
        }

        $vcCode = $this->payloadBuilder->traditionalPnrExtractValidatingCarrierCodeFromAirPriceOptionalQualifiers($oq);
        if ($vcCode === null && data_get($oq, 'FlightQualifiers.VendorPrefs.Airline.Code') !== null) {
            return $this->failSummary(
                '/CreatePassengerNameRecordRQ/AirPrice/0/PriceRequestInformation/OptionalQualifiers/FlightQualifiers/VendorPrefs/Airline/Code',
                'FlightQualifiers VendorPrefs Airline Code invalid or missing.',
            );
        }

        return $this->passSummary();
    }

    /**
     * @param  array<string, mixed>  $wire
     * @return array<string, mixed>
     */
    public function validateCpnrEnvelope(array $wire): array
    {
        $cpnr = is_array($wire['CreatePassengerNameRecordRQ'] ?? null)
            ? $wire['CreatePassengerNameRecordRQ']
            : $wire;

        return $this->validatePassengerRecordsV25GdsAirPrice($cpnr);
    }

    /**
     * @return array<string, mixed>
     */
    protected function failSummary(string $pointer, string $messageSummary): array
    {
        return [
            'cpnr_schema_validation_status' => 'fail',
            'cpnr_schema_validation_failed' => true,
            'cpnr_schema_validation_pointer' => substr($pointer, 0, 240),
            'cpnr_schema_validation_message_summary' => substr($messageSummary, 0, 240),
            'cpnr_schema_validation_stage' => 'pre_http',
            'safe_reason_code' => SabreBookingPayloadBuilder::V25_AIRPRICE_OPTIONAL_QUALIFIER_SCHEMA_ERROR,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    protected function passSummary(): array
    {
        return [
            'cpnr_schema_validation_status' => 'pass',
            'cpnr_schema_validation_failed' => false,
            'cpnr_schema_validation_pointer' => null,
            'cpnr_schema_validation_message_summary' => null,
            'cpnr_schema_validation_stage' => 'pre_http',
        ];
    }
}
