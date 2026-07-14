<?php

namespace Tests\Unit\Support\Suppliers;

use App\Support\Suppliers\SupplierSourcePresenter;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class SupplierSourcePresenterTest extends TestCase
{
    #[Test]
    public function test_labels_sabre_and_iati(): void
    {
        $this->assertSame('Sabre', SupplierSourcePresenter::label('sabre'));
        $this->assertSame('IATI', SupplierSourcePresenter::label('iati'));
    }

    #[Test]
    public function test_labels_pia_ndc_duffel_and_airline_direct(): void
    {
        $this->assertSame('PIA NDC', SupplierSourcePresenter::label('pia_ndc'));
        $this->assertSame('Duffel', SupplierSourcePresenter::label('duffel'));
        $this->assertSame('Airline Direct', SupplierSourcePresenter::label('airline_direct'));
    }

    #[Test]
    public function test_unknown_provider_defaults_to_supplier(): void
    {
        $this->assertSame('Supplier', SupplierSourcePresenter::label(null));
        $this->assertSame('Supplier', SupplierSourcePresenter::label(''));
        $this->assertSame('Supplier', SupplierSourcePresenter::label('unknown_vendor'));
    }

    #[Test]
    public function test_css_class_is_neutral_badge(): void
    {
        $this->assertSame('flight-card-source-badge', SupplierSourcePresenter::cssClass('sabre'));
        $this->assertSame('flight-card-source-badge', SupplierSourcePresenter::cssClass(null));
    }
}
