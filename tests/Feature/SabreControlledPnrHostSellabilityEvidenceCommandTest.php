<?php

namespace Tests\Feature;

use App\Console\Commands\SabreControlledPnrHostSellabilityEvidenceCommand;
use App\Models\Booking;
use App\Support\Bookings\SabreCertifiedRouteSelector;
use App\Support\Bookings\SabreControlledFinalPnrRetryAllowanceGate;
use App\Support\Bookings\SabreControlledFreshPnrContextApply;
use App\Support\Bookings\SabreControlledPnrRetryAfterAirpriceVcFixAllowanceGate;
use App\Support\Bookings\SabreControlledPnrRetryAfterAirpriceVcSchemaFixAllowanceGate;
use App\Support\Bookings\SabreControlledPnrRetryAllowanceGate;
use App\Support\Bookings\SabreControlledStrongRevalidationLinkageApply;
use App\Support\Sabre\SabrePassengerRecordsApplicationResultDigest;
use Database\Seeders\OtaFoundationSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Http;
use Tests\Support\Bookings\ControlledPnrContextTestFixtures;
use Tests\TestCase;

class SabreControlledPnrHostSellabilityEvidenceCommandTest extends TestCase
{
    use ControlledPnrContextTestFixtures;
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(OtaFoundationSeeder::class);
        config([
            'app.env' => 'testing',
            'suppliers.sabre.booking_enabled' => true,
            'suppliers.sabre.cpnr_connecting_same_carrier_gds_enabled' => true,
            'suppliers.sabre.ticketing_enabled' => false,
            'suppliers.sabre.cancel_enabled' => false,
        ]);
        Http::fake();
    }

    protected function postF9qFailureBooking(): Booking
    {
        $snapshot = [
            'supplier_provider' => 'sabre',
            'supplier_offer_id' => 'offer-f9r-qr-bfm',
            'validating_carrier' => 'QR',
            'brand_code' => 'ECONVENIEN',
            'fare_family_code' => 'ECONVENIEN',
            'origin' => 'LHE',
            'destination' => 'JED',
            'fare_breakdown' => [
                'supplier_total' => 88415.63,
                'currency' => 'USD',
                'passenger_counts' => ['adults' => 1],
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
                    'distribution_channel' => 'GDS',
                    'shop_endpoint_path' => '/v4/offers/shop',
                    'itinerary_ref' => '2',
                    'pricing_information_index' => 0,
                    'validating_carrier' => 'QR',
                    'fare_basis_codes' => ['OJPKP1RI', 'OJPKP1RI'],
                    'booking_classes_by_segment' => ['O', 'O'],
                    'fare_component_refs' => [7, 8],
                    'leg_refs' => [7, 8],
                    'schedule_refs' => [7, 8],
                ],
                'sabre_shop_identifiers' => [
                    'itinerary_id' => '2',
                    'pricing_information_index' => 0,
                ],
            ],
        ];
        $revalidatedAt = now()->subMinutes(5)->toIso8601String();

        return $this->booking53Style(array_merge(
            $this->approvalMetaForBooking(),
            [
                'search_criteria' => [
                    'trip_type' => 'one_way',
                    'origin' => 'LHE',
                    'destination' => 'JED',
                    'depart_date' => '2026-07-23',
                    'adults' => 1,
                ],
                'normalized_offer_snapshot' => $snapshot,
                'validated_offer_snapshot' => $snapshot,
                'flight_offer_snapshot' => $snapshot,
                'revalidation_status' => 'success',
                'last_revalidated_at' => $revalidatedAt,
                'selected_offer_created_at' => $revalidatedAt,
                'offer_refresh_status' => 'refreshed',
                'sabre_booking_context' => [
                    'ready_for_booking_payload' => true,
                    'has_revalidation_linkage' => true,
                    'strong_bfm_revalidation_linkage_applied' => true,
                    'validating_carrier' => 'QR',
                    'brand_code' => 'ECONVENIEN',
                ],
                'certified_route_selection' => [
                    'category' => SabreCertifiedRouteSelector::CATEGORY_ONE_WAY_CONNECTING_SAME_CARRIER_GDS,
                    'route_status' => SabreCertifiedRouteSelector::STATUS_CONTROLLED_CERTIFIED,
                    'endpoint_path' => SabreCertifiedRouteSelector::ENDPOINT_PASSENGER_RECORDS_V24_CREATE,
                    'payload_style' => 'iati_like_cpnr_v2_4_gds',
                ],
                SabrePassengerRecordsApplicationResultDigest::META_DIGEST_KEY => [
                    'status' => 'incomplete_no_locator',
                    'application_status' => 'Incomplete',
                    'error_count' => 1,
                    'warning_count' => 1,
                    'errors' => [
                        ['type' => 'error', 'code' => 'ERR.SP.PROVIDER_ERROR', 'message' => 'Unable to perform air booking step'],
                    ],
                    'warnings' => [
                        ['type' => 'warning', 'code' => 'WARN.SWS.HOST.ERROR_IN_RESPONSE', 'message' => 'EnhancedAirBookRQ: *NO FARES/RBD/CARRIER'],
                    ],
                ],
                SabreControlledFreshPnrContextApply::META_KEY => [
                    'applied' => true,
                    'applied_at' => $revalidatedAt,
                    'applied_by' => 'controlled_command',
                ],
                SabreControlledStrongRevalidationLinkageApply::META_KEY => [
                    'applied' => true,
                    'applied_at' => $revalidatedAt,
                    'applied_by' => 'controlled_command',
                    'segment_count_match' => true,
                    'rbd_match' => true,
                    'fare_basis_match' => true,
                    'brand_match' => true,
                    'validating_carrier_match' => true,
                ],
                SabreControlledPnrRetryAllowanceGate::META_KEY => ['used' => true],
                SabreControlledPnrRetryAfterAirpriceVcFixAllowanceGate::META_KEY => ['used' => true],
                SabreControlledPnrRetryAfterAirpriceVcSchemaFixAllowanceGate::META_KEY => ['used' => true],
                SabreControlledFinalPnrRetryAllowanceGate::META_KEY => [
                    'allowed' => true,
                    'used' => true,
                    'create_attempted' => true,
                    'used_for' => SabreControlledFinalPnrRetryAllowanceGate::USED_FOR,
                ],
            ],
        ));
    }

    public function test_read_only_evidence_for_post_f9q_host_failure(): void
    {
        $booking = $this->postF9qFailureBooking();

        $exit = Artisan::call('sabre:controlled-pnr-host-sellability-evidence', [
            '--booking' => (string) $booking->id,
            '--json' => true,
        ]);

        $this->assertSame(0, $exit);
        $decoded = json_decode(trim(Artisan::output()), true);
        $this->assertIsArray($decoded);
        $this->assertTrue($decoded['host_rejected_sellability']);
        $this->assertTrue($decoded['post_final_retry_host_failure']);
        $this->assertSame('NO_FARES_RBD_CARRIER', $decoded['post_final_retry_host_failure_code']);
        $this->assertIsBool($decoded['brand_match']);
        $this->assertTrue($decoded['brand_match']);
        if (($decoded['local_payload_clean'] ?? false) === true) {
            $this->assertStringContainsString('Staff review', $decoded['recommended_next_action']);
        }
        $this->assertArrayNotHasKey('request_body', $decoded);
        $this->assertArrayNotHasKey('response_body', $decoded);
        $this->assertFalse($decoded['live_supplier_call_attempted']);
        $this->assertFalse($decoded['pnr_create_attempted']);
        Http::assertNothingSent();
    }

    public function test_production_requires_exact_readonly_confirm(): void
    {
        config(['app.env' => 'production']);
        $booking = $this->postF9qFailureBooking();

        $exit = Artisan::call('sabre:controlled-pnr-host-sellability-evidence', [
            '--booking' => (string) $booking->id,
        ]);

        $this->assertSame(1, $exit);
        $this->assertStringContainsString(
            SabreControlledPnrHostSellabilityEvidenceCommand::PRODUCTION_READONLY_CONFIRM_PHRASE,
            Artisan::output(),
        );
    }
}
