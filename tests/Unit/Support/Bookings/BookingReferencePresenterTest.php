<?php

namespace Tests\Unit\Support\Bookings;

use App\Models\Agency;
use App\Models\Agent;
use App\Models\Booking;
use App\Support\Agencies\AgencyPrefixService;
use App\Support\Bookings\BookingReferencePresenter;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class BookingReferencePresenterTest extends TestCase
{
    use RefreshDatabase;

    public function test_customer_booking_reference_returns_stored_value_unchanged(): void
    {
        $agency = Agency::factory()->create(['name' => 'Premier Routes']);
        AgencyPrefixService::savePrefix($agency, 'PR');

        $booking = Booking::factory()->create([
            'agency_id' => $agency->id,
            'agent_id' => null,
            'source_channel' => 'public_guest',
            'booking_reference' => 'GXJDHD8K',
        ]);

        $this->assertSame('GXJDHD8K', BookingReferencePresenter::forPortal($booking));
    }

    public function test_agent_booking_reference_returns_stored_value_unchanged(): void
    {
        $agency = Agency::factory()->create(['name' => 'Premier Routes']);
        AgencyPrefixService::savePrefix($agency, 'PR');
        $agent = Agent::factory()->create(['agency_id' => $agency->id]);

        $booking = Booking::factory()->create([
            'agency_id' => $agency->id,
            'agent_id' => $agent->id,
            'source_channel' => 'agent_portal',
            'booking_reference' => 'GXJDHD8K',
        ]);

        $this->assertSame('GXJDHD8K', BookingReferencePresenter::forPortal($booking));
    }

    public function test_legacy_prefixed_stored_reference_is_not_transformed(): void
    {
        $agency = Agency::factory()->create(['name' => 'Asif Travels']);

        $booking = Booking::factory()->create([
            'agency_id' => $agency->id,
            'source_channel' => 'public_guest',
            'booking_reference' => 'OTA-ABCDEFGH',
        ]);

        $this->assertSame('OTA-ABCDEFGH', BookingReferencePresenter::forPortal($booking));
    }
}
