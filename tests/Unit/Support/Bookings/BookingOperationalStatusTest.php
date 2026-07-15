<?php

namespace Tests\Unit\Support\Bookings;

use App\Enums\BookingStatus;
use App\Models\Booking;
use App\Models\SupplierBookingAttempt;
use App\Support\Bookings\BookingListPresenter;
use App\Support\Bookings\BookingOperationalStatus;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class BookingOperationalStatusTest extends TestCase
{
    use RefreshDatabase;

    public function test_no_pnr_with_failed_429_attempt_shows_sabre_busy_not_ticketing_pending(): void
    {
        $result = BookingOperationalStatus::fromValues(
            BookingStatus::Draft->value,
            'unpaid',
            null,
            'pending',
            false,
            null,
            'failed',
            'sabre_booking_http_failed',
            429,
        );

        $this->assertSame('sabre_busy_retry', $result['code']);
        $this->assertSame('Sabre busy / retry later', $result['label']);
        $this->assertNotSame('ticketing_pending', $result['code']);
    }

    public function test_no_pnr_with_needs_review_stale_shop_shows_flight_unavailable(): void
    {
        $result = BookingOperationalStatus::fromValues(
            BookingStatus::Draft->value,
            'unpaid',
            'manual_review',
            'pending',
            false,
            null,
            'needs_review',
            'sabre_passenger_records_stale_shop_segment',
            null,
        );

        $this->assertSame('flight_no_longer_available', $result['code']);
        $this->assertStringContainsString('Flight no longer available', $result['label']);
        $this->assertStringContainsString('search again', strtolower($result['label']));
    }

    public function test_no_pnr_with_needs_review_application_error_shows_staff_review(): void
    {
        $result = BookingOperationalStatus::fromValues(
            BookingStatus::Draft->value,
            'unpaid',
            'manual_review',
            'pending',
            false,
            null,
            'needs_review',
            'sabre_booking_application_error',
            null,
        );

        $this->assertSame('sabre_application_error', $result['code']);
        $this->assertStringContainsString('Supplier booking failed', $result['label']);
        $this->assertStringContainsString('staff review', strtolower($result['label']));
    }

    public function test_no_pnr_with_connection_timeout_shows_timeout_retry(): void
    {
        $result = BookingOperationalStatus::fromValues(
            BookingStatus::Draft->value,
            'unpaid',
            null,
            'pending',
            false,
            null,
            'failed',
            'sabre_booking_connection_error',
            null,
        );

        $this->assertSame('sabre_timeout_retry', $result['code']);
        $this->assertSame('Sabre timeout / retry needed', $result['label']);
    }

    public function test_no_pnr_needs_review_connection_error_shows_timeout_retry(): void
    {
        $result = BookingOperationalStatus::fromValues(
            BookingStatus::Draft->value,
            'unpaid',
            'manual_review',
            'pending',
            false,
            null,
            'needs_review',
            'sabre_booking_connection_error',
            null,
        );

        $this->assertSame('sabre_timeout_retry', $result['code']);
    }

    public function test_safe_summary_too_many_requests_triggers_busy_without_429(): void
    {
        $this->assertTrue(BookingOperationalStatus::safeSummaryIndicatesTooManyRequests([
            'message' => 'Too Many Requests',
        ]));

        $result = BookingOperationalStatus::fromValues(
            BookingStatus::Draft->value,
            'unpaid',
            null,
            'pending',
            false,
            null,
            'failed',
            'sabre_booking_http_failed',
            null,
            true,
        );

        $this->assertSame('sabre_busy_retry', $result['code']);
        $this->assertSame('Sabre busy / retry later', $result['label']);
    }

    public function test_pnr_exists_with_ticketing_pending_shows_ticketing_pending(): void
    {
        $result = BookingOperationalStatus::fromValues(
            BookingStatus::Draft->value,
            'unpaid',
            null,
            'pending',
            true,
        );

        $this->assertSame('ticketing_pending', $result['code']);
        $this->assertSame('Ticketing pending', $result['label']);
    }

    public function test_pnr_exists_without_ticketing_pending_shows_pnr_created(): void
    {
        $result = BookingOperationalStatus::fromValues(
            BookingStatus::Draft->value,
            'unpaid',
            null,
            'not_started',
            true,
        );

        $this->assertSame('pnr_created', $result['code']);
        $this->assertSame('PNR created', $result['label']);
    }

    public function test_unpaid_payment_label_unchanged_in_list_presenter(): void
    {
        $booking = Booking::factory()->create([
            'status' => BookingStatus::Draft,
            'payment_status' => 'unpaid',
            'ticketing_status' => 'pending',
            'pnr' => null,
            'supplier_reference' => null,
        ]);

        SupplierBookingAttempt::query()->create([
            'agency_id' => $booking->agency_id,
            'booking_id' => $booking->id,
            'provider' => 'sabre',
            'action' => 'create_pnr',
            'status' => 'failed',
            'error_code' => 'sabre_booking_http_failed',
            'safe_summary' => ['http_status' => 429],
            'attempted_at' => now(),
            'completed_at' => now(),
        ]);

        $row = BookingListPresenter::toListRow($booking->fresh());

        $this->assertSame('Sabre busy / retry later', $row['status_display']);
        $this->assertSame('unpaid', $row['payment_status_display']);
        $this->assertStringNotContainsString('ticketing pending', strtolower((string) $row['status_display']));
    }

    public function test_ticketing_pending_without_pnr_shows_not_started_ticketing_column(): void
    {
        $booking = Booking::factory()->create([
            'status' => BookingStatus::Draft,
            'payment_status' => 'unpaid',
            'ticketing_status' => 'pending',
            'pnr' => null,
            'supplier_reference' => null,
            'meta' => ['supplier_provider' => 'sabre'],
        ]);

        $row = BookingListPresenter::toListRow($booking->fresh());

        $this->assertSame('not started', $row['ticketing_status_display']);
    }

    public function test_no_pnr_without_failed_attempt_does_not_show_ticketing_pending(): void
    {
        $result = BookingOperationalStatus::fromValues(
            BookingStatus::Draft->value,
            'unpaid',
            null,
            'pending',
            false,
        );

        $this->assertSame('draft', $result['code']);
    }

    public function test_supplier_reference_counts_as_pnr_for_ticketing_pending(): void
    {
        $result = BookingOperationalStatus::fromValues(
            BookingStatus::Draft->value,
            'unpaid',
            null,
            'pending',
            true,
        );

        $this->assertSame('ticketing_pending', $result['code']);
    }
}
