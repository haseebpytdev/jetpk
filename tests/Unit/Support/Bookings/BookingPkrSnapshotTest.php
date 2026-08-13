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

    public function test_quote_time_usd_conversion_uses_pricing_components(): void
    {
        $this->assertSame(164500.0, BookingPkrSnapshot::fromOffer([
            'currency' => 'USD',
            'total' => 590,
            'supplier_currency' => 'USD',
            'supplier_total' => 590,
            'pricing_components' => [
                'supplier_total_source' => 590,
                'supplier_currency' => 'USD',
                'pricing_currency' => 'PKR',
                'conversion_status' => 'converted',
                'fx_rate' => 278.8136,
                'final_total' => 164500,
            ],
        ]));
    }

    public function test_sar_conversion_uses_pricing_components(): void
    {
        $this->assertSame(74200.0, BookingPkrSnapshot::fromOffer([
            'currency' => 'SAR',
            'total' => 1000,
            'pricing_components' => [
                'supplier_currency' => 'SAR',
                'pricing_currency' => 'PKR',
                'conversion_status' => 'converted',
                'final_total' => 74200,
            ],
        ]));
    }

    public function test_supplier_overlay_does_not_hide_quote_pkr(): void
    {
        $snapshot = [
            'currency' => 'PKR',
            'total' => 164500,
            'final_customer_price' => 164500,
            'pricing_components' => [
                'supplier_currency' => 'USD',
                'pricing_currency' => 'PKR',
                'conversion_status' => 'converted',
                'final_total' => 164500,
            ],
        ];

        $this->assertSame(164500.0, BookingPkrSnapshot::fromOffer($snapshot));
        $this->assertNull(BookingPkrSnapshot::fromOffer([
            'currency' => 'USD',
            'total' => 590,
            'supplier_currency' => 'USD',
            'supplier_total' => 590,
        ]));
    }
}
