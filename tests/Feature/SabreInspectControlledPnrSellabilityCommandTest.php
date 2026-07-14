<?php

namespace Tests\Feature;

use App\Models\Booking;
use App\Services\FlightSearch\FlightSearchService;
use App\Support\Bookings\SabreCertifiedRouteSelector;
use App\Support\Bookings\SabreControlledPnrRetryAfterAirpriceVcFixAllowanceGate;
use App\Support\Bookings\SabreControlledPnrRetryAfterAirpriceVcSchemaFixAllowanceGate;
use App\Support\Bookings\SabreControlledPnrRetryAllowanceGate;
use App\Support\Sabre\SabrePassengerRecordsApplicationResultDigest;
use Database\Seeders\OtaFoundationSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Http;
use Tests\Support\Bookings\ControlledPnrContextTestFixtures;
use Tests\TestCase;

class SabreInspectControlledPnrSellabilityCommandTest extends TestCase
{
    use ControlledPnrContextTestFixtures;
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(OtaFoundationSeeder::class);
        config([
            'suppliers.sabre.booking_enabled' => true,
            'suppliers.sabre.cpnr_connecting_same_carrier_gds_enabled' => true,
            'suppliers.sabre.passenger_records_block_risky_itinerary_live' => false,
            'suppliers.sabre.ticketing_enabled' => false,
            'suppliers.sabre.public_auto_pnr_enabled' => false,
            'suppliers.sabre.verified_multiseg_auto_pnr_enabled' => false,
        ]);
        Http::fake();
    }

    protected function booking53QrSellabilitySnapshot(string $brandCode = 'ECONVENIEN'): array
    {
        return [
            'supplier_provider' => 'sabre',
            'supplier_offer_id' => 'offer-f9m-qr-controlled',
            'validating_carrier' => 'QR',
            'brand_code' => $brandCode,
            'fare_family_code' => $brandCode,
            'origin' => 'LHE',
            'destination' => 'JED',
            'fare_breakdown' => [
                'supplier_total' => 100.0,
                'currency' => 'USD',
                'passenger_counts' => ['adults' => 1, 'children' => 0, 'infants' => 0],
            ],
            'segments' => [
                [
                    'origin' => 'LHE',
                    'destination' => 'DOH',
                    'departure_at' => '2026-07-23T03:10:00',
                    'arrival_at' => '2026-07-23T06:40:00',
                    'carrier' => 'QR',
                    'flight_number' => '621',
                    'booking_class' => 'O',
                    'fare_basis_code' => 'OJPKP1RI',
                ],
                [
                    'origin' => 'DOH',
                    'destination' => 'JED',
                    'departure_at' => '2026-07-23T07:40:00',
                    'arrival_at' => '2026-07-23T10:10:00',
                    'carrier' => 'QR',
                    'flight_number' => '1190',
                    'booking_class' => 'O',
                    'fare_basis_code' => 'OJPKP1RI',
                ],
            ],
            'raw_payload' => [
                'sabre_shop_context' => [
                    'pricing_information_ref' => 'pi-f9m-qr',
                    'offer_ref' => 'offer-f9m-qr',
                    'itinerary_ref' => 'itin-f9m-qr',
                    'validating_carrier' => 'QR',
                    'fare_basis_codes' => ['OJPKP1RI', 'OJPKP1RI'],
                    'pricing_information_index' => 0,
                ],
            ],
        ];
    }

    /**
     * @param  array<string, mixed>  $metaOverrides
     */
    protected function booking53QrSellabilityStyle(array $metaOverrides = []): Booking
    {
        $snapshot = $this->booking53QrSellabilitySnapshot();

        return $this->booking53Style(array_merge([
            'search_criteria' => [
                'trip_type' => 'one_way',
                'origin' => 'LHE',
                'destination' => 'JED',
                'depart_date' => '2026-07-23',
                'adults' => 1,
            ],
            'normalized_offer_snapshot' => $snapshot,
            'validated_offer_snapshot' => $snapshot,
            'sabre_booking_context' => [
                'ready_for_booking_payload' => true,
                'has_revalidation_linkage' => true,
                'validating_carrier' => 'QR',
                'brand_code' => 'ECONVENIEN',
            ],
            'certified_route_selection' => [
                'category' => SabreCertifiedRouteSelector::CATEGORY_ONE_WAY_CONNECTING_SAME_CARRIER_GDS,
                'route_status' => SabreCertifiedRouteSelector::STATUS_CONTROLLED_CERTIFIED,
                'endpoint_path' => SabreCertifiedRouteSelector::ENDPOINT_PASSENGER_RECORDS_V24_CREATE,
                'payload_style' => 'iati_like_cpnr_v2_4_gds',
            ],
        ], $metaOverrides));
    }

    protected function tearDown(): void
    {
        \Mockery::close();
        parent::tearDown();
    }

    public function test_inspect_command_is_read_only_and_outputs_sellability_diagnostics(): void
    {
        $booking = $this->booking53QrSellabilityStyle($this->approvalMetaForBooking());

        Artisan::call('sabre:inspect-controlled-pnr-sellability', [
            '--booking' => (string) $booking->id,
        ]);

        $output = Artisan::output();
        $this->assertStringContainsString('live_supplier_call_attempted=false', $output);
        $this->assertStringContainsString('pnr_create_attempted=false', $output);
        $this->assertStringContainsString('ticketing_attempted=false', $output);
        $this->assertStringContainsString('cancellation_attempted=false', $output);
        $this->assertStringContainsString('segment_sellability_matrix=', $output);
        $this->assertStringContainsString('fare_brand_matrix=', $output);
        $this->assertStringContainsString('recommended_lane=', $output);
        $this->assertStringContainsString('host_no_fares_rbd_carrier_status=', $output);
        $this->assertStringNotContainsString('request_payload', $output);
        $this->assertStringNotContainsString('"raw_payload":', $output);
        Http::assertNothingSent();
    }

    public function test_production_requires_exact_readonly_confirm(): void
    {
        config(['app.env' => 'production']);
        $booking = $this->booking53QrSellabilityStyle($this->approvalMetaForBooking());

        Artisan::call('sabre:inspect-controlled-pnr-sellability', [
            '--booking' => (string) $booking->id,
        ]);
        $this->assertStringContainsString('Production requires --confirm=', Artisan::output());

        Artisan::call('sabre:inspect-controlled-pnr-sellability', [
            '--booking' => (string) $booking->id,
            '--confirm' => 'READONLY-CONTROLLED-PNR-SELLABILITY',
        ]);
        $this->assertStringContainsString('recommended_lane=', Artisan::output());
        Http::assertNothingSent();
    }

    public function test_json_output_has_no_pii_or_secrets(): void
    {
        $booking = $this->booking53QrSellabilityStyle($this->approvalMetaForBooking());

        Artisan::call('sabre:inspect-controlled-pnr-sellability', [
            '--booking' => (string) $booking->id,
            '--json' => true,
        ]);

        $output = Artisan::output();
        $this->assertStringNotContainsString('passport', strtolower($output));
        $this->assertStringNotContainsString('request_body', $output);
        $decoded = json_decode(trim($output), true);
        $this->assertIsArray($decoded);
        $this->assertFalse($decoded['live_supplier_call_attempted']);
        $this->assertFalse($decoded['ticketing_attempted']);
    }

    public function test_classifies_stale_context_when_revalidation_is_old(): void
    {
        $booking = $this->booking53Style(array_merge($this->approvalMetaForBooking(), [
            'last_revalidated_at' => now()->subHours(3)->toIso8601String(),
            'offer_freshness' => ['freshness_status' => 'stale'],
        ]));

        Artisan::call('sabre:inspect-controlled-pnr-sellability', [
            '--booking' => (string) $booking->id,
            '--json' => true,
        ]);

        $decoded = json_decode(trim(Artisan::output()), true);
        $this->assertTrue($decoded['stale_context_risk']);
        $this->assertSame('refresh_required_before_retry', $decoded['recommended_lane']);
    }

    public function test_classifies_weak_revalidation_when_legacy_signal_without_strong_linkage(): void
    {
        $snapshot = [
            'supplier_provider' => 'sabre',
            'validating_carrier' => 'GF',
            'segments' => [
                [
                    'origin' => 'LHE',
                    'destination' => 'BAH',
                    'departure_at' => '2026-07-29T08:00:00',
                    'carrier' => 'GF',
                    'flight_number' => '765',
                    'booking_class' => 'W',
                    'fare_basis_code' => 'WDLIT3PK',
                ],
            ],
            'fare_breakdown' => ['supplier_total' => 100.0, 'currency' => 'USD'],
        ];

        $booking = $this->booking53Style(array_merge($this->approvalMetaForBooking(), [
            'normalized_offer_snapshot' => $snapshot,
            'validated_offer_snapshot' => $snapshot,
            'revalidation_status' => 'success',
            'last_revalidated_at' => now()->subMinutes(2)->toIso8601String(),
            'sabre_booking_context' => ['has_revalidation_linkage' => false],
        ]));

        Artisan::call('sabre:inspect-controlled-pnr-sellability', [
            '--booking' => (string) $booking->id,
            '--json' => true,
        ]);

        $decoded = json_decode(trim(Artisan::output()), true);
        $this->assertTrue($decoded['legacy_revalidation_signal_used']);
        $this->assertTrue($decoded['weak_revalidation_risk']);
        $this->assertSame('selected_offer_not_strongly_revalidated', $decoded['recommended_lane']);
    }

    public function test_classifies_host_no_fares_unresolved_when_application_warning_present_and_payload_clean(): void
    {
        $digest = [
            'status' => 'incomplete_no_locator',
            'application_status' => 'Incomplete',
            'has_record_locator' => false,
            'error_count' => 1,
            'warning_count' => 1,
            'errors' => [
                ['type' => 'error', 'code' => 'ERR.SP.PROVIDER_ERROR', 'message' => 'Unable to perform air booking step'],
            ],
            'warnings' => [
                ['type' => 'warning', 'code' => 'WARN.SWS.HOST.ERROR_IN_RESPONSE', 'message' => 'EnhancedAirBookRQ: *NO FARES/RBD/CARRIER'],
            ],
            'messages' => [],
            'source' => 'passenger_records_create',
            'recorded_at' => now()->toIso8601String(),
        ];

        $booking = $this->booking53QrSellabilityStyle(array_merge($this->approvalMetaForBooking(), [
            SabrePassengerRecordsApplicationResultDigest::META_DIGEST_KEY => $digest,
        ]));

        Artisan::call('sabre:inspect-controlled-pnr-sellability', [
            '--booking' => (string) $booking->id,
            '--json' => true,
        ]);

        $decoded = json_decode(trim(Artisan::output()), true);
        $this->assertSame('unresolved', $decoded['host_no_fares_rbd_carrier_status']);
        $this->assertTrue($decoded['host_sellability_risk']);
        $this->assertFalse($decoded['hard_payload_risk']);
        $this->assertContains($decoded['recommended_lane'], [
            'host_inventory_or_pcc_entitlement_issue',
            'no_safe_retry_recommended',
        ]);
    }

    public function test_classifies_brand_mismatch_when_safe_contexts_disagree(): void
    {
        $validated = $this->booking53QrSellabilitySnapshot('ECLASSIC');
        $normalized = $this->booking53QrSellabilitySnapshot('ECONVENIEN');

        $booking = $this->booking53QrSellabilityStyle(array_merge($this->approvalMetaForBooking(), [
            'normalized_offer_snapshot' => $normalized,
            'validated_offer_snapshot' => $validated,
            'last_revalidated_at' => now()->subMinutes(2)->toIso8601String(),
            'sabre_booking_context' => [
                'ready_for_booking_payload' => true,
                'has_revalidation_linkage' => true,
                'validating_carrier' => 'QR',
                'brand_code' => 'ECONVENIEN',
            ],
        ]));

        Artisan::call('sabre:inspect-controlled-pnr-sellability', [
            '--booking' => (string) $booking->id,
            '--json' => true,
        ]);

        $decoded = json_decode(trim(Artisan::output()), true);
        $this->assertFalse($decoded['fare_brand_matrix']['brand_consistency']);
        $this->assertTrue($decoded['brand_qualifier_risk']);
        $this->assertSame('brand_qualifier_requires_adjustment', $decoded['recommended_lane']);
    }

    public function test_classifies_fare_basis_mismatch_when_snapshots_disagree(): void
    {
        $validated = $this->booking53QrSellabilitySnapshot();
        $normalized = json_decode(json_encode($validated), true);
        $normalized['segments'][0]['fare_basis_code'] = 'OTHERFB1';
        $normalized['segments'][1]['fare_basis_code'] = 'OTHERFB1';
        $normalized['raw_payload']['sabre_shop_context']['fare_basis_codes'] = ['OTHERFB1', 'OTHERFB1'];

        $booking = $this->booking53QrSellabilityStyle(array_merge($this->approvalMetaForBooking(), [
            'normalized_offer_snapshot' => $normalized,
            'validated_offer_snapshot' => $validated,
            'last_revalidated_at' => now()->subMinutes(2)->toIso8601String(),
        ]));

        Artisan::call('sabre:inspect-controlled-pnr-sellability', [
            '--booking' => (string) $booking->id,
            '--json' => true,
        ]);

        $decoded = json_decode(trim(Artisan::output()), true);
        $this->assertFalse($decoded['fare_brand_matrix']['fare_basis_consistency']);
        $this->assertContains($decoded['recommended_lane'], [
            'rbd_or_fare_basis_not_sellable',
            'brand_qualifier_requires_adjustment',
        ]);
    }

    public function test_probe_refused_in_production_without_stricter_confirm(): void
    {
        config(['app.env' => 'production']);
        $booking = $this->booking53Style($this->approvalMetaForBooking());

        Artisan::call('sabre:inspect-controlled-pnr-sellability', [
            '--booking' => (string) $booking->id,
            '--probe-fresh-revalidate' => true,
            '--confirm' => 'READONLY-CONTROLLED-PNR-SELLABILITY',
        ]);

        $this->assertStringContainsString('Invalid --confirm phrase', Artisan::output());
    }

    public function test_optional_fresh_probe_does_not_create_booking_or_mutate_meta(): void
    {
        $booking = $this->booking53QrSellabilityStyle($this->approvalMetaForBooking());
        $metaBefore = $booking->fresh()->meta;

        Artisan::call('sabre:inspect-controlled-pnr-sellability', [
            '--booking' => (string) $booking->id,
            '--probe-fresh-revalidate' => true,
            '--json' => true,
        ]);

        $decoded = json_decode(trim(Artisan::output()), true);
        $this->assertTrue($decoded['live_supplier_call_attempted']);
        $this->assertFalse($decoded['pnr_create_attempted']);
        $this->assertFalse($decoded['ticketing_attempted']);
        $this->assertArrayHasKey('fresh_probe', $decoded);

        $booking->refresh();
        $this->assertSame($metaBefore, $booking->meta);
        $this->assertNull($booking->pnr);
    }

    public function test_reference_resolves_by_booking_reference_column(): void
    {
        $booking = $this->booking53QrSellabilityStyle($this->approvalMetaForBooking());
        $booking->forceFill(['booking_reference' => 'PAR-F9M-REF-LOOKUP'])->save();

        Artisan::call('sabre:inspect-controlled-pnr-sellability', [
            '--reference' => 'PAR-F9M-REF-LOOKUP',
            '--json' => true,
        ]);

        $decoded = json_decode(trim(Artisan::output()), true);
        $this->assertSame($booking->id, $decoded['booking_id']);
        $this->assertSame('PAR-F9M-REF-LOOKUP', $decoded['booking_reference']);
    }

    public function test_same_rbd_list_true_when_probe_lists_match(): void
    {
        $booking = $this->booking53QrSellabilityStyle(array_merge($this->approvalMetaForBooking(), [
            'search_criteria' => [
                'trip_type' => 'one_way',
                'origin' => 'LHE',
                'destination' => 'JED',
                'depart_date' => '2026-07-23',
                'adults' => 1,
            ],
        ]));

        $freshOffer = $this->booking53QrSellabilitySnapshot();
        $mock = \Mockery::mock(FlightSearchService::class);
        $mock->shouldReceive('searchWithMeta')
            ->once()
            ->andReturn(['offers' => [$freshOffer], 'warnings' => []]);
        $this->app->instance(FlightSearchService::class, $mock);

        Artisan::call('sabre:inspect-controlled-pnr-sellability', [
            '--booking' => (string) $booking->id,
            '--probe-fresh-revalidate' => true,
            '--json' => true,
        ]);

        $decoded = json_decode(trim(Artisan::output()), true);
        $this->assertTrue($decoded['fresh_probe']['same_rbd_list']);
        $this->assertSame(['O', 'O'], $decoded['fresh_probe']['existing_rbd_list']);
        $this->assertSame(['O', 'O'], $decoded['fresh_probe']['fresh_rbd_list']);
    }

    public function test_no_public_auto_pnr_or_ticketing_flags_changed(): void
    {
        $this->assertFalse((bool) config('suppliers.sabre.ticketing_enabled'));
        $this->assertFalse((bool) config('suppliers.sabre.public_auto_pnr_enabled'));
        $this->assertFalse((bool) config('suppliers.sabre.verified_multiseg_auto_pnr_enabled'));
    }

    public function test_classifies_no_safe_retry_when_all_controlled_retries_consumed(): void
    {
        $digest = [
            'status' => 'incomplete_no_locator',
            'application_status' => 'Incomplete',
            'has_record_locator' => false,
            'error_count' => 1,
            'warning_count' => 1,
            'errors' => [
                ['type' => 'error', 'code' => 'ERR.SP.PROVIDER_ERROR', 'message' => 'Unable to perform air booking step'],
            ],
            'warnings' => [
                ['type' => 'warning', 'code' => 'WARN.SWS.HOST.ERROR_IN_RESPONSE', 'message' => 'EnhancedAirBookRQ: *NO FARES/RBD/CARRIER'],
            ],
            'messages' => [],
            'source' => 'passenger_records_create',
            'recorded_at' => now()->toIso8601String(),
        ];

        $booking = $this->booking53QrSellabilityStyle(array_merge($this->approvalMetaForBooking(), [
            SabrePassengerRecordsApplicationResultDigest::META_DIGEST_KEY => $digest,
            SabreControlledPnrRetryAllowanceGate::META_KEY => ['used' => true, 'used_at' => now()->toIso8601String()],
            SabreControlledPnrRetryAfterAirpriceVcFixAllowanceGate::META_KEY => ['used' => true, 'used_at' => now()->toIso8601String()],
            SabreControlledPnrRetryAfterAirpriceVcSchemaFixAllowanceGate::META_KEY => ['used' => true, 'used_at' => now()->toIso8601String()],
        ]));

        Artisan::call('sabre:inspect-controlled-pnr-sellability', [
            '--booking' => (string) $booking->id,
            '--json' => true,
        ]);

        $decoded = json_decode(trim(Artisan::output()), true);
        $this->assertSame('no_safe_retry_recommended', $decoded['recommended_lane']);
    }
}
