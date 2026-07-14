<?php

namespace Tests\Feature;

use App\Data\SupplierBookingResultData;
use App\Enums\BookingStatus;
use App\Models\Agency;
use App\Models\Booking;
use App\Models\BookingContact;
use App\Models\BookingPassenger;
use App\Models\SupplierBookingAttempt;
use App\Models\SupplierConnection;
use App\Services\Suppliers\Sabre\Booking\SabreBookingService;
use App\Support\Bookings\SabreCertifiedRouteSelector;
use App\Support\Bookings\SabreControlledFinalPnrRetryAllowanceGate;
use App\Support\Bookings\SabreControlledFreshPnrContextApply;
use App\Support\Bookings\SabreControlledPnrManualReviewApproval;
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

class SabreControlledCreatePnrCommandTest extends TestCase
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
        ]);
        Http::fake();
    }

    public function test_dry_run_does_not_call_supplier(): void
    {
        config([
            'suppliers.sabre.booking_live_call_enabled' => true,
        ]);
        $booking = $this->booking53Style($this->approvalMetaForBooking());
        $attemptsBefore = SupplierBookingAttempt::query()->count();

        Artisan::call('sabre:controlled-create-pnr', [
            '--booking' => (string) $booking->id,
            '--dry-run' => true,
        ]);

        $output = Artisan::output();
        $this->assertStringContainsString('live_supplier_call_attempted=false', $output);
        $this->assertStringContainsString('pnr_create_attempted=false', $output);
        $this->assertStringContainsString('ticketing_attempted=false', $output);
        $this->assertStringContainsString('cancellation_attempted=false', $output);
        $this->assertStringContainsString('booking_created_in_supplier=false', $output);
        $this->assertStringContainsString('classification=dry_run_readiness_only', $output);
        $this->assertStringContainsString('hard_no_fares_rbd_carrier_risk=', $output);
        $this->assertStringContainsString('airprice_validating_carrier_present=', $output);
        $this->assertStringContainsString('brand_match=', $output);
        $this->assertSame($attemptsBefore, SupplierBookingAttempt::query()->count());
        Http::assertNothingSent();
    }

    public function test_without_confirm_does_not_call_supplier(): void
    {
        $booking = $this->sabreBooking();

        Artisan::call('sabre:controlled-create-pnr', [
            '--booking' => (string) $booking->id,
        ]);

        $output = Artisan::output();
        $this->assertStringContainsString('live_supplier_call_attempted=false', $output);
        $this->assertStringContainsString('classification=dry_run_readiness_only', $output);
        Http::assertNothingSent();
    }

    public function test_wrong_confirm_is_blocked(): void
    {
        $booking = $this->sabreBooking();

        Artisan::call('sabre:controlled-create-pnr', [
            '--booking' => (string) $booking->id,
            '--confirm' => 'CREATE-PNR-FOR-BOOKING-99999',
        ]);

        $output = Artisan::output();
        $this->assertStringContainsString('live_supplier_call_attempted=false', $output);
        $this->assertStringContainsString('classification=blocked_missing_confirmation', $output);
        Http::assertNothingSent();
    }

    public function test_exact_confirm_with_live_call_disabled_still_blocks_supplier(): void
    {
        $booking = $this->sabreBooking();

        Artisan::call('sabre:controlled-create-pnr', [
            '--booking' => (string) $booking->id,
            '--confirm' => 'CREATE-PNR-FOR-BOOKING-'.$booking->id,
        ]);

        $output = Artisan::output();
        $this->assertStringContainsString('live_supplier_call_attempted=false', $output);
        $this->assertStringContainsString('pnr_create_attempted=false', $output);
        Http::assertNothingSent();
    }

    public function test_dry_run_after_approval_requires_exact_create_confirmation(): void
    {
        config([
            'suppliers.sabre.booking_enabled' => true,
            'suppliers.sabre.booking_live_call_enabled' => true,
            'suppliers.sabre.cpnr_connecting_same_carrier_gds_enabled' => true,
            'suppliers.sabre.passenger_records_block_risky_itinerary_live' => false,
        ]);

        $booking = $this->booking53Style(array_merge([
            'defer_supplier_booking_to_manual_review' => true,
            'supplier_pnr_deferred_reason' => SabreCertifiedRouteSelector::DEFER_REASON,
        ], $this->approvalMetaForBooking()));

        Artisan::call('sabre:controlled-create-pnr', [
            '--booking' => (string) $booking->id,
            '--dry-run' => true,
        ]);

        $output = Artisan::output();
        $this->assertStringContainsString('can_attempt_supplier_pnr=true', $output);
        $this->assertStringContainsString('controlled_pnr_manual_review_approved=true', $output);
        $this->assertStringContainsString('exact_create_confirmation_required=true', $output);
        $this->assertStringContainsString('classification=dry_run_readiness_only', $output);
        $this->assertStringContainsString('live_supplier_call_attempted=false', $output);
        Http::assertNothingSent();
    }

    public function test_dry_run_after_fare_acceptance_shows_historical_fare_flags(): void
    {
        config([
            'suppliers.sabre.booking_enabled' => true,
            'suppliers.sabre.booking_live_call_enabled' => true,
            'suppliers.sabre.cpnr_connecting_same_carrier_gds_enabled' => true,
            'suppliers.sabre.passenger_records_block_risky_itinerary_live' => false,
        ]);

        $booking = $this->booking53StyleWithFareChangeGate(array_merge(
            $this->approvalMetaForBooking(),
            [
                'defer_supplier_booking_to_manual_review' => true,
                'supplier_pnr_deferred_reason' => SabreCertifiedRouteSelector::DEFER_REASON,
            ],
        ));

        Artisan::call('sabre:accept-controlled-pnr-fare-change', [
            '--booking' => (string) $booking->id,
            '--confirm' => 'ACCEPT-CONTROLLED-PNR-FARE-FOR-BOOKING-'.$booking->id,
        ]);

        Artisan::call('sabre:controlled-create-pnr', [
            '--booking' => (string) $booking->id,
            '--dry-run' => true,
        ]);

        $output = Artisan::output();
        $this->assertStringContainsString('controlled_pnr_fare_change_accepted=true', $output);
        $this->assertStringContainsString('historical_offer_refresh_price_changed=true', $output);
        $this->assertStringContainsString('historical_offer_refresh_requires_customer_confirmation=true', $output);
        $this->assertStringContainsString('exact_create_confirmation_required=true', $output);
        Http::assertNothingSent();
    }

    public function test_dry_run_includes_historical_defer_fields_without_override(): void
    {
        config([
            'suppliers.sabre.booking_enabled' => true,
            'suppliers.sabre.booking_live_call_enabled' => true,
            'suppliers.sabre.cpnr_connecting_same_carrier_gds_enabled' => true,
            'suppliers.sabre.passenger_records_block_risky_itinerary_live' => false,
        ]);

        $booking = $this->booking53Style(array_merge([
            'defer_supplier_booking_to_manual_review' => true,
            'supplier_pnr_deferred_reason' => SabreCertifiedRouteSelector::DEFER_REASON,
        ], $this->approvalMetaForBooking()));

        Artisan::call('sabre:controlled-create-pnr', [
            '--booking' => (string) $booking->id,
            '--dry-run' => true,
        ]);

        $output = Artisan::output();
        $this->assertStringContainsString('historical_defer_supplier_booking_to_manual_review=true', $output);
        $this->assertStringContainsString('controlled_manual_review_override_used=false', $output);
        $this->assertStringContainsString('live_supplier_call_attempted=false', $output);
        Http::assertNothingSent();
    }

    public function test_without_approval_blocks_manual_review_and_does_not_use_override(): void
    {
        config([
            'suppliers.sabre.booking_enabled' => true,
            'suppliers.sabre.booking_live_call_enabled' => true,
            'suppliers.sabre.cpnr_connecting_same_carrier_gds_enabled' => true,
            'suppliers.sabre.passenger_records_block_risky_itinerary_live' => false,
        ]);

        $booking = $this->booking53Style([
            'defer_supplier_booking_to_manual_review' => true,
            'supplier_pnr_deferred_reason' => SabreCertifiedRouteSelector::DEFER_REASON,
        ]);

        Artisan::call('sabre:controlled-create-pnr', [
            '--booking' => (string) $booking->id,
            '--confirm' => 'CREATE-PNR-FOR-BOOKING-'.$booking->id,
        ]);

        $output = Artisan::output();
        $this->assertStringContainsString('live_supplier_call_attempted=false', $output);
        $this->assertStringContainsString('controlled_manual_review_override_used=false', $output);
        $this->assertStringContainsString('manual_review_required', $output);
        Http::assertNothingSent();
    }

    public function test_dry_run_does_not_set_controlled_manual_review_override_used(): void
    {
        config([
            'suppliers.sabre.booking_enabled' => true,
            'suppliers.sabre.booking_live_call_enabled' => true,
            'suppliers.sabre.cpnr_connecting_same_carrier_gds_enabled' => true,
            'suppliers.sabre.passenger_records_block_risky_itinerary_live' => false,
        ]);

        $booking = $this->booking53Style(array_merge([
            'defer_supplier_booking_to_manual_review' => true,
            'supplier_pnr_deferred_reason' => SabreCertifiedRouteSelector::DEFER_REASON,
        ], $this->approvalMetaForBooking()));

        Artisan::call('sabre:controlled-create-pnr', [
            '--booking' => (string) $booking->id,
            '--dry-run' => true,
        ]);

        $output = Artisan::output();
        $this->assertStringContainsString('controlled_manual_review_override_used=false', $output);
        $this->assertStringContainsString('live_supplier_call_attempted=false', $output);
    }

    public function test_dry_run_includes_controlled_supplier_retry_allowance_fields_as_false(): void
    {
        config([
            'suppliers.sabre.booking_enabled' => true,
            'suppliers.sabre.booking_live_call_enabled' => true,
            'suppliers.sabre.cpnr_connecting_same_carrier_gds_enabled' => true,
            'suppliers.sabre.passenger_records_block_risky_itinerary_live' => false,
        ]);

        $booking = $this->booking53StyleWithFareChangeGate(array_merge(
            $this->approvalMetaForBooking(),
            [
                'defer_supplier_booking_to_manual_review' => true,
                'supplier_pnr_deferred_reason' => SabreCertifiedRouteSelector::DEFER_REASON,
            ],
        ));

        Artisan::call('sabre:accept-controlled-pnr-fare-change', [
            '--booking' => (string) $booking->id,
            '--confirm' => 'ACCEPT-CONTROLLED-PNR-FARE-FOR-BOOKING-'.$booking->id,
        ]);

        Artisan::call('sabre:controlled-create-pnr', [
            '--booking' => (string) $booking->id,
            '--dry-run' => true,
        ]);

        $output = Artisan::output();
        $this->assertStringContainsString('controlled_supplier_retry_allowance_used=false', $output);
        $this->assertStringContainsString('controlled_supplier_retry_allowance_reason=', $output);
    }

    public function test_dry_run_includes_final_retry_allowance_and_readiness_fields(): void
    {
        config([
            'suppliers.sabre.booking_enabled' => true,
            'suppliers.sabre.booking_live_call_enabled' => true,
            'suppliers.sabre.cpnr_connecting_same_carrier_gds_enabled' => true,
            'suppliers.sabre.passenger_records_block_risky_itinerary_live' => false,
        ]);

        $booking = $this->booking53Style($this->approvalMetaForBooking());
        $gate = app(SabreControlledFinalPnrRetryAllowanceGate::class);
        $meta = is_array($booking->meta) ? $booking->meta : [];
        $meta[SabreControlledFinalPnrRetryAllowanceGate::META_KEY] = $gate->buildAllowanceRecord($booking, []);
        $booking->forceFill(['meta' => $meta])->save();

        Artisan::call('sabre:controlled-create-pnr', [
            '--booking' => (string) $booking->id,
            '--dry-run' => true,
        ]);

        $output = Artisan::output();
        $this->assertStringContainsString('controlled_final_pnr_retry_allowance_present=true', $output);
        $this->assertStringContainsString('controlled_final_pnr_retry_allowance_valid=true', $output);
        $this->assertStringContainsString('final_pnr_retry_ready=', $output);
        $this->assertStringContainsString('final_pnr_retry_blockers=', $output);
        $this->assertStringContainsString('exact_create_confirm_phrase=CREATE-PNR-FOR-BOOKING-'.$booking->id, $output);
        $this->assertStringContainsString('ticketing_attempted=false', $output);
        $this->assertStringContainsString('cancellation_attempted=false', $output);
        Http::assertNothingSent();
    }

    public function test_failure_output_includes_application_error_digest_summary_when_meta_present(): void
    {
        config([
            'suppliers.sabre.booking_enabled' => true,
            'suppliers.sabre.booking_live_call_enabled' => true,
            'suppliers.sabre.cpnr_connecting_same_carrier_gds_enabled' => true,
            'suppliers.sabre.passenger_records_block_risky_itinerary_live' => false,
        ]);

        $digest = [
            'status' => 'incomplete_no_locator',
            'application_status' => 'Incomplete',
            'has_record_locator' => false,
            'error_count' => 1,
            'warning_count' => 0,
            'message_count' => 0,
            'errors' => [
                ['type' => 'error', 'code' => 'ERR.SP.PROVIDER_ERROR', 'message' => 'Unable to perform air booking step'],
            ],
            'warnings' => [],
            'messages' => [],
        ];

        $booking = $this->booking53StyleWithFareChangeGate(array_merge(
            $this->approvalMetaForBooking(),
            [
                'defer_supplier_booking_to_manual_review' => true,
                'supplier_pnr_deferred_reason' => SabreCertifiedRouteSelector::DEFER_REASON,
            ],
        ));

        Artisan::call('sabre:accept-controlled-pnr-fare-change', [
            '--booking' => (string) $booking->id,
            '--confirm' => 'ACCEPT-CONTROLLED-PNR-FARE-FOR-BOOKING-'.$booking->id,
        ]);

        $meta = is_array($booking->fresh()->meta) ? $booking->fresh()->meta : [];
        $meta[SabrePassengerRecordsApplicationResultDigest::META_DIGEST_KEY] = $digest;
        $meta['sabre_last_create_status'] = 'Incomplete';
        $meta['sabre_last_create_error_code'] = 'ERR.SP.PROVIDER_ERROR';
        $booking->forceFill(['meta' => $meta])->save();

        $realService = app(SabreBookingService::class);
        $serviceMock = \Mockery::mock($realService)->makePartial();
        $serviceMock->shouldReceive('createSupplierBooking')
            ->once()
            ->andReturn(new SupplierBookingResultData(
                success: false,
                status: 'manual_review',
                provider: 'sabre',
                error_code: 'sabre_booking_application_error',
                error_message: 'Sabre Passenger Records returned Incomplete or NotProcessed without a PNR locator. Staff review required.',
                safe_summary: ['source' => 'sabre_booking_service'],
            ));
        $this->instance(SabreBookingService::class, $serviceMock);

        Artisan::call('sabre:controlled-create-pnr', [
            '--booking' => (string) $booking->id,
            '--confirm' => 'CREATE-PNR-FOR-BOOKING-'.$booking->id,
        ]);

        $output = Artisan::output();
        $this->assertStringContainsString('classification=controlled_pnr_create_failed', $output);
        $this->assertStringContainsString('application_error_digest_available=true', $output);
        $this->assertStringContainsString('sabre_application_status=Incomplete', $output);
        $this->assertStringContainsString('sabre_application_first_error_code=ERR.SP.PROVIDER_ERROR', $output);
    }

    public function test_failure_output_includes_payload_digest_summary_when_rebuilt(): void
    {
        config([
            'suppliers.sabre.booking_enabled' => true,
            'suppliers.sabre.booking_live_call_enabled' => true,
            'suppliers.sabre.cpnr_connecting_same_carrier_gds_enabled' => true,
            'suppliers.sabre.passenger_records_block_risky_itinerary_live' => false,
        ]);

        $booking = $this->booking53StyleWithFareChangeGate(array_merge(
            $this->approvalMetaForBooking(),
            [
                'defer_supplier_booking_to_manual_review' => true,
                'supplier_pnr_deferred_reason' => SabreCertifiedRouteSelector::DEFER_REASON,
            ],
        ));

        Artisan::call('sabre:accept-controlled-pnr-fare-change', [
            '--booking' => (string) $booking->id,
            '--confirm' => 'ACCEPT-CONTROLLED-PNR-FARE-FOR-BOOKING-'.$booking->id,
        ]);

        $realService = app(SabreBookingService::class);
        $serviceMock = \Mockery::mock($realService)->makePartial();
        $serviceMock->shouldReceive('createSupplierBooking')
            ->once()
            ->andReturn(new SupplierBookingResultData(
                success: false,
                status: 'manual_review',
                provider: 'sabre',
                error_code: 'sabre_booking_application_error',
                error_message: 'Sabre Passenger Records returned Incomplete or NotProcessed without a PNR locator. Staff review required.',
                safe_summary: ['source' => 'sabre_booking_service'],
            ));
        $this->instance(SabreBookingService::class, $serviceMock);

        Artisan::call('sabre:controlled-create-pnr', [
            '--booking' => (string) $booking->id,
            '--confirm' => 'CREATE-PNR-FOR-BOOKING-'.$booking->id,
        ]);

        $output = Artisan::output();
        $this->assertStringContainsString('classification=controlled_pnr_create_failed', $output);
        $this->assertStringContainsString('payload_digest_available=', $output);
        $this->assertStringContainsString('no_fares_rbd_carrier_risk=', $output);
        $this->assertStringContainsString('airbook_segment_count=', $output);
        $this->assertStringContainsString('airprice_present=', $output);
    }

    /**
     * @return array<string, mixed>
     */
    protected function approvalMetaForBooking(): array
    {
        return [
            SabreControlledPnrManualReviewApproval::META_KEY => app(
                SabreControlledPnrManualReviewApproval::class
            )->buildApprovalRecord(
                Booking::factory()->make(['reference_code' => 'PAR-F9C']),
                'controlled_burn_in',
                'platform_ops',
            ),
        ];
    }

    protected function sabreBooking(): Booking
    {
        $agency = Agency::query()->where('slug', 'asif-travels')->firstOrFail();
        $conn = SupplierConnection::query()
            ->where('agency_id', $agency->id)
            ->where('provider', 'sabre')
            ->firstOrFail();

        $booking = Booking::factory()->create([
            'agency_id' => $agency->id,
            'status' => BookingStatus::Paid,
            'meta' => [
                'supplier_provider' => 'sabre',
                'supplier_connection_id' => $conn->id,
                'normalized_offer_snapshot' => [
                    'validating_carrier' => 'GF',
                    'segments' => [
                        ['origin' => 'LHE', 'destination' => 'DXB', 'carrier' => 'GF', 'booking_class' => 'Y'],
                    ],
                ],
            ],
        ]);

        BookingPassenger::factory()->for($booking)->create([
            'passenger_index' => 0,
            'is_lead_passenger' => true,
            'first_name' => 'Test',
            'last_name' => 'Passenger',
            'date_of_birth' => now()->subYears(30)->toDateString(),
            'gender' => 'male',
            'passenger_type' => 'adult',
        ]);
        BookingContact::query()->create([
            'booking_id' => $booking->id,
            'email' => 'guest@example.test',
            'phone' => '+923001234567',
        ]);

        return $booking->fresh(['passengers', 'contact']);
    }

    public function test_dry_run_blocks_after_f9q_final_retry_host_failure(): void
    {
        $revalidatedAt = now()->subMinutes(5)->toIso8601String();
        $snapshot = [
            'supplier_provider' => 'sabre',
            'validating_carrier' => 'QR',
            'brand_code' => 'ECONVENIEN',
            'origin' => 'LHE',
            'destination' => 'JED',
            'segments' => [
                ['origin' => 'LHE', 'destination' => 'DOH', 'departure_at' => '2026-07-23T03:10:00', 'carrier' => 'QR', 'booking_class' => 'O', 'fare_basis_code' => 'OJPKP1RI'],
                ['origin' => 'DOH', 'destination' => 'JED', 'departure_at' => '2026-07-23T07:40:00', 'carrier' => 'QR', 'booking_class' => 'O', 'fare_basis_code' => 'OJPKP1RI'],
            ],
            'raw_payload' => [
                'sabre_shop_context' => [
                    'validating_carrier' => 'QR',
                    'fare_basis_codes' => ['OJPKP1RI', 'OJPKP1RI'],
                    'booking_classes_by_segment' => ['O', 'O'],
                ],
            ],
        ];

        $booking = $this->booking53StyleWithFareChangeGate(array_merge(
            $this->approvalMetaForBooking(),
            [
                'normalized_offer_snapshot' => $snapshot,
                'validated_offer_snapshot' => $snapshot,
                'last_revalidated_at' => $revalidatedAt,
                'selected_offer_created_at' => $revalidatedAt,
                'certified_route_selection' => [
                    'route_status' => SabreCertifiedRouteSelector::STATUS_CONTROLLED_CERTIFIED,
                    'endpoint_path' => SabreCertifiedRouteSelector::ENDPOINT_PASSENGER_RECORDS_V24_CREATE,
                    'payload_style' => 'iati_like_cpnr_v2_4_gds',
                ],
                SabrePassengerRecordsApplicationResultDigest::META_DIGEST_KEY => [
                    'status' => 'incomplete_no_locator',
                    'application_status' => 'Incomplete',
                    'errors' => [
                        ['code' => 'ERR.SP.PROVIDER_ERROR', 'message' => 'Unable to perform air booking step'],
                    ],
                    'warnings' => [
                        ['code' => 'WARN.SWS.HOST.ERROR_IN_RESPONSE', 'message' => 'EnhancedAirBookRQ: *NO FARES/RBD/CARRIER'],
                    ],
                ],
                SabreControlledFreshPnrContextApply::META_KEY => ['applied' => true, 'applied_at' => $revalidatedAt],
                SabreControlledStrongRevalidationLinkageApply::META_KEY => ['applied' => true, 'applied_at' => $revalidatedAt],
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

        $exit = Artisan::call('sabre:controlled-create-pnr', [
            '--booking' => (string) $booking->id,
            '--dry-run' => true,
        ]);

        $output = Artisan::output();
        $this->assertSame(1, $exit);
        $this->assertStringContainsString('eligible=false', $output);
        $this->assertStringContainsString('can_attempt_supplier_pnr=false', $output);
        $this->assertStringContainsString('live_supplier_call_allowed=false', $output);
        $this->assertStringContainsString('exact_create_confirmation_required=false', $output);
        $this->assertStringContainsString('post_final_retry_host_failure=true', $output);
        $this->assertStringContainsString('post_final_retry_host_failure_contained', $output);
        $this->assertStringContainsString('no_safe_retry_without_remediation=true', $output);
        $this->assertStringContainsString('final_retry_allowance_used', $output);
        $this->assertStringContainsString(
            'recommended_next_action=Staff review / Sabre host/PCC/QR/RBD/fare basis/brand qualifier investigation.',
            $output
        );
        $this->assertStringContainsString(
            'blocked_message=Post-final-retry host failure contained — no safe controlled PNR retry without staff remediation.',
            $output
        );
        $this->assertStringContainsString('classification=controlled_pnr_create_blocked_post_final_retry_host_failure', $output);
        $this->assertStringContainsString('live_supplier_call_attempted=false', $output);
        $this->assertStringContainsString('pnr_create_attempted=false', $output);
        $this->assertStringContainsString('ticketing_attempted=false', $output);
        $this->assertStringContainsString('cancellation_attempted=false', $output);
        Http::assertNothingSent();
    }

    public function test_confirmed_command_blocks_after_f9q_final_retry_host_failure_without_supplier_call(): void
    {
        config([
            'suppliers.sabre.booking_enabled' => true,
            'suppliers.sabre.booking_live_call_enabled' => true,
            'suppliers.sabre.cpnr_connecting_same_carrier_gds_enabled' => true,
            'suppliers.sabre.passenger_records_block_risky_itinerary_live' => false,
        ]);

        $booking = $this->f9rContainedBooking53Style();

        $realService = app(SabreBookingService::class);
        $serviceMock = \Mockery::mock($realService)->makePartial();
        $serviceMock->shouldNotReceive('createSupplierBooking');
        $this->instance(SabreBookingService::class, $serviceMock);

        $exit = Artisan::call('sabre:controlled-create-pnr', [
            '--booking' => (string) $booking->id,
            '--confirm' => 'CREATE-PNR-FOR-BOOKING-'.$booking->id,
        ]);

        $output = Artisan::output();
        $this->assertSame(1, $exit);
        $this->assertStringContainsString('classification=controlled_pnr_create_blocked_post_final_retry_host_failure', $output);
        $this->assertStringContainsString('eligible=false', $output);
        $this->assertStringContainsString('can_attempt_supplier_pnr=false', $output);
        $this->assertStringContainsString('live_supplier_call_allowed=false', $output);
        $this->assertStringContainsString('exact_create_confirmation_required=false', $output);
        $this->assertStringContainsString('reason_code=post_final_retry_host_failure_contained', $output);
        $this->assertStringContainsString('error_code=post_final_retry_host_failure_contained', $output);
        $this->assertStringContainsString(
            'error_message=Post-final-retry host failure contained — no safe controlled PNR retry without staff remediation.',
            $output
        );
        $this->assertStringContainsString('post_final_retry_host_failure=true', $output);
        $this->assertStringContainsString('no_safe_retry_without_remediation=true', $output);
        $this->assertStringContainsString('final_retry_allowance_used', $output);
        $this->assertStringContainsString('live_supplier_call_attempted=false', $output);
        $this->assertStringContainsString('pnr_create_attempted=false', $output);
        $this->assertStringContainsString('ticketing_attempted=false', $output);
        $this->assertStringContainsString('cancellation_attempted=false', $output);
        $this->assertStringContainsString('booking_created_in_supplier=false', $output);
        Http::assertNothingSent();
    }

    /**
     * @return array<string, mixed>
     */
    protected function f9rContainedBooking53Style(): Booking
    {
        $revalidatedAt = now()->subMinutes(5)->toIso8601String();
        $snapshot = [
            'supplier_provider' => 'sabre',
            'validating_carrier' => 'QR',
            'brand_code' => 'ECONVENIEN',
            'origin' => 'LHE',
            'destination' => 'JED',
            'segments' => [
                ['origin' => 'LHE', 'destination' => 'DOH', 'departure_at' => '2026-07-23T03:10:00', 'carrier' => 'QR', 'booking_class' => 'O', 'fare_basis_code' => 'OJPKP1RI'],
                ['origin' => 'DOH', 'destination' => 'JED', 'departure_at' => '2026-07-23T07:40:00', 'carrier' => 'QR', 'booking_class' => 'O', 'fare_basis_code' => 'OJPKP1RI'],
            ],
            'raw_payload' => [
                'sabre_shop_context' => [
                    'validating_carrier' => 'QR',
                    'fare_basis_codes' => ['OJPKP1RI', 'OJPKP1RI'],
                    'booking_classes_by_segment' => ['O', 'O'],
                ],
            ],
        ];

        return $this->booking53StyleWithFareChangeGate(array_merge(
            $this->approvalMetaForBooking(),
            [
                'normalized_offer_snapshot' => $snapshot,
                'validated_offer_snapshot' => $snapshot,
                'last_revalidated_at' => $revalidatedAt,
                'selected_offer_created_at' => $revalidatedAt,
                'certified_route_selection' => [
                    'route_status' => SabreCertifiedRouteSelector::STATUS_CONTROLLED_CERTIFIED,
                    'endpoint_path' => SabreCertifiedRouteSelector::ENDPOINT_PASSENGER_RECORDS_V24_CREATE,
                    'payload_style' => 'iati_like_cpnr_v2_4_gds',
                ],
                SabrePassengerRecordsApplicationResultDigest::META_DIGEST_KEY => [
                    'status' => 'incomplete_no_locator',
                    'application_status' => 'Incomplete',
                    'errors' => [
                        ['code' => 'ERR.SP.PROVIDER_ERROR', 'message' => 'Unable to perform air booking step'],
                    ],
                    'warnings' => [
                        ['code' => 'WARN.SWS.HOST.ERROR_IN_RESPONSE', 'message' => 'EnhancedAirBookRQ: *NO FARES/RBD/CARRIER'],
                    ],
                ],
                SabreControlledFreshPnrContextApply::META_KEY => ['applied' => true, 'applied_at' => $revalidatedAt],
                SabreControlledStrongRevalidationLinkageApply::META_KEY => ['applied' => true, 'applied_at' => $revalidatedAt],
                SabreControlledPnrRetryAllowanceGate::META_KEY => ['used' => true],
                SabreControlledPnrRetryAfterAirpriceVcFixAllowanceGate::META_KEY => ['used' => true],
                SabreControlledPnrRetryAfterAirpriceVcSchemaFixAllowanceGate::META_KEY => ['used' => true],
                SabreControlledFinalPnrRetryAllowanceGate::META_KEY => [
                    'allowed' => true,
                    'used' => true,
                    'create_attempted' => true,
                    'used_for' => SabreControlledFinalPnrRetryAllowanceGate::USED_FOR,
                    'final_controlled_create_failed' => true,
                    'post_final_retry_host_failure' => true,
                    'post_final_retry_host_failure_code' => 'NO_FARES_RBD_CARRIER',
                    'no_safe_retry_without_remediation' => true,
                ],
            ],
        ));
    }
}
