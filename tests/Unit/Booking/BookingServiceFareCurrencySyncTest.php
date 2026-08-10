<?php

namespace Tests\Unit\Booking;

use App\Models\Agency;
use App\Services\Booking\BookingService;
use Database\Seeders\OtaFoundationSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class BookingServiceFareCurrencySyncTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(OtaFoundationSeeder::class);
    }

    public function test_attach_fare_breakdown_syncs_booking_currency_from_offer_currency(): void
    {
        $agency = Agency::query()->firstOrFail();
        $service = app(BookingService::class);

        $booking = $service->createDraftBooking($agency);
        $this->assertSame('PKR', $booking->currency);

        $service->attachFareBreakdown($booking, [
            'base_fare' => 500,
            'taxes' => 50,
            'fees' => 0,
            'markup' => 0,
            'discount' => 0,
            'total' => 550,
            'currency' => 'USD',
        ]);

        $booking->refresh();
        $this->assertSame('USD', $booking->currency);
    }
}
