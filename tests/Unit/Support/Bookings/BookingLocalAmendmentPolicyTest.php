<?php

namespace Tests\Unit\Support\Bookings;

use App\Models\Booking;
use App\Support\Bookings\BookingLocalAmendmentPolicy;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class BookingLocalAmendmentPolicyTest extends TestCase
{
    #[Test]
    public function contact_editable_before_and_after_pnr_when_not_cancelled(): void
    {
        $booking = new Booking([
            'status' => 'confirmed',
            'pnr' => 'ABC123',
            'ticketing_status' => 'pending',
        ]);

        $result = BookingLocalAmendmentPolicy::evaluate($booking);

        $this->assertTrue($result['canEditContact']);
        $this->assertFalse($result['canEditPassengers']);
        $this->assertTrue($result['hasSupplierPnr']);
        $this->assertStringContainsString('not synced', $result['contactPolicy']);
    }

    #[Test]
    public function passengers_editable_only_without_pnr_or_ticket(): void
    {
        $booking = new Booking([
            'status' => 'pending',
            'pnr' => null,
            'ticketing_status' => 'not_started',
        ]);

        $result = BookingLocalAmendmentPolicy::evaluate($booking);

        $this->assertTrue($result['canEditContact']);
        $this->assertTrue($result['canEditPassengers']);
        $this->assertFalse($result['hasSupplierPnr']);
    }

    #[Test]
    public function cancelled_blocks_contact_and_passengers(): void
    {
        $booking = new Booking([
            'status' => 'cancelled',
            'pnr' => null,
        ]);

        $result = BookingLocalAmendmentPolicy::evaluate($booking);

        $this->assertFalse($result['canEditContact']);
        $this->assertFalse($result['canEditPassengers']);
    }
}
