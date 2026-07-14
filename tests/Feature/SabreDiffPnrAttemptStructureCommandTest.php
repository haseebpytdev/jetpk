<?php

namespace Tests\Feature;

use App\Enums\SupplierProvider;
use App\Models\Agency;
use App\Models\Booking;
use App\Models\BookingContact;
use App\Models\BookingFareBreakdown;
use App\Models\BookingPassenger;
use App\Models\SupplierBookingAttempt;
use App\Services\Suppliers\Sabre\Booking\SabreBookingPayloadBuilder;
use App\Support\Sabre\GdsPnrCreate\SabreGdsPnrCreateStrategyRegistry;
use App\Support\Sabre\SabrePnrAttemptStructureDiff;
use App\Support\Sabre\SabrePnrAttemptStructureSnapshot;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SabreDiffPnrAttemptStructureCommandTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(\Database\Seeders\OtaFoundationSeeder::class);
    }

    public function test_diff_command_compares_failed_to_regenerated_success_attempts(): void
    {
        $bookingFailed = $this->makeDirectPkBooking();
        $bookingSuccess = $this->makeDirectPkBooking();

        $failedAttempt = SupplierBookingAttempt::query()->create([
            'agency_id' => $bookingFailed->agency_id,
            'booking_id' => $bookingFailed->id,
            'provider' => SupplierProvider::Sabre->value,
            'action' => 'create_pnr',
            'status' => 'failed',
            'attempted_at' => now(),
            'completed_at' => now(),
            'safe_summary' => $this->failedAttemptSafeSummary(),
        ]);

        $successAttempt = SupplierBookingAttempt::query()->create([
            'agency_id' => $bookingSuccess->agency_id,
            'booking_id' => $bookingSuccess->id,
            'provider' => SupplierProvider::Sabre->value,
            'action' => 'create_pnr',
            'status' => 'success',
            'attempted_at' => now(),
            'completed_at' => now(),
            'safe_summary' => [
                'payload_schema' => SabreGdsPnrCreateStrategyRegistry::STRATEGY_IATI_LIKE_CPNR_V2_4_GDS,
                'endpoint_path' => '/v2.4.0/passenger/records?mode=create',
                'http_status' => 200,
                'pnr' => 'QNCBBB',
                'application_results_status' => 'Complete',
            ],
        ]);

        $this->artisan('sabre:diff-pnr-attempt-structure', [
            '--failed' => (string) $failedAttempt->id,
            '--success' => (string) $successAttempt->id,
        ])->assertSuccessful()
            ->expectsOutputToContain('Classification: READ-ONLY')
            ->expectsOutputToContain('failed_attempt_id='.$failedAttempt->id)
            ->expectsOutputToContain('field_diff_comparable=true');

        $report = app(SabrePnrAttemptStructureDiff::class)->compare(
            $failedAttempt->id,
            [$successAttempt->id],
        );
        $this->assertTrue($report['field_diff']['comparable'] ?? false);
        $successRow = $report['success'][0] ?? [];
        $this->assertSame('regenerated_from_booking_meta', $successRow['structure_snapshot_source'] ?? null);
        $this->assertArrayHasKey('safe_airbook_structure', $successRow);
        $this->assertArrayHasKey('safe_enhanced_airbook_structure', $successRow);
        $this->assertArrayHasKey('safe_airprice_structure', $successRow);
        $this->assertArrayHasKey('safe_postprocessing_structure', $successRow);
    }

    public function test_diff_highlights_enhanced_airbook_segment_matrix_when_structures_differ(): void
    {
        $diff = app(SabrePnrAttemptStructureDiff::class);
        $method = new \ReflectionMethod($diff, 'highlightStructuralDifferences');
        $method->setAccessible(true);

        $knownGoodMatrix = [[
            'odi_index' => 0,
            'segment_index' => 0,
            'flight_number_format' => 'zero_padded_4',
            'marriage_grp_present' => false,
        ]];
        $badMatrix = [[
            'odi_index' => 0,
            'segment_index' => 0,
            'flight_number_format' => 'unpadded_numeric',
            'marriage_grp_present' => true,
            'operating_airline_present' => true,
        ]];

        $result = $method->invoke(
            $diff,
            ['safe_airbook_structure' => ['flight_segment_field_matrix' => $badMatrix]],
            [['found' => true, 'attempt_id' => 1, 'safe_airbook_structure' => ['flight_segment_field_matrix' => $knownGoodMatrix]]],
        );

        $this->assertArrayHasKey('enhanced_airbook_flight_segment_matrix', $result);
    }

    /**
     * @return array<string, mixed>
     */
    protected function failedAttemptSafeSummary(): array
    {
        $builder = app(SabreBookingPayloadBuilder::class);
        $envelope = $builder->buildIatiLikeCpnrV24GdsWire($this->minimalDraft(), []);

        return array_merge(
            app(SabrePnrAttemptStructureSnapshot::class)->buildFromWire($envelope, [
                'endpoint_path' => '/v2.4.0/passenger/records?mode=create',
                'payload_schema' => SabreGdsPnrCreateStrategyRegistry::STRATEGY_IATI_LIKE_CPNR_V2_4_GDS,
                'structure_snapshot_source' => 'live_pre_call',
            ]),
            [
                'payload_schema' => SabreGdsPnrCreateStrategyRegistry::STRATEGY_IATI_LIKE_CPNR_V2_4_GDS,
                'endpoint_path' => '/v2.4.0/passenger/records?mode=create',
                'http_status' => 200,
                'application_results_status' => 'Incomplete',
                'application_results_incomplete' => true,
                'host_warning_sabre_codes' => ['FORMAT'],
                'host_warning_messages_truncated' => ['EnhancedAirBookRQ: FORMAT'],
            ],
        );
    }

    /**
     * @return array<string, mixed>
     */
    protected function minimalDraft(): array
    {
        return [
            '_valid' => true,
            'supplier_connection_id' => 2,
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

    /**
     * @param  array<string, mixed>  $overrides
     */
    protected function makeDirectPkBooking(array $overrides = []): Booking
    {
        $agency = Agency::query()->where('slug', 'asif-travels')->firstOrFail();
        $booking = Booking::query()->create(array_merge([
            'agency_id' => $agency->id,
            'booking_reference' => 'BR-PK-DIFF-'.uniqid(),
            'status' => 'pending',
            'supplier' => SupplierProvider::Sabre->value,
            'selected_fare_total' => 82485,
            'revalidated_fare_total' => 82485,
            'meta' => [
                'supplier_provider' => SupplierProvider::Sabre->value,
                'supplier_connection_id' => 2,
                'payment_mode' => 'pay_later_booking_request',
                'selected_fare_family_option' => [
                    'brand_code' => 'SM',
                    'displayed_price' => 82485,
                    'baggage_summary' => '20 kg',
                    'fare_basis_codes_by_segment' => ['VOWSM/V'],
                    'booking_classes_by_segment' => ['V'],
                ],
                'sabre_booking_context' => [
                    'selected_brand_code' => 'SM',
                    'brand_code' => 'SM',
                    'fare_basis_codes_by_segment' => ['VOWSM/V'],
                    'booking_classes_by_segment' => ['V'],
                    'baggage' => '20 kg',
                    'validating_carrier' => 'PK',
                    'selected_price_total' => 82485,
                ],
                'normalized_offer_snapshot' => $this->directPkSnapshot(),
                'distribution_channel' => 'gds',
                'fare_option_key' => 'sm-key',
                'search_criteria' => ['trip_type' => 'one_way', 'origin' => 'LHE', 'destination' => 'DXB', 'depart_date' => '2026-08-15'],
            ],
        ], $overrides));

        $booking->forceFill(['travel_date' => '2026-08-15'])->save();

        BookingPassenger::factory()->create([
            'booking_id' => $booking->id,
            'passenger_type' => 'adult',
            'first_name' => 'Test',
            'last_name' => 'Traveler',
            'date_of_birth' => now()->subYears(30)->toDateString(),
            'gender' => 'male',
            'passport_number' => 'AB1234567',
            'passport_issuing_country' => 'PK',
            'passport_expiry_date' => now()->addYears(5)->toDateString(),
            'nationality' => 'PK',
        ]);
        BookingContact::query()->create([
            'booking_id' => $booking->id,
            'email' => 'booker@example.com',
            'phone' => '3001234567',
        ]);
        BookingFareBreakdown::query()->create([
            'booking_id' => $booking->id,
            'total' => 82485,
            'currency' => 'PKR',
        ]);

        return $booking->fresh(['passengers', 'contact', 'fareBreakdown']);
    }

    /**
     * @return array<string, mixed>
     */
    protected function directPkSnapshot(): array
    {
        return [
            'supplier_provider' => SupplierProvider::Sabre->value,
            'supplier_connection_id' => 2,
            'supplier_offer_id' => 'pk-lhe-dxb-test-offer',
            'offer_id' => 'pk-lhe-dxb-test-offer',
            'validating_carrier' => 'PK',
            'distribution_channel' => 'gds',
            'segments' => [[
                'origin' => 'LHE',
                'destination' => 'DXB',
                'carrier' => 'PK',
                'marketing_carrier' => 'PK',
                'operating_carrier' => 'PK',
                'flight_number' => '233',
                'departure_at' => '2026-08-15T08:00:00',
                'arrival_at' => '2026-08-15T11:00:00',
                'booking_class' => 'V',
                'fare_basis_code' => 'VOWSM/V',
            ]],
            'fare_breakdown' => [
                'supplier_total' => 82485,
                'currency' => 'PKR',
                'passenger_counts' => ['adults' => 1, 'children' => 0, 'infants' => 0],
            ],
        ];
    }
}
