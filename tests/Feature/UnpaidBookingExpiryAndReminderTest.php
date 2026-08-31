<?php

namespace Tests\Feature;

use App\Enums\BookingPaymentStatus;
use App\Enums\BookingStatus;
use App\Models\Agency;
use App\Models\Booking;
use App\Models\BookingContact;
use App\Models\BookingPayment;
use App\Models\CommunicationLog;
use App\Services\Bookings\PaymentDeadlineService;
use App\Services\Bookings\PaymentReminderService;
use App\Services\Bookings\UnpaidBookingExpiryService;
use App\Services\Booking\BookingService;
use Carbon\Carbon;
use Database\Seeders\OtaFoundationSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

class UnpaidBookingExpiryAndReminderTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(OtaFoundationSeeder::class);
        Mail::fake();
        config([
            'ota.payment_window_minutes' => 120,
            'ota.payment_deadline_safety_buffer_minutes' => 15,
            'ota.unpaid_booking_expiry.enabled' => true,
            'ota.unpaid_booking_expiry.supplier_cancel_enabled' => false,
            'ota.payment_reminders.enabled' => true,
            'ota.payment_reminders.first_remaining_fraction' => 0.5,
            'ota.payment_reminders.final_minutes_before' => 30,
            'mail.default' => 'array',
            'queue.default' => 'sync',
        ]);
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    public function test_submit_sets_payment_due_at_from_business_window(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-08-31 10:00:00', 'UTC'));

        $agency = Agency::query()->firstOrFail();
        $booking = Booking::factory()->create([
            'agency_id' => $agency->id,
            'status' => BookingStatus::Draft,
            'payment_status' => 'unpaid',
            'payment_required_by' => null,
        ]);
        BookingContact::query()->create([
            'booking_id' => $booking->id,
            'email' => 'guest.ops@example.com',
            'phone' => '+923001112233',
        ]);

        $submitted = app(BookingService::class)->submitBookingRequest($booking->fresh());

        $this->assertSame(BookingStatus::Pending, $submitted->status);
        $this->assertNotNull($submitted->payment_due_at);
        $this->assertTrue(
            $submitted->payment_due_at->equalTo(Carbon::parse('2026-08-31 12:00:00', 'UTC'))
        );
    }

    public function test_effective_deadline_respects_supplier_minus_buffer(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-08-31 10:00:00', 'UTC'));
        config(['ota.payment_window_minutes' => 180]);

        $agency = Agency::query()->firstOrFail();
        $booking = Booking::factory()->create([
            'agency_id' => $agency->id,
            'status' => BookingStatus::Draft,
            'submitted_at' => now(),
            'payment_required_by' => Carbon::parse('2026-08-31 11:00:00', 'UTC'),
        ]);

        $deadline = app(PaymentDeadlineService::class)->computeEffectiveDeadline($booking);

        // supplier 11:00 − 15m buffer = 10:45, business = 13:00 → effective 10:45
        $this->assertTrue($deadline->equalTo(Carbon::parse('2026-08-31 10:45:00', 'UTC')));
    }

    public function test_unpaid_booking_expires_once_and_is_idempotent(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-08-31 10:00:00', 'UTC'));

        $agency = Agency::query()->firstOrFail();
        $booking = Booking::factory()->create([
            'agency_id' => $agency->id,
            'status' => BookingStatus::Pending,
            'payment_status' => 'unpaid',
            'submitted_at' => now()->subHours(3),
            'payment_due_at' => now()->subMinute(),
            'booking_reference' => 'JPOPS001',
        ]);
        BookingContact::query()->create([
            'booking_id' => $booking->id,
            'email' => 'expire.ops@example.com',
        ]);

        $service = app(UnpaidBookingExpiryService::class);
        $first = $service->expireIfDue($booking->fresh());
        $second = $service->expireIfDue($booking->fresh());

        $this->assertTrue($first['expired']);
        $this->assertFalse($second['expired']);
        $this->assertSame('already_expired', $second['reason']);

        $booking->refresh();
        $this->assertSame(BookingStatus::Expired, $booking->status);
        $this->assertSame(0, $first['supplier_cancel_attempted'] ? 1 : 0);

        $customerExpired = CommunicationLog::query()
            ->where('booking_id', $booking->id)
            ->where('event', 'booking_expired')
            ->count();
        $this->assertGreaterThanOrEqual(1, $customerExpired);
    }

    public function test_paid_booking_is_not_expired_on_race(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-08-31 10:00:00', 'UTC'));

        $agency = Agency::query()->firstOrFail();
        $booking = Booking::factory()->create([
            'agency_id' => $agency->id,
            'status' => BookingStatus::PaymentPending,
            'payment_status' => 'unpaid',
            'submitted_at' => now()->subHours(3),
            'payment_due_at' => now()->subMinute(),
            'booking_reference' => 'JPOPS002',
        ]);

        BookingPayment::query()->create([
            'booking_id' => $booking->id,
            'agency_id' => $agency->id,
            'amount' => 1000,
            'currency' => 'PKR',
            'status' => BookingPaymentStatus::Verified,
            'method' => 'bank_transfer',
        ]);
        $booking->forceFill(['payment_status' => 'paid', 'status' => BookingStatus::Paid])->save();

        $result = app(UnpaidBookingExpiryService::class)->expireIfDue($booking->fresh());

        $this->assertFalse($result['expired']);
        $this->assertSame('paid_barrier', $result['reason']);
        $this->assertSame(BookingStatus::Paid, $booking->fresh()->status);
    }

    public function test_payment_reminder_dedupes_per_stage_and_suppresses_paid(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-08-31 10:00:00', 'UTC'));

        $agency = Agency::query()->firstOrFail();
        $booking = Booking::factory()->create([
            'agency_id' => $agency->id,
            'status' => BookingStatus::Pending,
            'payment_status' => 'unpaid',
            'submitted_at' => now()->subMinutes(90),
            'payment_due_at' => now()->addMinutes(30),
            'booking_reference' => 'JPOPS003',
        ]);
        BookingContact::query()->create([
            'booking_id' => $booking->id,
            'email' => 'remind.ops@example.com',
        ]);

        $reminders = app(PaymentReminderService::class);
        $first = $reminders->sendIfDue($booking->fresh());
        $again = $reminders->sendIfDue($booking->fresh());

        $this->assertTrue($first['sent']);
        $this->assertSame('final', $first['stage']);
        $this->assertFalse($again['sent']);

        $booking->forceFill(['payment_status' => 'paid', 'status' => BookingStatus::Paid])->save();
        $paid = $reminders->sendIfDue($booking->fresh());
        $this->assertFalse($paid['sent']);
    }

    public function test_artisan_expiry_command_runs(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-08-31 10:00:00', 'UTC'));

        $agency = Agency::query()->firstOrFail();
        Booking::factory()->create([
            'agency_id' => $agency->id,
            'status' => BookingStatus::Pending,
            'payment_status' => 'unpaid',
            'submitted_at' => now()->subHours(3),
            'payment_due_at' => now()->subMinute(),
            'booking_reference' => 'JPOPS004',
        ]);

        $this->artisan('ota:expire-unpaid-bookings')->assertSuccessful();

        $this->assertSame(
            BookingStatus::Expired,
            Booking::query()->where('booking_reference', 'JPOPS004')->firstOrFail()->status
        );
    }
}
