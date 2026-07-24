<?php

namespace Tests\Feature\Communication;

use App\Enums\BookingCommunicationEvent;
use App\Enums\BookingStatus;
use App\Enums\OtaNotificationEvent;
use App\Models\Agency;
use App\Models\AgencyCommunicationSetting;
use App\Models\Booking;
use App\Models\CommunicationLog;
use App\Models\User;
use App\Services\Communication\BookingCommunicationService;
use App\Services\Communication\StaleSynchronousCommunicationLogRepairService;
use Database\Seeders\OtaFoundationSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Mail\Mailable;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

class BookingCancellationSyncMailLogTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(OtaFoundationSeeder::class);
        $this->enableAgencyEmail();
    }

    public function test_sync_queue_successful_cancellation_mail_marks_single_log_sent(): void
    {
        Mail::fake();
        config(['mail.default' => 'smtp', 'queue.default' => 'sync']);

        $booking = $this->cancelledBookingWithContact();
        app(BookingCommunicationService::class)->sendCancellationConfirmedIfNeeded($booking);

        $logs = CommunicationLog::query()
            ->where('booking_id', $booking->id)
            ->where('event', BookingCommunicationEvent::BookingCancelled->value)
            ->where('channel', 'email')
            ->get();

        $this->assertCount(1, $logs);
        $log = $logs->first();
        $this->assertSame('sent', $log->status);
        $this->assertNotNull($log->sent_at);
        $this->assertSame('smtp', $log->provider);
        Mail::assertSent(Mailable::class);
    }

    public function test_sync_queue_failed_cancellation_mail_records_safe_error_on_single_log(): void
    {
        config(['mail.default' => 'smtp', 'queue.default' => 'sync']);
        $agency = Agency::query()->where('slug', 'asif-travels')->firstOrFail();
        AgencyCommunicationSetting::query()->updateOrCreate(
            ['agency_id' => $agency->id],
            [
                'email_enabled' => true,
                'smtp_password' => 'super-secret-smtp-pass',
            ],
        );
        Mail::shouldReceive('send')->andReturnUsing(function () {
            static $calls = 0;
            $calls++;
            if ($calls === 1) {
                throw new \RuntimeException('SMTP auth failed: super-secret-smtp-pass');
            }
        });

        $booking = $this->cancelledBookingWithContact();
        app(BookingCommunicationService::class)->sendCancellationConfirmedIfNeeded($booking);

        $logs = CommunicationLog::query()
            ->where('booking_id', $booking->id)
            ->where('event', BookingCommunicationEvent::BookingCancelled->value)
            ->where('channel', 'email')
            ->get();

        $this->assertCount(1, $logs);
        $log = $logs->first();
        $this->assertSame('failed', $log->status);
        $this->assertStringNotContainsString('super-secret-smtp-pass', (string) $log->error_message);
        $this->assertNull($log->sent_at);
    }

    public function test_database_queue_keeps_customer_cancellation_log_queued_until_job_execution(): void
    {
        Mail::fake();
        Queue::fake();
        config(['mail.default' => 'smtp', 'queue.default' => 'database']);

        $booking = $this->cancelledBookingWithContact();
        app(BookingCommunicationService::class)->sendCancellationConfirmedIfNeeded($booking);

        $log = CommunicationLog::query()
            ->where('booking_id', $booking->id)
            ->where('event', BookingCommunicationEvent::BookingCancelled->value)
            ->where('channel', 'email')
            ->first();

        $this->assertNotNull($log);
        $this->assertSame('queued', $log->status);
        $this->assertNull($log->sent_at);
        Mail::assertQueued(Mailable::class);
    }

    public function test_duplicate_cancellation_call_creates_no_extra_row_or_send(): void
    {
        Mail::fake();
        config(['mail.default' => 'smtp', 'queue.default' => 'sync']);

        $booking = $this->cancelledBookingWithContact();
        $service = app(BookingCommunicationService::class);
        $service->sendCancellationConfirmedIfNeeded($booking);
        $sentAfterFirst = count(Mail::sent(Mailable::class, function (): bool {
            return true;
        }));
        $service->sendCancellationConfirmedIfNeeded($booking->fresh());

        $this->assertSame(1, CommunicationLog::query()
            ->where('booking_id', $booking->id)
            ->where('event', BookingCommunicationEvent::BookingCancelled->value)
            ->where('channel', 'email')
            ->count());
        $this->assertSame($sentAfterFirst, count(Mail::sent(Mailable::class, function (): bool {
            return true;
        })));
    }

    public function test_cancellation_mail_path_makes_no_supplier_http_calls(): void
    {
        Http::fake();
        Mail::fake();
        config(['mail.default' => 'smtp', 'queue.default' => 'sync']);

        $booking = $this->cancelledBookingWithContact();
        app(BookingCommunicationService::class)->sendCancellationConfirmedIfNeeded($booking);

        Http::assertNothingSent();
    }

    public function test_repair_marks_stale_booking_cancelled_log_sent_when_operational_evidence_exists(): void
    {
        config(['queue.default' => 'sync']);
        $booking = $this->cancelledBookingWithContact();

        $staleLog = CommunicationLog::query()->create([
            'agency_id' => $booking->agency_id,
            'booking_id' => $booking->id,
            'user_id' => $booking->customer_id,
            'channel' => 'email',
            'event' => BookingCommunicationEvent::BookingCancelled->value,
            'recipient_email' => 'traveler@example.test',
            'status' => 'queued',
            'provider' => 'smtp',
            'meta' => [
                'recipient_type' => 'customer',
                'status_label' => 'cancelled',
            ],
            'created_at' => now()->subMinute(),
            'updated_at' => now()->subMinute(),
        ]);

        $corroborating = CommunicationLog::query()->create([
            'agency_id' => $booking->agency_id,
            'booking_id' => $booking->id,
            'channel' => 'email',
            'event' => OtaNotificationEvent::BookingStatusChanged->value,
            'recipient_email' => 'admin@example.test',
            'status' => 'sent',
            'provider' => 'smtp',
            'sent_at' => now()->subMinute(),
            'meta' => [
                'payload' => ['status_label' => 'cancelled'],
            ],
            'created_at' => now()->subMinute(),
            'updated_at' => now()->subMinute(),
        ]);

        $result = app(StaleSynchronousCommunicationLogRepairService::class)->repair($staleLog, apply: true);

        $this->assertSame('repaired', $result['outcome']);
        $staleLog->refresh();
        $this->assertSame('sent', $staleLog->status);
        $this->assertNotNull($staleLog->sent_at);
        $this->assertSame($corroborating->sent_at?->toDateTimeString(), $staleLog->sent_at?->toDateTimeString());
    }

    public function test_repair_marks_stale_log_for_manual_review_without_evidence(): void
    {
        config(['queue.default' => 'sync']);
        $booking = $this->cancelledBookingWithContact();

        $staleLog = CommunicationLog::query()->create([
            'agency_id' => $booking->agency_id,
            'booking_id' => $booking->id,
            'channel' => 'email',
            'event' => BookingCommunicationEvent::BookingCancelled->value,
            'recipient_email' => 'traveler@example.test',
            'status' => 'queued',
            'provider' => 'smtp',
            'meta' => ['recipient_type' => 'customer'],
        ]);

        $result = app(StaleSynchronousCommunicationLogRepairService::class)->repair($staleLog, apply: true);

        $this->assertSame('needs_manual_review', $result['outcome']);
        $staleLog->refresh();
        $this->assertSame('queued', $staleLog->status);
        $this->assertNull($staleLog->sent_at);
        $this->assertStringContainsString('manual review', strtolower((string) $staleLog->error_message));
        $this->assertSame('needs_manual_review', $staleLog->meta['stale_sync_repair'] ?? null);
    }

    public function test_repair_command_refuses_non_sync_queue(): void
    {
        config(['queue.default' => 'database']);

        $exitCode = Artisan::call('ota:repair-stale-sync-communication-logs', [
            '--booking-id' => 1,
        ]);

        $this->assertSame(1, $exitCode);
        $this->assertStringContainsString('not sync', Artisan::output());
    }

    protected function enableAgencyEmail(): void
    {
        $agency = Agency::query()->where('slug', 'asif-travels')->firstOrFail();
        AgencyCommunicationSetting::query()->updateOrCreate(
            ['agency_id' => $agency->id],
            ['email_enabled' => true],
        );
    }

    protected function cancelledBookingWithContact(): Booking
    {
        $agency = Agency::query()->where('slug', 'asif-travels')->firstOrFail();
        $customer = User::factory()->create(['current_agency_id' => $agency->id]);
        $booking = Booking::factory()->create([
            'agency_id' => $agency->id,
            'customer_id' => $customer->id,
            'status' => BookingStatus::Cancelled,
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
}
