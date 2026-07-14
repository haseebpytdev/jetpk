<?php

namespace Tests\Feature;

use App\Enums\BookingStatus;
use App\Mail\BookingRequestReceivedMail;
use App\Mail\BookingUniversalNotification;
use App\Models\Agency;
use App\Models\AgencyCommunicationSetting;
use App\Models\Booking;
use App\Models\BookingContact;
use App\Models\BookingFareBreakdown;
use App\Services\Communication\BookingCommunicationService;
use App\Services\Communication\BookingEmailPayloadFactory;
use App\Support\FlightSearch\FlightOfferDisplayPresenter;
use Database\Seeders\OtaFoundationSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class BookingBrandedFareBookingEmailTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    public function branded_booking_request_email_shows_selected_fare_family_not_base_total(): void
    {
        $booking = $this->bookingWithFareContext(branded: true);
        $payload = app(BookingEmailPayloadFactory::class)->bookingReceived($booking);
        $html = $this->renderUniversalNotificationHtml($payload);

        $this->assertStringContainsString('FREEDOM', $html);
        $this->assertStringContainsString('FL', $html);
        $this->assertStringContainsString('30 KG', $html);
        $this->assertStringContainsString('Approx. PKR 90,062', $html);
        $this->assertStringContainsString('economy', $html);
        $this->assertStringContainsString('VOWFL/V', $html);
        $this->assertStringContainsString(FlightOfferDisplayPresenter::SELECTED_FARE_VALIDATION_NOTE, $html);
        $this->assertStringContainsString(FlightOfferDisplayPresenter::SELECTED_FARE_PAYABLE_DISCLAIMER, $html);
        $this->assertStringContainsString('Estimated selected fare', $html);
        $this->assertStringContainsString('Estimated amount due', $html);
        $this->assertStringContainsString('Base fare (search)', $html);
        $this->assertStringNotContainsString('>Total</td>', $html);
        $this->assertStringNotContainsString('>Balance due</td>', $html);
        $this->assertArrayNotHasKey('total', $payload['payment']);
        $this->assertArrayNotHasKey('balance_due', $payload['payment']);
        $this->assertSame('Approx. PKR 90,062', $payload['payment']['estimated_selected_fare'] ?? null);
        $this->assertSame('Approx. PKR 90,062', $payload['payment']['estimated_amount_due'] ?? null);
    }

    #[Test]
    public function branded_admin_booking_alert_shows_estimated_fare_and_pending_validation_not_balance_due(): void
    {
        $booking = $this->bookingWithFareContext(branded: true);
        $payload = app(BookingEmailPayloadFactory::class)->adminNewBookingAlert($booking);
        $html = $this->renderUniversalNotificationHtml($payload);

        $this->assertSame('Approx. PKR 90,062', $payload['payment']['estimated_selected_fare'] ?? null);
        $this->assertSame('80,190.00 PKR', $payload['payment']['base_fare_total'] ?? null);
        $this->assertArrayNotHasKey('balance_due', $payload['payment']);
        $this->assertStringContainsString('Estimated amount due', $html);
        $this->assertStringContainsString('Final payable', $html);
        $this->assertStringContainsString('Pending validation', $html);
        $this->assertStringNotContainsString('>Balance due</td>', $html);
    }

    #[Test]
    public function base_booking_request_email_keeps_fare_breakdown_total(): void
    {
        $booking = $this->bookingWithFareContext(branded: false);
        $payload = app(BookingEmailPayloadFactory::class)->bookingReceived($booking);
        $html = $this->renderUniversalNotificationHtml($payload);

        $this->assertStringContainsString('80,190.00 PKR', $html);
        $this->assertStringContainsString('Balance due', $html);
        $this->assertStringNotContainsString('Selected Fare Family', $html);
        $this->assertStringNotContainsString('Estimated selected fare', $html);
        $this->assertArrayNotHasKey('estimated_selected_fare', $payload['payment']);
        $this->assertSame('80,190.00 PKR', $payload['payment']['total'] ?? null);
    }

    #[Test]
    public function legacy_booking_request_received_mail_shows_branded_estimate_when_present(): void
    {
        $this->seed(OtaFoundationSeeder::class);
        $booking = $this->bookingWithFareContext(branded: true);

        $mail = new BookingRequestReceivedMail($booking);

        $this->assertStringContainsString('FREEDOM (FL)', $mail->htmlBody);
        $this->assertStringContainsString('Approx. PKR 90,062', $mail->htmlBody);
        $this->assertStringContainsString('30 KG', $mail->htmlBody);
        $this->assertStringNotContainsString('80,190.00', $mail->htmlBody);
    }

    #[Test]
    public function send_booking_request_received_queues_universal_email_with_branded_fare(): void
    {
        Mail::fake();
        config(['mail.default' => 'array']);
        $this->seed(OtaFoundationSeeder::class);
        $this->enableOutboundEmail();
        $booking = $this->bookingWithFareContext(branded: true);

        app(BookingCommunicationService::class)->sendBookingRequestReceived($booking);

        Mail::assertSent(BookingUniversalNotification::class, function (BookingUniversalNotification $mail): bool {
            $payload = $mail->payload;
            $html = $this->renderUniversalNotificationHtml($payload);

            return ($payload['payment']['estimated_selected_fare'] ?? null) === 'Approx. PKR 90,062'
                && ($payload['payment']['estimated_amount_due'] ?? null) === 'Approx. PKR 90,062'
                && ! array_key_exists('balance_due', $payload['payment'] ?? [])
                && str_contains($html, 'FREEDOM (FL)')
                && str_contains($html, '30 KG')
                && str_contains($html, 'Estimated amount due')
                && ! str_contains($html, '>Total</td>')
                && ! str_contains($html, '>Balance due</td>');
        });
    }

    protected function bookingWithFareContext(bool $branded): Booking
    {
        if (! $this->app->runningUnitTests() || Agency::query()->where('slug', config('ota.default_agency_slug'))->doesntExist()) {
            $this->seed(OtaFoundationSeeder::class);
        }
        $agency = Agency::query()->where('slug', config('ota.default_agency_slug'))->firstOrFail();

        $meta = [];
        if ($branded) {
            $meta['selected_fare_family_option'] = [
                'name' => 'FREEDOM',
                'brand_code' => 'FL',
                'displayed_price' => 90062,
                'displayed_currency' => 'PKR',
                'price_display' => 'Approx. PKR 90,062',
                'price_is_approximate' => true,
                'baggage_summary' => '30 KG',
                'cabin' => 'economy',
                'booking_class' => 'V',
                'fare_basis' => 'VOWFL/V',
            ];
        }

        $booking = Booking::factory()->create([
            'agency_id' => $agency->id,
            'booking_reference' => $branded ? 'BF6-EMAIL-BRANDED' : 'BF6-EMAIL-BASE',
            'route' => 'LHE → DXB',
            'travel_date' => now()->addDays(12),
            'status' => BookingStatus::Pending,
            'currency' => 'PKR',
            'meta' => $meta,
        ]);

        BookingContact::query()->create([
            'booking_id' => $booking->id,
            'email' => 'branded-fare-email@example.test',
            'phone' => '+92000000099',
            'meta' => ['name' => 'Branded Fare Traveler'],
        ]);

        BookingFareBreakdown::query()->create([
            'booking_id' => $booking->id,
            'base_fare' => 70000,
            'taxes' => 10190,
            'fees' => 0,
            'markup' => 0,
            'discount' => 0,
            'total' => 80190,
            'currency' => 'PKR',
            'breakdown' => [],
        ]);

        return $booking->fresh(['agency.agencySetting', 'contact', 'passengers', 'customer', 'fareBreakdown']);
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    protected function renderUniversalNotificationHtml(array $payload): string
    {
        return view('emails.booking.universal-notification', ['payload' => $payload])->render();
    }

    protected function enableOutboundEmail(): void
    {
        $agency = Agency::query()->where('slug', config('ota.default_agency_slug'))->firstOrFail();
        AgencyCommunicationSetting::query()->updateOrCreate(
            ['agency_id' => $agency->id],
            ['email_enabled' => true, 'smtp_enabled' => false],
        );
    }
}
