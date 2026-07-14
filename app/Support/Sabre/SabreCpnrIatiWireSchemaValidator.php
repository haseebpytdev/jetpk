<?php

namespace App\Support\Sabre;

use App\Services\Suppliers\Sabre\Booking\SabreBookingPayloadBuilder;

/**
 * F9K: Local pre-HTTP schema validation for IATI-like CPNR v2.4 AirPrice optional qualifiers.
 * Mirrors Sabre Passenger Records JSON-schema rules (no raw payload output).
 */
final class SabreCpnrIatiWireSchemaValidator
{
    /** @var list<string> */
    public const PRICING_QUALIFIERS_ALLOWED_KEYS = [
        'PassengerType',
        'Brand',
        'ItineraryOptions',
        'CommandPricing',
        'CurrencyCode',
        'SpecificPenalty',
        'AlternateCurrency',
    ];

    public const PRICING_QUALIFIERS_POINTER = '/CreatePassengerNameRecordRQ/AirPrice/0/PriceRequestInformation/OptionalQualifiers/PricingQualifiers';

    public const BRAND_RPH_POINTER = SabreBookingPayloadBuilder::AIRPRICE_BRAND_RPH_REJECTED_POINTER;

    public const FLIGHT_QUALIFIERS_AIRLINE_POINTER = '/CreatePassengerNameRecordRQ/AirPrice/0/PriceRequestInformation/OptionalQualifiers/FlightQualifiers/VendorPrefs/Airline';

    public function __construct(
        protected SabreBookingPayloadBuilder $payloadBuilder,
    ) {}

    /**
     * @param  array<string, mixed>  $cpnr  CreatePassengerNameRecordRQ block (not envelope)
     * @return array<string, mixed>
     */
    public function validateIatiLikeCpnrV24AirPrice(array $cpnr): array
    {
        $airPrice = is_array($cpnr['AirPrice'] ?? null) ? $cpnr['AirPrice'] : [];
        if ($airPrice === [] || ! array_is_list($airPrice)) {
            return $this->failSummary(
                self::PRICING_QUALIFIERS_POINTER,
                'AirPrice array missing or invalid for IATI CPNR schema validation.',
                self::PRICING_QUALIFIERS_ALLOWED_KEYS,
                [],
            );
        }

        $first = is_array($airPrice[0] ?? null) ? $airPrice[0] : [];
        $pri = is_array($first['PriceRequestInformation'] ?? null) ? $first['PriceRequestInformation'] : [];
        $oq = is_array($pri['OptionalQualifiers'] ?? null) ? $pri['OptionalQualifiers'] : [];
        $pq = is_array($oq['PricingQualifiers'] ?? null) ? $oq['PricingQualifiers'] : [];

        $rejectedKeys = [];
        foreach (array_keys($pq) as $key) {
            if (! is_string($key) || $key === '') {
                continue;
            }
            if (! in_array($key, self::PRICING_QUALIFIERS_ALLOWED_KEYS, true)) {
                $rejectedKeys[] = $key;
            }
        }

        if ($rejectedKeys !== []) {
            $message = 'object instance has properties which are not allowed under PricingQualifiers: '
                .implode(', ', array_slice($rejectedKeys, 0, 8));

            return $this->failSummary(
                self::PRICING_QUALIFIERS_POINTER,
                $message,
                self::PRICING_QUALIFIERS_ALLOWED_KEYS,
                $rejectedKeys,
            );
        }

        $vcCode = $this->payloadBuilder->traditionalPnrExtractValidatingCarrierCodeFromAirPriceOptionalQualifiers($oq);
        $fqPresent = data_get($oq, 'FlightQualifiers.VendorPrefs.Airline.Code') !== null;
        $pqVcPresent = array_key_exists('ValidatingCarrier', $pq);

        if ($pqVcPresent) {
            return $this->failSummary(
                self::PRICING_QUALIFIERS_POINTER,
                'ValidatingCarrier must not appear under PricingQualifiers on IATI CPNR v2.4 wire.',
                self::PRICING_QUALIFIERS_ALLOWED_KEYS,
                ['ValidatingCarrier'],
            );
        }

        $hasPassengerType = isset($pq['PassengerType']) && is_array($pq['PassengerType']) && $pq['PassengerType'] !== [];
        if (! $hasPassengerType) {
            return $this->failSummary(
                self::PRICING_QUALIFIERS_POINTER.'/PassengerType',
                'PassengerType required under PricingQualifiers for IATI CPNR AirPrice.',
                self::PRICING_QUALIFIERS_ALLOWED_KEYS,
                [],
            );
        }

        if ($vcCode === null && $fqPresent) {
            return $this->failSummary(
                self::FLIGHT_QUALIFIERS_AIRLINE_POINTER,
                'FlightQualifiers VendorPrefs Airline Code invalid or missing.',
                ['Code'],
                ['FlightQualifiers'],
            );
        }

        $brandRphFailure = $this->validateBrandRphTypes($pq);
        if ($brandRphFailure !== null) {
            return $brandRphFailure;
        }

        return $this->passSummary();
    }

    /**
     * @param  array<string, mixed>  $pq
     * @return array<string, mixed>|null
     */
    protected function validateBrandRphTypes(array $pq): ?array
    {
        $brand = $pq['Brand'] ?? null;
        if (! is_array($brand)) {
            return null;
        }
        $rows = array_is_list($brand) ? $brand : [$brand];
        foreach ($rows as $index => $row) {
            if (! is_array($row) || ! array_key_exists('RPH', $row)) {
                continue;
            }
            if (! $this->payloadBuilder->iatiV24BrandRphWireValueIsSchemaValid($row['RPH'])) {
                $pointer = self::BRAND_RPH_POINTER;
                if ($index > 0) {
                    $pointer = str_replace('/Brand/0/', '/Brand/'.$index.'/', $pointer);
                }

                return $this->failSummary(
                    $pointer,
                    'instance type ('.gettype($row['RPH']).') does not match schema type (integer)',
                    ['RPH', 'content', 'Code'],
                    ['RPH'],
                );
            }
        }

        return null;
    }

    /**
     * @param  array<string, mixed>  $cpnr
     * @return array<string, mixed>
     */
    public function validateCpnrEnvelope(array $wire): array
    {
        $cpnr = is_array($wire['CreatePassengerNameRecordRQ'] ?? null)
            ? $wire['CreatePassengerNameRecordRQ']
            : $wire;

        return $this->validateIatiLikeCpnrV24AirPrice($cpnr);
    }

    /**
     * Classify Sabre HTTP validation failure as schema-only (no ApplicationResults) when safe.
     */
    public function outcomeLooksLikeCpnrSchemaValidationFailure(
        string $errorCode,
        ?string $errorMessage,
        bool $applicationErrorDigestAvailable,
    ): bool {
        if ($applicationErrorDigestAvailable) {
            return false;
        }

        if ($errorCode !== 'sabre_booking_validation_failed' && $errorCode !== 'sabre_booking_payload_validation_failed') {
            return false;
        }

        return self::messageLooksLikeCpnrSchemaValidation($errorMessage ?? '');
    }

    public static function messageLooksLikeCpnrSchemaValidation(string $text): bool
    {
        $lower = strtolower($text);
        $needles = [
            'pointer',
            'createpassengernamerecordrq',
            '/airprice/',
            'pricingqualifiers',
            'object instance has properties',
            'json schema',
            'sabre booking validation failed:',
        ];

        foreach ($needles as $needle) {
            if (str_contains($lower, $needle)) {
                return true;
            }
        }

        return false;
    }

    /**
     * @param  list<string>  $allowedKeys
     * @param  list<string>  $rejectedKeys
     * @return array<string, mixed>
     */
    protected function failSummary(
        string $pointer,
        string $messageSummary,
        array $allowedKeys,
        array $rejectedKeys,
    ): array {
        return [
            'cpnr_schema_validation_status' => 'fail',
            'cpnr_schema_validation_failed' => true,
            'cpnr_schema_validation_pointer' => substr($pointer, 0, 240),
            'cpnr_schema_validation_message_summary' => substr($messageSummary, 0, 240),
            'cpnr_schema_validation_allowed_keys_sample' => array_slice($allowedKeys, 0, 12),
            'cpnr_schema_validation_rejected_keys_sample' => array_slice($rejectedKeys, 0, 12),
            'cpnr_schema_validation_stage' => 'pre_http',
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
            'cpnr_schema_validation_allowed_keys_sample' => array_slice(self::PRICING_QUALIFIERS_ALLOWED_KEYS, 0, 12),
            'cpnr_schema_validation_rejected_keys_sample' => [],
            'cpnr_schema_validation_stage' => 'pre_http',
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public static function notRunSummary(): array
    {
        return [
            'cpnr_schema_validation_status' => 'not_run',
            'cpnr_schema_validation_failed' => false,
            'cpnr_schema_validation_pointer' => null,
            'cpnr_schema_validation_message_summary' => null,
            'cpnr_schema_validation_allowed_keys_sample' => [],
            'cpnr_schema_validation_rejected_keys_sample' => [],
            'cpnr_schema_validation_stage' => null,
        ];
    }
}
