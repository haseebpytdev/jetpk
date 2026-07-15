<?php

namespace Tests\Feature\Communication;

use App\Enums\AccountType;
use App\Enums\BookingPaymentMethod;
use App\Enums\BookingPaymentStatus;
use App\Enums\BookingStatus;
use App\Mail\BookingItineraryReadyMail;
use App\Mail\BookingRequestReceivedMail;
use App\Mail\BookingStatusChangedMail;
use App\Mail\GoogleCustomerWelcomeMail;
use App\Mail\OtaOperationalNotificationMail;
use App\Mail\PaymentRejectedMail;
use App\Mail\PaymentVerifiedMail;
use App\Mail\TicketIssuedMail;
use App\Models\Agency;
use App\Models\Booking;
use App\Models\BookingContact;
use App\Models\BookingPayment;
use App\Models\BookingTicket;
use App\Models\User;
use App\Support\Branding\CompanyEmailProfileResolver;
use Database\Seeders\OtaFoundationSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Storage;
use PHPUnit\Framework\Attributes\Test;
use Tests\Support\PlatformAdminTestHelpers;
use Tests\TestCase;

class CustomerFacingMailableModernLayoutTest extends TestCase
{
    use PlatformAdminTestHelpers;
    use RefreshDatabase;

    #[Test]
    public function booking_request_received_mail_renders_modern_layout_with_branding_and_reference(): void
    {
        $this->seed(OtaFoundationSeeder::class);
        $agency = $this->asifAgency();
        $profile = CompanyEmailProfileResolver::resolve($agency);
        $booking = $this->bookingWithContact($agency, 'ASIF-2026-000777');

        $mail = new BookingRequestReceivedMail($booking);

        $this->assertStringContainsString('<!DOCTYPE html>', $mail->htmlBody);
        $this->assertStringContainsString(e($profile->name), $mail->htmlBody);
        $this->assertStringContainsString('ASIF-2026-000777', $mail->htmlBody);
        $this->assertStringContainsString('Booking request received', $mail->htmlBody);
        $this->assertStringContainsString('ASIF-2026-000777', $mail->plainBody);
        $this->assertSame('Booking request received — ASIF-2026-000777', $mail->envelope()->subject);
    }

    #[Test]
    public function booking_status_changed_mail_shows_status_and_reference(): void
    {
        $this->seed(OtaFoundationSeeder::class);
        $agency = $this->asifAgency();
        $booking = $this->bookingWithContact($agency, 'ASIF-2026-000888');

        $mail = new BookingStatusChangedMail($booking, 'confirmed');

        $this->assertStringContainsString('<!DOCTYPE html>', $mail->htmlBody);
        $this->assertStringContainsString('Booking status updated', $mail->htmlBody);
        $this->assertStringContainsString('ASIF-2026-000888', $mail->htmlBody);
        $this->assertStringContainsString('Confirmed', $mail->htmlBody);
        $this->assertSame('Booking status update - ASIF-2026-000888', $mail->envelope()->subject);
    }

    #[Test]
    public function payment_verified_mail_shows_amount_and_reference_without_internal_meta(): void
    {
        $this->seed(OtaFoundationSeeder::class);
        $agency = $this->asifAgency();
        $booking = $this->bookingWithContact($agency, 'ASIF-2026-000999');
        $payment = BookingPayment::query()->create([
            'agency_id' => $agency->id,
            'booking_id' => $booking->id,
            'method' => BookingPaymentMethod::BankTransfer,
            'status' => BookingPaymentStatus::Verified,
            'amount' => 12500.50,
            'currency' => 'PKR',
            'notes' => 'Internal reviewer note',
            'meta' => ['sabre_payload' => 'SECRET-SABRE-DATA', 'rejection_reason' => 'hidden'],
        ]);

        $mail = new PaymentVerifiedMail($payment);

        $this->assertStringContainsString('<!DOCTYPE html>', $mail->htmlBody);
        $this->assertStringContainsString('Payment verified', $mail->htmlBody);
        $this->assertStringContainsString('ASIF-2026-000999', $mail->htmlBody);
        $this->assertStringContainsString('12,500.50', $mail->htmlBody);
        $this->assertStringNotContainsString('SECRET-SABRE-DATA', $mail->htmlBody);
        $this->assertStringNotContainsString('Internal reviewer note', $mail->htmlBody);
        $this->assertSame('Payment verified - ASIF-2026-000999', $mail->envelope()->subject);
    }

    #[Test]
    public function payment_rejected_mail_does_not_expose_internal_rejection_reason(): void
    {
        $this->seed(OtaFoundationSeeder::class);
        $agency = $this->asifAgency();
        $booking = $this->bookingWithContact($agency, 'ASIF-2026-001001');
        $payment = BookingPayment::query()->create([
            'agency_id' => $agency->id,
            'booking_id' => $booking->id,
            'method' => BookingPaymentMethod::BankTransfer,
            'status' => BookingPaymentStatus::Rejected,
            'amount' => 500,
            'currency' => 'PKR',
            'meta' => ['rejection_reason' => 'Invalid transfer screenshot'],
        ]);

        $mail = new PaymentRejectedMail($payment);

        $this->assertStringContainsString('could not be verified', strtolower($mail->htmlBody));
        $this->assertStringNotContainsString('Invalid transfer screenshot', $mail->htmlBody);
        $this->assertSame('Payment update - ASIF-2026-001001', $mail->envelope()->subject);
    }

    #[Test]
    public function ticket_issued_mail_shows_pnr_and_ticket_numbers(): void
    {
        $this->seed(OtaFoundationSeeder::class);
        $agency = $this->asifAgency();
        $booking = $this->bookingWithContact($agency, 'ASIF-2026-001002', pnr: 'ABC123');
        BookingTicket::query()->create([
            'agency_id' => $agency->id,
            'booking_id' => $booking->id,
            'ticket_number' => '176-1234567890',
            'pnr' => 'ABC123',
            'provider' => 'sabre',
            'airline_code' => 'EK',
            'status' => 'issued',
            'meta' => ['passenger_name' => 'Jane Doe'],
        ]);

        $mail = new TicketIssuedMail($booking->fresh(['tickets']));

        $this->assertStringContainsString('<!DOCTYPE html>', $mail->htmlBody);
        $this->assertStringContainsString('ABC123', $mail->htmlBody);
        $this->assertStringContainsString('176-1234567890', $mail->htmlBody);
        $this->assertSame('Ticket issued — ASIF-2026-001002', $mail->envelope()->subject);
    }

    #[Test]
    public function itinerary_ready_mail_preserves_pdf_attachment(): void
    {
        Storage::fake('local');
        $path = 'itineraries/test-itinerary.pdf';
        Storage::disk('local')->put($path, '%PDF-1.4 fake');

        $this->seed(OtaFoundationSeeder::class);
        $agency = $this->asifAgency();
        $booking = $this->bookingWithContact($agency, 'ASIF-2026-001003');

        $mail = new BookingItineraryReadyMail($booking, 'Please travel with ID.', $path);

        $this->assertStringContainsString('<!DOCTYPE html>', $mail->htmlBody);
        $this->assertStringContainsString('itinerary is ready', strtolower($mail->htmlBody));
        $this->assertStringContainsString('Please travel with ID.', $mail->htmlBody);
        $attachments = $mail->attachments();
        $this->assertCount(1, $attachments);
        $this->assertSame('ticket-itinerary-ASIF-2026-001003.pdf', $attachments[0]->as);
    }

    #[Test]
    public function google_customer_welcome_mail_renders_modern_layout_with_cta(): void
    {
        $this->seed(OtaFoundationSeeder::class);
        $agency = $this->asifAgency();
        $profile = CompanyEmailProfileResolver::resolve($agency);
        $user = User::factory()->create([
            'account_type' => AccountType::Customer,
            'current_agency_id' => $agency->id,
            'name' => 'Google User',
            'email' => 'google.user@example.test',
        ]);

        $mail = GoogleCustomerWelcomeMail::forUser($user);

        $this->assertStringContainsString('<!DOCTYPE html>', $mail->htmlBody);
        $this->assertStringContainsString(e($profile->name), $mail->htmlBody);
        $this->assertStringContainsString('Go to my account', $mail->htmlBody);
        $this->assertStringContainsString('google.user@example.test', $mail->htmlBody);
        $this->assertStringContainsString('Welcome to '.$profile->name, $mail->envelope()->subject);
    }

    #[Test]
    public function operational_notification_tests_still_isolate_customer_mailables(): void
    {
        Mail::fake();
        $this->seed(OtaFoundationSeeder::class);

        Mail::assertNotSent(BookingRequestReceivedMail::class);
        Mail::assertNotSent(PaymentVerifiedMail::class);
        Mail::assertNotSent(TicketIssuedMail::class);
        Mail::assertNotSent(OtaOperationalNotificationMail::class);
    }

    protected function asifAgency(): Agency
    {
        return Agency::query()->where('slug', config('ota.default_agency_slug'))->firstOrFail();
    }

    protected function bookingWithContact(Agency $agency, string $reference, ?string $pnr = null): Booking
    {
        $booking = Booking::factory()->create([
            'agency_id' => $agency->id,
            'booking_reference' => $reference,
            'route' => 'KHI → DXB',
            'travel_date' => now()->addDays(14),
            'status' => BookingStatus::Pending,
            'pnr' => $pnr,
        ]);
        BookingContact::query()->create([
            'booking_id' => $booking->id,
            'email' => 'customer@example.test',
            'phone' => '+92000000001',
            'meta' => ['name' => 'Test Customer'],
        ]);

        return $booking->fresh(['agency.agencySetting', 'contact', 'customer', 'passengers', 'fareBreakdown', 'tickets']);
    }
}
