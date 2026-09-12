<?php

namespace Tests\Feature\Communication;

use App\Enums\BookingStatus;
use App\Enums\OtaNotificationEvent;
use App\Mail\BookingUniversalNotification;
use App\Models\Agency;
use App\Models\AgencyCommunicationSetting;
use App\Models\Booking;
use App\Models\CommunicationLog;
use App\Models\User;
use App\Services\Booking\BookingService;
use App\Services\Communication\BookingCommunicationService;
use App\Services\Communication\BookingEmailPayloadFactory;
use Database\Seeders\OtaFoundationSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class BookingStatusChangeEmailSemanticsTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    public function operational_status_change_payload_is_not_new_booking_alert(): void
    {
        $booking = $this->booking();
        $payload = app(BookingEmailPayloadFactory::class)->adminStatusChangedAlert($booking, 'approved', 'pending');

        $this->assertSame('admin_booking_status_changed', $payload['type']);
        $this->assertStringContainsString('[ADMIN]', (string) $payload['subject']);
        $this->assertStringContainsString('Booking Status Updated', (string) $payload['subject']);
        $this->assertStringNotContainsString('New customer booking', (string) $payload['intro']);
    }

    #[Test]
    public function submit_booking_request_does_not_emit_creation_time_status_change_notification(): void
    {
        Mail::fake();
        config(['mail.default' => 'array']);

        $booking = $this->draftBookingWithContact();
        $this->enableOutboundEmail();
        app(BookingService::class)->submitBookingRequest($booking);

        Mail::assertSent(BookingUniversalNotification::class, function (BookingUniversalNotification $mail): bool {
            return ($mail->payload['type'] ?? '') === 'booking_received';
        });

        $this->assertFalse(
            CommunicationLog::query()
                ->where('booking_id', $booking->id)
                ->where('event', OtaNotificationEvent::BookingStatusChanged->value)
                ->exists(),
        );
    }

    #[Test]
    public function genuine_status_transition_emits_status_change_operational_payload(): void
    {
        $booking = $this->booking(BookingStatus::Pending);
        $this->enableOutboundEmail();

        app(BookingCommunicationService::class)->sendBookingStatusChanged($booking, 'confirmed', 'pending');

        $log = CommunicationLog::query()
            ->where('booking_id', $booking->id)
            ->where('event', OtaNotificationEvent::BookingStatusChanged->value)
            ->latest('id')
            ->first();

        $this->assertNotNull($log);
        $meta = is_array($log->meta) ? $log->meta : [];
        $payload = is_array($meta['payload'] ?? null) ? $meta['payload'] : [];
        $universal = is_array($payload['universal_email'] ?? null) ? $payload['universal_email'] : [];

        $this->assertSame('admin_booking_status_changed', $universal['type'] ?? $meta['notification_type'] ?? null);
    }

    private function booking(BookingStatus $status = BookingStatus::Pending): Booking
    {
        $this->seed(OtaFoundationSeeder::class);
        $agency = Agency::query()->where('slug', 'asif-travels')->firstOrFail();

        return Booking::factory()->create([
            'agency_id' => $agency->id,
            'booking_reference' => 'STATUS-SEM-'.strtoupper(substr(uniqid(), -5)),
            'status' => $status,
            'route' => 'LHE-KHI',
        ]);
    }

    private function draftBookingWithContact(): Booking
    {
        $this->seed(OtaFoundationSeeder::class);
        $agency = Agency::query()->where('slug', 'asif-travels')->firstOrFail();
        $customer = User::factory()->create(['current_agency_id' => $agency->id]);
        $booking = Booking::factory()->create([
            'agency_id' => $agency->id,
            'customer_id' => $customer->id,
            'status' => BookingStatus::Draft,
            'route' => 'LHE-KHI',
        ]);
        $booking->contact()->create([
            'email' => 'traveler@example.test',
            'phone' => '03001234567',
            'country' => 'PK',
            'address_line' => 'Street 1',
            'meta' => ['name' => 'Test Traveler'],
        ]);

        return $booking->fresh(['agency', 'contact', 'customer']);
    }

    private function enableOutboundEmail(): void
    {
        $agency = Agency::query()->where('slug', 'asif-travels')->firstOrFail();
        AgencyCommunicationSetting::query()->updateOrCreate(
            ['agency_id' => $agency->id],
            ['email_enabled' => true, 'smtp_enabled' => false],
        );
    }
}
