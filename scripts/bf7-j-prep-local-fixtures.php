<?php

/**
 * BF7-J-PREP: Bootstrap local read-only diagnostic fixtures (QR Booking-53-like + GF go/no-go).
 * Run: php scripts/bf7-j-prep-local-fixtures.php
 * Does not call Sabre HTTP. Safe for local/testing only.
 */

use App\Enums\BookingStatus;
use App\Enums\SupplierConnectionStatus;
use App\Enums\SupplierProvider;
use App\Models\Agency;
use App\Models\Booking;
use App\Models\BookingContact;
use App\Models\BookingPassenger;
use App\Models\SupplierConnection;
use App\Support\Bookings\SabreBrandedFarePublicAutoPnrEligibility;
use App\Support\Bookings\SabreSafeRefreshContext;
use Database\Seeders\OtaFoundationSeeder;
use Illuminate\Contracts\Console\Kernel;

require __DIR__.'/../vendor/autoload.php';
$app = require __DIR__.'/../bootstrap/app.php';
$app->make(Kernel::class)->bootstrap();

if (! in_array((string) config('app.env'), ['local', 'testing'], true)) {
    fwrite(STDERR, "Refusing to run outside local/testing.\n");
    exit(1);
}

$app->make(OtaFoundationSeeder::class)->run();

$agency = Agency::query()->where('slug', 'asif-travels')->firstOrFail();
$conn = SupplierConnection::query()
    ->where('agency_id', $agency->id)
    ->where('provider', SupplierProvider::Sabre->value)
    ->firstOrFail();
$conn->update([
    'is_active' => true,
    'status' => SupplierConnectionStatus::Active,
    'base_url' => 'https://api.cert.platform.sabre.com',
    'credentials' => [
        'client_id' => 'cid',
        'client_secret' => 'sec',
        'pcc' => 'TEST',
        'pseudo_city_code' => 'TEST',
        'target_city' => 'TEST',
    ],
]);

$qrMeta = [
    'supplier_provider' => SupplierProvider::Sabre->value,
    'supplier_connection_id' => $conn->id,
    'offer_validation_status' => 'valid',
    'confirmation_method' => 'pay_later_booking_request',
    'booking_method' => 'pay_later_booking_request',
    'fare_option_key' => 'qr-econvenien',
    'selected_fare_family_option' => [
        'brand_code' => 'ECONVENIEN',
        'brand_name' => 'Economy Convenience',
        'fare_option_key' => 'qr-econvenien',
        'booking_class' => 'N',
        'fare_basis' => 'NJR6R1RI',
    ],
    'search_criteria' => ['trip_type' => 'one_way'],
    'normalized_offer_snapshot' => [
        'supplier_provider' => SupplierProvider::Sabre->value,
        'validating_carrier' => 'QR',
        'segments' => [
            [
                'origin' => 'LHE',
                'destination' => 'DOH',
                'carrier' => 'QR',
                'flight_number' => '615',
                'booking_class' => 'N',
                'fare_basis_code' => 'NJR6R1RI',
                'departure_at' => '2026-08-15T03:25:00',
                'arrival_at' => '2026-08-15T05:10:00',
            ],
            [
                'origin' => 'DOH',
                'destination' => 'JED',
                'carrier' => 'QR',
                'flight_number' => '1188',
                'booking_class' => 'N',
                'fare_basis_code' => 'NJR6R1RI',
                'departure_at' => '2026-08-15T07:35:00',
                'arrival_at' => '2026-08-15T09:50:00',
            ],
        ],
    ],
    SabreBrandedFarePublicAutoPnrEligibility::META_KEY => [
        'eligible' => false,
        'reason_code' => 'auto_pnr_flag_disabled',
        'failed_conditions' => ['auto_pnr_flag_enabled', 'public_flag_enabled'],
        'selected_brand_code' => 'ECONVENIEN',
        'brand_shape' => 'object_content',
        'carrier_chain' => 'QR→QR',
        'payment_mode' => 'pay_later_booking_request',
        'ticketing_enabled' => false,
        'public_flag_enabled' => false,
        'auto_pnr_flag_enabled' => false,
        'live_supplier_call_attempted' => false,
        'evaluated_at' => '2026-06-15T19:00:00+00:00',
    ],
];

$qrBooking = Booking::factory()->create([
    'agency_id' => $agency->id,
    'status' => BookingStatus::Draft,
    'supplier' => SupplierProvider::Sabre->value,
    'confirmation_method' => 'pay_later_booking_request',
    'meta' => $qrMeta,
]);
$qrSnapshot = $qrMeta['normalized_offer_snapshot'];
$qrMetaFull = is_array($qrBooking->meta) ? $qrBooking->meta : [];
$qrMetaFull[SabreSafeRefreshContext::META_KEY] = app(SabreSafeRefreshContext::class)->buildFromCheckout($qrSnapshot, [
    'trip_type' => 'one_way',
    'origin' => 'LHE',
    'destination' => 'JED',
    'depart_date' => '2026-08-15',
    'adults' => 1,
], [
    'checkout_search_id' => 'bf7j-prep-qr-search',
    'checkout_offer_id' => 'bf7j-prep-qr-offer',
    'supplier_total' => 150000.0,
    'supplier_currency' => 'PKR',
]);
$qrBooking->forceFill(['meta' => $qrMetaFull])->save();
BookingPassenger::factory()->for($qrBooking)->create([
    'passenger_index' => 0,
    'is_lead_passenger' => true,
    'first_name' => 'Prep',
    'last_name' => 'QRGuest',
    'date_of_birth' => now()->subYears(30)->toDateString(),
    'gender' => 'male',
    'passenger_type' => 'adult',
]);
BookingContact::query()->create([
    'booking_id' => $qrBooking->id,
    'email' => 'prep-qr@example.test',
    'phone' => '+923001234567',
]);

$gfMeta = [
    'supplier_provider' => SupplierProvider::Sabre->value,
    'supplier_connection_id' => $conn->id,
    'create_payload_strategy_version' => 'E5A_SAFE_STRUCTURE_V1',
    'offer_validation_status' => 'valid',
    'confirmation_method' => 'pay_later_booking_request',
    'booking_method' => 'pay_later_booking_request',
    'fare_option_key' => 'fl-pi3',
    'selected_fare_family_option' => [
        'brand_code' => 'FL',
        'brand_name' => 'FREEDOM',
        'fare_option_key' => 'fl-pi3',
        'booking_class' => 'W',
        'fare_basis' => 'WDLIT3PK',
    ],
    'search_criteria' => ['trip_type' => 'one_way'],
    'normalized_offer_snapshot' => [
        'supplier_provider' => SupplierProvider::Sabre->value,
        'validating_carrier' => 'GF',
        'segments' => [
            [
                'origin' => 'LHE',
                'destination' => 'BAH',
                'carrier' => 'GF',
                'flight_number' => '767',
                'booking_class' => 'W',
                'fare_basis_code' => 'WDLIT3PK',
                'departure_at' => '2026-07-29T22:00:00',
                'arrival_at' => '2026-07-30T01:55:00',
            ],
            [
                'origin' => 'BAH',
                'destination' => 'JED',
                'carrier' => 'GF',
                'flight_number' => '171',
                'booking_class' => 'W',
                'fare_basis_code' => 'WDLIT3PK',
                'departure_at' => '2026-07-30T10:05:00',
                'arrival_at' => '2026-07-30T12:30:00',
            ],
        ],
    ],
];

$gfBooking = Booking::factory()->create([
    'agency_id' => $agency->id,
    'status' => BookingStatus::Draft,
    'supplier' => SupplierProvider::Sabre->value,
    'confirmation_method' => 'pay_later_booking_request',
    'meta' => $gfMeta,
]);
$gfSnapshot = $gfMeta['normalized_offer_snapshot'];
$gfMetaFull = is_array($gfBooking->meta) ? $gfBooking->meta : [];
$gfMetaFull[SabreSafeRefreshContext::META_KEY] = app(SabreSafeRefreshContext::class)->buildFromCheckout($gfSnapshot, [
    'trip_type' => 'one_way',
    'origin' => 'LHE',
    'destination' => 'JED',
    'depart_date' => '2026-07-29',
    'adults' => 1,
], [
    'checkout_search_id' => 'bf7j-prep-gf-search',
    'checkout_offer_id' => 'bf7j-prep-gf-offer',
    'supplier_total' => 100000.0,
    'supplier_currency' => 'PKR',
]);
$gfBooking->forceFill(['meta' => $gfMetaFull])->save();
BookingPassenger::factory()->for($gfBooking)->create([
    'passenger_index' => 0,
    'is_lead_passenger' => true,
    'first_name' => 'Prep',
    'last_name' => 'GFGuest',
    'date_of_birth' => now()->subYears(30)->toDateString(),
    'gender' => 'male',
    'passenger_type' => 'adult',
]);
BookingContact::query()->create([
    'booking_id' => $gfBooking->id,
    'email' => 'prep-gf@example.test',
    'phone' => '+923001234567',
]);

echo 'BF7J_PREP_QR_BOOKING_ID='.$qrBooking->id.PHP_EOL;
echo 'BF7J_PREP_GF_DRAFT_BOOKING_ID='.$gfBooking->id.PHP_EOL;
