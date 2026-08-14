<?php

namespace Tests\Feature\Communication;

use App\Enums\AccountType;
use App\Models\Agency;
use App\Models\Booking;
use App\Models\User;
use App\Services\Communication\BookingEmailPayloadFactory;
use App\Support\Emails\EmailBaseVariables;
use Database\Seeders\OtaFoundationSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class BookingEmailCustomerCtaTest extends TestCase
{
    use RefreshDatabase;

    public function test_customer_view_booking_cta_uses_booking_reference_not_numeric_id(): void
    {
        $this->seed(OtaFoundationSeeder::class);
        $agency = Agency::query()->where('slug', 'asif-travels')->firstOrFail();
        $customer = User::factory()->create([
            'account_type' => AccountType::Customer,
            'current_agency_id' => $agency->id,
        ]);
        $booking = Booking::factory()->create([
            'agency_id' => $agency->id,
            'customer_id' => $customer->id,
            'booking_reference' => 'JP-CTA-REF-9001',
        ]);

        $url = EmailBaseVariables::customerBookingShowUrl($booking);
        $this->assertNotNull($url);
        $this->assertStringContainsString('/customer/bookings/JP-CTA-REF-9001', $url);
        $this->assertStringNotContainsString('/customer/bookings/'.$booking->id, $url);

        $payload = app(BookingEmailPayloadFactory::class)->bookingReceived($booking);
        $ctaUrl = $payload['cta'][0]['url'] ?? '';
        $this->assertSame($url, $ctaUrl);
        $this->assertSame(route('customer.bookings.show', ['booking' => 'JP-CTA-REF-9001'], false), parse_url($url, PHP_URL_PATH));
    }
}
