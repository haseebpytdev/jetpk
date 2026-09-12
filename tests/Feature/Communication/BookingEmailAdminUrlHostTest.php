<?php

namespace Tests\Feature\Communication;

use App\Models\Agency;
use App\Models\Booking;
use App\Services\Communication\BookingEmailPayloadFactory;
use Database\Seeders\OtaFoundationSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class BookingEmailAdminUrlHostTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    public function admin_booking_email_urls_ignore_internal_request_host(): void
    {
        config(['app.url' => 'https://jetpakistan.pk']);
        $this->seed(OtaFoundationSeeder::class);
        $agency = Agency::query()->where('slug', config('ota.default_agency_slug'))->firstOrFail();
        $booking = Booking::factory()->create([
            'agency_id' => $agency->id,
            'booking_reference' => 'URLHOST-01',
        ]);

        $this->withServerVariables([
            'HTTP_HOST' => '127.0.0.1:8088',
            'SERVER_NAME' => '127.0.0.1',
            'SERVER_PORT' => '8088',
            'HTTP_X_FORWARDED_HOST' => '127.0.0.1:8088',
            'HTTP_X_FORWARDED_PROTO' => 'http',
        ]);

        $payload = app(BookingEmailPayloadFactory::class)->adminNewBookingAlert($booking->fresh(['agency.agencySetting', 'contact', 'passengers', 'customer', 'fareBreakdown']));
        $html = view('emails.booking.universal-notification', ['payload' => $payload])->render();
        $adminUrl = (string) ($payload['admin']['admin_booking_url'] ?? '');

        $this->assertStringStartsWith('https://jetpakistan.pk/', $adminUrl);
        $this->assertStringNotContainsString('127.0.0.1', $adminUrl);
        $this->assertStringNotContainsString('localhost', $html);
        $this->assertStringNotContainsString(':8088', $html);
        $this->assertStringNotContainsString('/index.php/', $html);
    }
}
