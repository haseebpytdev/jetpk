<?php

namespace Tests\Unit\Support\Emails;

use App\Enums\BookingPaymentMethod;
use App\Enums\BookingPaymentStatus;
use App\Enums\BookingStatus;
use App\Models\Agency;
use App\Models\Booking;
use App\Models\BookingPayment;
use App\Support\Emails\CustomerFacingEmailRenderer;
use App\Support\Emails\EmailPlaceholderFallbacks;
use App\Support\Emails\ModernEmailLayout;
use Database\Seeders\OtaFoundationSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class CustomerFacingEmailRendererTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    public function booking_request_email_contains_summary_card_sections(): void
    {
        Mail::fake();
        $this->seed(OtaFoundationSeeder::class);
        $agency = Agency::query()->where('slug', config('ota.default_agency_slug'))->firstOrFail();
        $booking = Booking::factory()->for($agency)->create([
            'route' => 'LHE — DXB',
            'travel_date' => now()->addDays(10),
            'status' => BookingStatus::Pending,
            'payment_status' => 'unpaid',
        ]);

        $rendered = app(CustomerFacingEmailRenderer::class)->bookingRequestReceived($booking);

        $this->assertStringContainsString('Booking summary', $rendered->html);
        $this->assertStringContainsString('Booking reference', $rendered->html);
        $this->assertStringContainsString('Passenger', $rendered->html);
        $this->assertStringContainsString('Next steps', $rendered->html);
        $this->assertStringContainsString('Booking request received', $rendered->html);
        $this->assertStringNotContainsString('{{', $rendered->html);
        Mail::assertNothingSent();
    }

    #[Test]
    public function ticket_issued_email_does_not_expose_supplier_internals(): void
    {
        Mail::fake();
        $this->seed(OtaFoundationSeeder::class);
        $agency = Agency::query()->where('slug', config('ota.default_agency_slug'))->firstOrFail();
        $booking = Booking::factory()->for($agency)->create([
            'pnr' => null,
            'status' => BookingStatus::Ticketed,
            'meta' => [
                'supplier_status' => 'CPNR pending',
                'supplier_response' => 'raw gds payload',
            ],
        ]);

        $rendered = app(CustomerFacingEmailRenderer::class)->ticketIssued($booking);

        $this->assertStringContainsString(EmailPlaceholderFallbacks::fallbackFor('pnr'), $rendered->html);
        $this->assertStringNotContainsString('supplier_status', strtolower($rendered->html));
        $this->assertStringNotContainsString('CPNR', $rendered->html);
        $this->assertStringNotContainsString('GDS', $rendered->html);
        $this->assertStringNotContainsString('supplier_response', strtolower($rendered->html));
        $this->assertStringNotContainsString('{{', $rendered->html);
        Mail::assertNothingSent();
    }

    #[Test]
    public function missing_pnr_displays_safe_customer_fallback(): void
    {
        $fallback = ModernEmailLayout::customerPnrDisplay(null);

        $this->assertSame(EmailPlaceholderFallbacks::fallbackFor('pnr'), $fallback);
        $this->assertSame('Not assigned yet', $fallback);
    }

    #[Test]
    public function payment_verified_email_includes_status_banner_and_summary(): void
    {
        Mail::fake();
        $this->seed(OtaFoundationSeeder::class);
        $agency = Agency::query()->where('slug', config('ota.default_agency_slug'))->firstOrFail();
        $booking = Booking::factory()->for($agency)->create();
        $payment = BookingPayment::query()->create([
            'agency_id' => $agency->id,
            'booking_id' => $booking->id,
            'amount' => 45000,
            'currency' => 'PKR',
            'method' => BookingPaymentMethod::BankTransfer,
            'status' => BookingPaymentStatus::Verified,
        ]);

        $rendered = app(CustomerFacingEmailRenderer::class)->paymentVerified($payment);

        $this->assertStringContainsString('Payment verified', $rendered->html);
        $this->assertStringContainsString('Booking reference', $rendered->html);
        $this->assertStringContainsString('Next steps', $rendered->html);
        $this->assertStringNotContainsString('{{', $rendered->html);
        Mail::assertNothingSent();
    }
}
