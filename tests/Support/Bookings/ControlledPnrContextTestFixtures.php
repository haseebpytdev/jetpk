<?php

namespace Tests\Support\Bookings;

use App\Enums\BookingStatus;
use App\Models\Agency;
use App\Models\Booking;
use App\Models\BookingContact;
use App\Models\BookingPassenger;
use App\Models\SupplierConnection;
use App\Support\Bookings\SabreCertifiedRouteSelector;
use App\Support\Bookings\SabreControlledPnrFareChangeAcceptance;
use App\Support\Bookings\SabreControlledPnrManualReviewApproval;
use App\Support\Bookings\SabreSafeRefreshContext;

trait ControlledPnrContextTestFixtures
{
    /**
     * Booking 53/54-style controlled-certified context (legacy revalidation success, no strict linkage).
     *
     * @param  array<string, mixed>  $metaOverrides
     */
    protected function booking53Style(array $metaOverrides = []): Booking
    {
        $agency = Agency::query()->where('slug', 'asif-travels')->firstOrFail();
        $conn = SupplierConnection::query()
            ->where('agency_id', $agency->id)
            ->where('provider', 'sabre')
            ->firstOrFail();

        $snapshot = [
            'supplier_provider' => 'sabre',
            'supplier_offer_id' => 'offer-f9b-controlled-context-offer',
            'validating_carrier' => 'GF',
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
                    'destination' => 'BAH',
                    'departure_at' => '2026-07-29T08:00:00',
                    'arrival_at' => '2026-07-29T10:30:00',
                    'carrier' => 'GF',
                    'flight_number' => '765',
                    'booking_class' => 'W',
                    'fare_basis_code' => 'WDLIT3PK',
                ],
                [
                    'origin' => 'BAH',
                    'destination' => 'JED',
                    'departure_at' => '2026-07-29T14:00:00',
                    'arrival_at' => '2026-07-29T16:00:00',
                    'carrier' => 'GF',
                    'flight_number' => '182',
                    'booking_class' => 'W',
                    'fare_basis_code' => 'WDLIT3PK',
                ],
            ],
            'raw_payload' => [
                'sabre_shop_context' => [
                    'pricing_information_ref' => 'pi-f9c-controlled',
                    'offer_ref' => 'offer-f9c-controlled',
                    'itinerary_ref' => 'itin-f9c-controlled',
                    'validating_carrier' => 'GF',
                    'fare_basis_codes' => ['WDLIT3PK', 'WDLIT3PK'],
                ],
            ],
        ];

        $criteria = [
            'trip_type' => 'one_way',
            'origin' => 'LHE',
            'destination' => 'JED',
            'depart_date' => '2026-07-29',
            'adults' => 1,
        ];

        $safeRefresh = app(SabreSafeRefreshContext::class)->buildFromCheckout($snapshot, $criteria, [
            'supplier_connection_id' => $conn->id,
            'checkout_search_id' => 'f9b-controlled-context-search',
            'checkout_offer_id' => 'f9b-controlled-context-offer',
            'supplier_total' => 100.0,
            'supplier_currency' => 'PKR',
        ]);

        $revalidatedAt = now()->subMinutes(5)->toIso8601String();

        $meta = array_merge([
            'supplier_provider' => 'sabre',
            'supplier_connection_id' => $conn->id,
            'booking_method' => 'pay_later_booking_request',
            'confirmation_method' => 'pay_later_booking_request',
            'search_criteria' => $criteria,
            'revalidation_status' => 'success',
            'selected_offer_revalidation_status' => 'success',
            'last_revalidated_at' => $revalidatedAt,
            'selected_offer_last_revalidated_at' => $revalidatedAt,
            'normalized_offer_snapshot' => $snapshot,
            'validated_offer_snapshot' => $snapshot,
            'pricing_snapshot' => [
                'currency' => 'PKR',
                'pricing_currency' => 'PKR',
                'supplier_currency' => 'USD',
                'supplier_total' => 100.0,
                'base_fare' => 40.0,
                'taxes' => 60.0,
                'customer_total' => 120.0,
                'final_total' => 120.0,
            ],
            'sabre_booking_context' => [
                'ready_for_booking_payload' => true,
                'has_revalidation_linkage' => false,
                'validating_carrier' => 'GF',
            ],
            'certified_route_selection' => [
                'category' => SabreCertifiedRouteSelector::CATEGORY_ONE_WAY_CONNECTING_SAME_CARRIER_GDS,
                'route_status' => SabreCertifiedRouteSelector::STATUS_CONTROLLED_CERTIFIED,
                'endpoint_path' => SabreCertifiedRouteSelector::ENDPOINT_PASSENGER_RECORDS_V24_CREATE,
                'payload_style' => 'iati_like_cpnr_v2_4_gds',
            ],
            'sabre_checkout_outcome' => [
                'status' => 'needs_review',
                'live_call_attempted' => false,
                'error_code' => SabreCertifiedRouteSelector::ERROR_CODE_PENDING,
            ],
            SabreSafeRefreshContext::META_KEY => $safeRefresh,
        ], $metaOverrides);

        $booking = Booking::factory()->create([
            'agency_id' => $agency->id,
            'status' => BookingStatus::Pending,
            'payment_status' => 'unpaid',
            'confirmation_method' => 'pay_later_booking_request',
            'meta' => $meta,
        ]);

        BookingPassenger::factory()->for($booking)->create([
            'passenger_index' => 0,
            'is_lead_passenger' => true,
            'first_name' => 'Test',
            'last_name' => 'Passenger',
            'date_of_birth' => now()->subYears(30)->toDateString(),
            'gender' => 'male',
            'passenger_type' => 'adult',
            'passport_number' => 'AB1234567',
            'passport_expiry_date' => now()->addYears(2)->toDateString(),
        ]);

        BookingContact::query()->create([
            'booking_id' => $booking->id,
            'email' => 'guest@example.test',
            'phone' => '+923001234567',
        ]);

        return $booking->fresh(['passengers', 'contact', 'supplierBookings', 'tickets']);
    }

    /**
     * Booking 53-style with F9E fare-change gate active (F9C approval still required separately).
     *
     * @param  array<string, mixed>  $metaOverrides
     */
    protected function booking53StyleWithFareChangeGate(array $metaOverrides = []): Booking
    {
        return $this->booking53Style(array_merge([
            'offer_refresh_status' => 'refreshed',
            'offer_refresh_reason' => 'rbd_or_price_changed',
            'offer_refresh_refreshed_at' => now()->subHour()->toIso8601String(),
            'offer_refresh_price_changed' => true,
            'offer_refresh_requires_customer_confirmation' => true,
            'offer_refresh_accepted' => false,
            'supplier_total' => 88415.63,
            'supplier_currency' => 'USD',
        ], $metaOverrides));
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

    /**
     * @return array<string, mixed>
     */
    protected function fareChangeAcceptanceMetaForBooking(Booking $booking): array
    {
        return [
            SabreControlledPnrFareChangeAcceptance::META_KEY => app(
                SabreControlledPnrFareChangeAcceptance::class
            )->buildAcceptanceRecord($booking, 'controlled_fare_retry', 'platform_ops'),
            'offer_refresh_accepted' => true,
            'offer_refresh_accepted_at' => now()->toIso8601String(),
            'offer_refresh_accepted_by' => 'platform_ops',
            'offer_refresh_acceptance_source' => 'artisan',
        ];
    }
}
