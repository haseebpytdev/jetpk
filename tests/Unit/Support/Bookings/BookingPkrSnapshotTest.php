<?php

namespace Tests\Unit\Support\Bookings;

use App\Support\Bookings\BookingPkrSnapshot;
use PHPUnit\Framework\TestCase;

class BookingPkrSnapshotTest extends TestCase
{
    public function test_pkr_offer_keeps_pkr_total(): void
    {
        $this->assertSame(50000.0, BookingPkrSnapshot::fromOffer([
            'currency' => 'PKR',
            'total' => 50000,
        ]));
    }

    public function test_usd_total_is_not_copied_into_pkr(): void
    {
        $this->assertNull(BookingPkrSnapshot::fromOffer([
            'currency' => 'USD',
            'total' => 590,
            'supplier_currency' => 'USD',
            'supplier_total' => 590,
        ]));
    }

    public function test_explicit_commercial_pkr_snapshot_is_kept(): void
    {
        $this->assertSame(164500.0, BookingPkrSnapshot::fromOffer([
            'currency' => 'USD',
            'total' => 590,
            'customer_total_pkr' => 164500,
        ]));
    }
}
