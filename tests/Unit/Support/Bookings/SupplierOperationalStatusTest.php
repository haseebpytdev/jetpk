<?php

namespace Tests\Unit\Support\Bookings;

use App\Support\Bookings\SupplierOperationalStatus;
use Tests\TestCase;

class SupplierOperationalStatusTest extends TestCase
{
    public function test_from_values_returns_operational_shape_without_class_not_found(): void
    {
        $result = SupplierOperationalStatus::fromValues('not_started', 'sabre', false, null);

        $this->assertSame(['code', 'label', 'meaning'], array_keys($result));
        $this->assertSame('not_started', $result['code']);
        $this->assertSame('not started', $result['label']);
        $this->assertSame('No supplier booking attempted.', $result['meaning']);
    }

    public function test_from_values_marks_booked_when_pnr_present(): void
    {
        $result = SupplierOperationalStatus::fromValues('not_started', 'sabre', true, null);

        $this->assertSame('booked', $result['code']);
    }

    public function test_from_values_handles_null_and_empty_inputs(): void
    {
        $result = SupplierOperationalStatus::fromValues(null, null, false, null);

        $this->assertSame('not_supported', $result['code']);
        $this->assertArrayHasKey('meaning', $result);
    }
}
