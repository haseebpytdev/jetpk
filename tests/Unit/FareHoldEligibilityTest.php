<?php

namespace Tests\Unit;

use App\Models\Booking;
use App\Models\User;
use App\Services\Bookings\FareHoldService;
use Tests\TestCase;

class FareHoldEligibilityTest extends TestCase
{
    public function test_requires_instant_payment_disables_supplier_hold(): void
    {
        $svc = app(FareHoldService::class);
        $offer = [
            'raw_payload' => [
                'payment_requirements' => ['requires_instant_payment' => true],
            ],
        ];

        $this->assertFalse($svc->canSupplierHoldOffer($offer));
    }

    public function test_requires_instant_payment_skips_hold_creator_even_with_actor(): void
    {
        $svc = app(FareHoldService::class);
        $booking = new Booking;
        $booking->meta = [
            'flight_offer_snapshot' => [
                'raw_payload' => [
                    'payment_requirements' => ['requires_instant_payment' => true],
                ],
            ],
        ];
        $actor = User::factory()->make();

        $out = $svc->createHoldIfSupported($booking, $actor, fn () => throw new \RuntimeException('hold should not run'));

        $this->assertSame('not_supported', $out['status']);
    }

    public function test_hold_not_attempted_without_actor_when_hold_supported(): void
    {
        $svc = app(FareHoldService::class);
        $booking = new Booking;
        $booking->meta = [
            'flight_offer_snapshot' => [
                'raw_payload' => [
                    'payment_requirements' => ['requires_instant_payment' => false],
                ],
            ],
        ];

        $out = $svc->createHoldIfSupported($booking, null, fn () => throw new \RuntimeException('hold should not run'));

        $this->assertSame('hold_pending_passenger_details', $out['status']);
    }
}
