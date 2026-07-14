<?php

namespace Tests\Unit\Support\Bookings;

use App\Models\Booking;
use App\Support\Bookings\SabreBookingValidationManualRequestPolicy;
use Tests\TestCase;

class SabreBookingValidationManualRequestPolicyTest extends TestCase
{
    public function test_allows_non_blocking_when_manual_request_and_flags_off(): void
    {
        config([
            'suppliers.sabre.verified_multiseg_auto_pnr_enabled' => false,
            'suppliers.sabre.cpnr_connecting_same_carrier_public_checkout_enabled' => false,
            'suppliers.sabre.ticketing_enabled' => false,
        ]);

        $booking = new Booking([
            'meta' => [
                'confirmation_method' => 'pay_later_booking_request',
            ],
        ]);

        $this->assertTrue(SabreBookingValidationManualRequestPolicy::allowsNonBlockingValidationFailure($booking, [
            'success' => false,
            'status' => 'failed',
            'error_code' => 'sabre_booking_validation_failed',
            'live_call_attempted' => true,
            'pnr' => null,
        ]));
    }

    public function test_blocks_when_public_auto_pnr_enabled(): void
    {
        config([
            'suppliers.sabre.verified_multiseg_auto_pnr_enabled' => true,
            'suppliers.sabre.cpnr_connecting_same_carrier_public_checkout_enabled' => false,
            'suppliers.sabre.ticketing_enabled' => false,
        ]);

        $booking = new Booking([
            'meta' => [
                'confirmation_method' => 'pay_later_booking_request',
            ],
        ]);

        $this->assertFalse(SabreBookingValidationManualRequestPolicy::allowsNonBlockingValidationFailure($booking, [
            'success' => false,
            'error_code' => 'sabre_booking_validation_failed',
            'live_call_attempted' => true,
        ]));
    }

    public function test_blocks_when_confirmation_method_is_not_pay_later_booking_request(): void
    {
        config([
            'suppliers.sabre.verified_multiseg_auto_pnr_enabled' => false,
            'suppliers.sabre.ticketing_enabled' => false,
        ]);

        $booking = new Booking([
            'meta' => [
                'confirmation_method' => 'online_card',
            ],
        ]);

        $this->assertFalse(SabreBookingValidationManualRequestPolicy::allowsNonBlockingValidationFailure($booking, [
            'success' => false,
            'error_code' => 'sabre_booking_validation_failed',
        ]));
    }

    public function test_customer_safe_message_strips_internal_sabre_pointer_text(): void
    {
        $raw = 'Sabre booking validation failed: pointer: /CreatePassengerNameRecordRQ/AirPrice/0/message: object instance has properties';
        $safe = SabreBookingValidationManualRequestPolicy::customerSafeMessage($raw);

        $this->assertStringNotContainsString('CreatePassengerNameRecordRQ', $safe);
        $this->assertStringNotContainsString('/AirPrice/0/message', $safe);
    }
}
