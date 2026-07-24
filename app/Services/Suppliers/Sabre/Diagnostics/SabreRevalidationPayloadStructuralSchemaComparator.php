<?php

namespace App\Services\Suppliers\Sabre\Diagnostics;

use App\Services\Suppliers\Sabre\Gds\SabreRevalidationPayloadBuilder;

/**
 * Read-only structural schema comparison for Sabre revalidation payload styles (no HTTP).
 */
final class SabreRevalidationPayloadStructuralSchemaComparator
{
    /** @var list<string> */
    public const DEFAULT_COMPARE_STYLES = [
        'bfm_revalidate_v1',
        'bfm_revalidate_minimal_segments',
        'bfm_revalidate_with_pricing_context',
        'bfm_revalidate_original_like',
        'client_gds_revalidate_v1',
        'client_gds_revalidate_without_pos',
        'client_gds_revalidate_without_travel_preferences',
        'client_gds_revalidate_segments_only',
        'iati_like_bfm_revalidate_v1',
        'shop_replay_selected_itinerary_v1',
    ];

    public function __construct(
        protected SabreRevalidationPayloadBuilder $builder,
    ) {}

    /**
     * @param  array<string, mixed>  $internalDraft
     * @param  list<string>|null  $styles
     * @return array<string, mixed>
     */
    public function compareForDraft(array $internalDraft, ?array $styles = null, ?string $endpointPath = null): array
    {
        $styles = $styles ?? self::DEFAULT_COMPARE_STYLES;
        $endpoint = $endpointPath ?? SabreRevalidationPayloadBuilder::DEFAULT_REVALIDATE_ENDPOINT_PATH;
        $rows = [];
        foreach ($styles as $style) {
            $payload = $this->builder->buildPayload($internalDraft, $style);
            $safe = $this->builder->safePayloadSummary($payload);
            $coverage = $this->builder->normalizedPayloadCoverageSummary($payload);
            $schema = $this->builder->evaluateRevalidationPayloadSchema($payload, $endpoint);
            $odis = is_array(data_get($payload, 'OTA_AirLowFareSearchRQ.OriginDestinationInformation'))
                ? data_get($payload, 'OTA_AirLowFareSearchRQ.OriginDestinationInformation')
                : [];
            $rows[$style] = [
                'payload_style' => $style,
                'endpoint_path' => $endpoint,
                'origin_destination_contains_flight_segment' => ($schema['contains_invalid_direct_flight_segment'] ?? false) === true,
                'wire_root_keys' => $this->builder->collectWireRootKeyNames($payload),
                'origin_destination_child_keys' => $schema['origin_destination_child_keys'] ?? [],
                'origin_destination_count' => count($odis),
                'segment_count_represented' => (int) ($safe['segment_count'] ?? 0),
                'itinerary_reference_present' => ($safe['has_itinerary_reference'] ?? false) === true,
                'leg_references_present' => ($safe['has_leg_refs'] ?? false) === true,
                'schedule_references_present' => ($safe['has_schedule_refs'] ?? false) === true,
                'fare_component_references_present' => ($safe['has_fare_component_refs'] ?? false) === true,
                'booking_classes_complete' => ($safe['has_booking_class'] ?? false) === true,
                'fare_basis_complete' => ($safe['has_fare_basis'] ?? false) === true,
                'production_blocked' => $this->builder->isProductionBlockedRevalidateStyle($style),
                'production_allowed' => ! $this->builder->isProductionBlockedRevalidateStyle($style),
                'payload_freeze_fingerprint' => $this->builder->revalidationPayloadFreezeFingerprint($payload, $internalDraft),
                'schema_compatibility_verdict' => $this->schemaCompatibilityVerdict($schema),
                'payload_schema_reason_code' => $schema['payload_schema_reason_code'] ?? null,
                'airline_marketing_type_valid' => ($schema['airline_marketing_type_valid'] ?? true) === true,
                'airline_operating_type_valid' => ($schema['airline_operating_type_valid'] ?? true) === true,
                'contains_unsupported_segment_number' => ($schema['contains_unsupported_segment_number'] ?? false) === true,
                'contains_unsupported_resbookdesigcode' => ($schema['contains_unsupported_resbookdesigcode'] ?? false) === true,
                'contains_unsupported_fare_basis_code' => ($schema['contains_unsupported_fare_basis_code'] ?? false) === true,
                'contains_unsupported_cabin_code' => ($schema['contains_unsupported_cabin_code'] ?? false) === true,
                'contains_unsupported_single_branded_fare' => ($schema['contains_unsupported_single_branded_fare'] ?? false) === true,
                'root_version_present' => ($schema['root_version_present'] ?? false) === true,
                'root_version_type_valid' => ($schema['root_version_type_valid'] ?? false) === true,
                'root_child_keys' => $schema['root_child_keys'] ?? [],
                'root_target_present' => ($schema['root_target_present'] ?? false) === true,
                'root_target_type_valid' => ($schema['root_target_type_valid'] ?? true) === true,
                'requestor_id_present' => ($schema['requestor_id_present'] ?? false) === true,
                'requestor_id_type_valid' => ($schema['requestor_id_type_valid'] ?? false) === true,
                'requestor_id_non_empty' => ($schema['requestor_id_non_empty'] ?? false) === true,
                'pos_child_keys' => $schema['pos_child_keys'] ?? [],
                'source_child_keys' => $schema['source_child_keys'] ?? [],
                'requestor_id_child_keys' => $schema['requestor_id_child_keys'] ?? [],
                'requestor_id_child_types' => $schema['requestor_id_child_types'] ?? [],
                'requestor_identity_source_present' => ($schema['requestor_identity_source_present'] ?? false) === true,
                'requestor_identity_source_location' => $schema['requestor_identity_source_location'] ?? null,
                'pseudo_city_code_present' => ($schema['pseudo_city_code_present'] ?? false) === true,
                'pseudo_city_code_type_valid' => ($schema['pseudo_city_code_type_valid'] ?? false) === true,
                'pseudo_city_code_non_empty' => ($schema['pseudo_city_code_non_empty'] ?? false) === true,
                'pseudo_city_code_source_present' => ($schema['pseudo_city_code_source_present'] ?? false) === true,
                'pseudo_city_code_source_location' => $schema['pseudo_city_code_source_location'] ?? null,
                'unsupported_branded_fare_indicator_keys' => $schema['unsupported_branded_fare_indicator_keys'] ?? [],
                'branded_fare_indicator_child_keys' => $schema['branded_fare_indicator_child_keys'] ?? [],
                'branded_fare_indicator_child_types' => $schema['branded_fare_indicator_child_types'] ?? [],
                'branded_fare_context_present' => ($schema['branded_fare_context_present'] ?? false) === true,
                'branded_fare_context_location' => $schema['branded_fare_context_location'] ?? null,
                'booking_class_context_present' => ($schema['booking_class_context_present'] ?? false) === true,
                'cabin_context_present' => ($schema['cabin_context_present'] ?? false) === true,
                'fare_basis_context_present' => ($schema['fare_basis_context_present'] ?? false) === true,
                'pricing_context_present' => ($schema['pricing_context_present'] ?? false) === true,
                'fare_component_references_present' => ($schema['fare_component_references_present'] ?? false) === true,
                'unsupported_flight_child_keys' => $schema['unsupported_flight_child_keys'] ?? [],
                'invalid_schema_type_count' => (int) ($schema['invalid_schema_type_count'] ?? 0),
            ];
        }

        return [
            'report_version' => 'sabre_revalidate_structural_schema_compare_v1',
            'endpoint_path' => $endpoint,
            'styles' => $rows,
        ];
    }

    /**
     * @param  array<string, mixed>  $schema
     */
    protected function schemaCompatibilityVerdict(array $schema): string
    {
        if (($schema['revalidation_payload_schema_valid'] ?? true) !== true) {
            if (($schema['root_version_present'] ?? true) !== true || ($schema['root_version_type_valid'] ?? true) !== true) {
                return 'invalid_root_version';
            }
            if (($schema['requestor_id_present'] ?? true) !== true
                || ($schema['requestor_id_type_valid'] ?? true) !== true
                || ($schema['requestor_id_non_empty'] ?? true) !== true) {
                return 'invalid_requestor_id';
            }
            if (($schema['pseudo_city_code_present'] ?? true) !== true
                || ($schema['pseudo_city_code_type_valid'] ?? true) !== true
                || ($schema['pseudo_city_code_non_empty'] ?? true) !== true) {
                return 'invalid_pseudo_city_code';
            }
            if (($schema['contains_invalid_direct_flight_segment'] ?? false) === true) {
                return 'invalid_flightsegment_on_odi';
            }
            if (($schema['contains_unsupported_segment_number'] ?? false) === true) {
                return 'invalid_unsupported_flight_child_key';
            }
            if (($schema['contains_unsupported_resbookdesigcode'] ?? false) === true) {
                return 'invalid_unsupported_resbookdesigcode';
            }
            if (($schema['contains_unsupported_fare_basis_code'] ?? false) === true) {
                return 'invalid_unsupported_fare_basis_code';
            }
            if (($schema['contains_unsupported_cabin_code'] ?? false) === true) {
                return 'invalid_unsupported_cabin_code';
            }
            if (($schema['contains_unsupported_single_branded_fare'] ?? false) === true) {
                return 'invalid_unsupported_single_branded_fare';
            }

            return 'invalid_airline_scalar_type';
        }

        return 'compatible';
    }
}
