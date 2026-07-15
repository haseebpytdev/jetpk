<?php

namespace Tests\Unit;

use App\Services\Suppliers\Sabre\Booking\SabreBookingPayloadBuilder;
use App\Support\Sabre\SabrePnrAttemptStructureSnapshot;
use Tests\TestCase;

class SabrePnrAttemptStructureSnapshotTest extends TestCase
{
    /**
     * @return array<string, mixed>
     */
    protected function minimalDraft(): array
    {
        return [
            '_valid' => true,
            'supplier_connection_id' => 1,
            '_sabre_pseudo_city_code' => 'AB12',
            'validating_carrier' => 'PK',
            'segments' => [[
                'origin' => 'LHE',
                'destination' => 'DXB',
                'carrier' => 'PK',
                'flight_number' => '233',
                'departure_at' => '2026-08-15T08:00:00',
                'arrival_at' => '2026-08-15T11:00:00',
                'booking_class' => 'V',
            ]],
            'passengers' => [[
                'type' => 'ADT',
                'first_name' => 'Test',
                'last_name' => 'Traveler',
                'gender' => 'MALE',
                'date_of_birth' => '1990-01-15',
            ]],
            'contact' => [
                'email' => 'booker@example.com',
                'phone' => '3001234567',
            ],
            '_requires_passport_doc' => false,
            '_sabre_booking_context' => [],
        ];
    }

    public function test_snapshot_contains_required_safe_structure_keys(): void
    {
        $builder = app(SabreBookingPayloadBuilder::class);
        $envelope = $builder->buildIatiLikeCpnrV24GdsWire($this->minimalDraft(), []);
        $snapshots = app(SabrePnrAttemptStructureSnapshot::class)->buildFromWire($envelope, [
            'endpoint_path' => '/v2.4.0/passenger/records?mode=create',
            'payload_schema' => SabreBookingPayloadBuilder::IATI_LIKE_CPNR_V2_4_GDS,
            'structure_snapshot_source' => 'live_pre_call',
        ]);

        foreach ([
            'safe_request_structure',
            'safe_enhanced_airbook_structure',
            'safe_airbook_structure',
            'safe_airprice_structure',
            'safe_postprocessing_structure',
            'safe_enhanced_airbook_fingerprint',
            'structure_snapshot_version',
            'structure_snapshot_source',
        ] as $key) {
            $this->assertArrayHasKey($key, $snapshots, "Missing snapshot key: {$key}");
        }
        $this->assertSame('live_pre_call', $snapshots['structure_snapshot_source']);
    }

    public function test_snapshot_contains_no_raw_payload_pii_or_secrets(): void
    {
        $builder = app(SabreBookingPayloadBuilder::class);
        $envelope = $builder->buildIatiLikeCpnrV24GdsWire($this->minimalDraft(), []);
        $snapshots = app(SabrePnrAttemptStructureSnapshot::class)->buildFromWire($envelope, [
            'endpoint_path' => '/v2.4.0/passenger/records?mode=create',
            'payload_schema' => SabreBookingPayloadBuilder::IATI_LIKE_CPNR_V2_4_GDS,
        ]);
        $encoded = strtolower(json_encode($snapshots, JSON_UNESCAPED_UNICODE) ?: '');

        foreach ([
            'createpassengernamerecordrq',
            'givenname',
            'surname',
            'passport',
            'booker@example.com',
            '3001234567',
            'ab12',
            'targetcity',
            'password',
            'token',
            'credential',
        ] as $forbidden) {
            $this->assertStringNotContainsString($forbidden, $encoded, "Forbidden substring leaked: {$forbidden}");
        }
    }

    public function test_response_structure_builds_safe_host_fingerprint(): void
    {
        $snapshot = app(SabrePnrAttemptStructureSnapshot::class);
        $response = $snapshot->buildResponseStructure([
            'http_status' => 200,
            'application_results_status' => 'Incomplete',
            'application_results_incomplete' => true,
            'host_warning_sabre_codes' => ['FORMAT'],
            'host_warning_messages_truncated' => ['EnhancedAirBookRQ: FORMAT'],
            'pnr' => '',
        ]);

        $this->assertSame(200, $response['http_status'] ?? null);
        $this->assertSame('INCOMPLETE', $response['application_results_status'] ?? null);
        $this->assertArrayHasKey('safe_host_error_fingerprint', $response);
        $this->assertNull($response['pnr_present'] ?? null);
    }
}
