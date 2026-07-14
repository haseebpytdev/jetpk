<?php

namespace Tests\Unit\Support\Suppliers;

use App\Support\Suppliers\SupplierSourcePresenter;
use Tests\TestCase;

class SupplierSourcePresenterAirBlueTest extends TestCase
{
    public function test_airblue_label(): void
    {
        $this->assertSame('AirBlue', SupplierSourcePresenter::label('airblue'));
    }
}
