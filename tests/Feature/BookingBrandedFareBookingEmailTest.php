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
        $this->assertSame('Approx. PKR 90,062', $payload['payment']['estimated_selected_fare'] ?? null);
        $this->assertStringContainsString('economy', $html);
        $this->assertStringContainsString('VOWFL/V', $html);
        $this->assertStringContainsString(FlightOfferDisplayPresenter::SELECTED_FARE_VALIDATION_NOTE, $html);
        $this->assertStringContainsString(FlightOfferDisplayPresenter::SELECTED_FARE_PAYABLE_DISCLAIMER, $html);
        $this->assertStringContainsString('Selected fare (needs confirmation)', $html);
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
        $this->assertSame('Awaiting fare validation', $payload['payment']['final_payable_status'] ?? null);
        $this->assertStringContainsString('Awaiting fare validation', $html);
        $this->assertStringNotContainsString('Pending validation', $html);
        $this->assertStringNotContainsString('>Balance due</td>', $html);
    }

    #[Test]
    public function validated_branded_booking_email_shows_authoritative_final_payable_not_pending_validation(): void
    {
        $booking = $this->bookingWithFareContext(branded: true, validated: true);
        $payload = app(BookingEmailPayloadFactory::class)->adminNewBookingAlert($booking);
        $html = $this->renderUniversalNotificationHtml($payload);

        $this->assertSame('Validated', $payload['payment']['fare_validation_status'] ?? null);
        $this->assertStringContainsString('73,241', (string) ($payload['payment']['final_payable_status'] ?? ''));
        $this->assertStringNotContainsString('Pending validation', $html);
        $this->assertStringNotContainsString('Awaiting fare validation', $html);
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

    protected function bookingWithFareContext(bool $branded, bool $validated = false): Booking
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
                'displayed_price' => $validated ? 73241 : 90062,
                'displayed_currency' => 'PKR',
                'price_display' => $validated ? 'PKR 73,241' : 'Approx. PKR 90,062',
                'price_is_approximate' => ! $validated,
                'authoritative_after_revalidation' => $validated,
                'baggage_summary' => '30 KG',
                'cabin' => 'economy',
                'booking_class' => 'V',
                'fare_basis' => 'VOWFL/V',
            ];
            if ($validated) {
                $meta['offer_validation_status'] = 'valid';
                $meta['offer_validated_at'] = now()->toIso8601String();
                $meta['validated_offer_snapshot'] = ['segments' => []];
            }
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
            'base_fare' => $validated ? 65000 : 70000,
            'taxes' => $validated ? 8241 : 10190,
            'fees' => 0,
            'markup' => 0,
            'discount' => 0,
            'total' => $validated ? 73241 : 80190,
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
