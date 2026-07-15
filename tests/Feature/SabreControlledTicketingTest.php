<?php

namespace Tests\Feature;

use App\Models\BookingTicket;
use Database\Seeders\OtaFoundationSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Http;
use Tests\Support\Bookings\ControlledPnrContextTestFixtures;
use Tests\TestCase;

class SabreControlledTicketingTest extends TestCase
{
    use ControlledPnrContextTestFixtures;
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(OtaFoundationSeeder::class);
        Config::set('suppliers.sabre.ticketing_enabled', false);
        Http::fake();
    }

    public function test_booking53_controlled_create_still_blocks_ticketing(): void
    {
        $booking = $this->booking53Style();
        Artisan::call('sabre:controlled-create-pnr', [
            '--booking' => (string) $booking->id,
            '--dry-run' => true,
        ]);
        $output = Artisan::output();
        $this->assertStringContainsString('ticketing_attempted=false', $output);
        Http::assertNothingSent();
    }

    public function test_duplicate_ticketing_prevention(): void
    {
        $booking = $this->booking53Style(['pnr' => 'B53PNR', 'payment_status' => 'paid']);
        BookingTicket::query()->create([
            'agency_id' => $booking->agency_id,
            'booking_id' => $booking->id,
            'ticket_number' => '176-1111111111',
            'pnr' => 'B53PNR',
            'provider' => 'sabre',
            'status' => 'issued',
            'issued_at' => now(),
        ]);

        Artisan::call('sabre:gds-ticketing-readiness', [
            '--booking' => (string) $booking->id,
            '--json' => true,
        ]);

        $this->assertStringContainsString('duplicate_ticketing_guard', Artisan::output());
    }
}
