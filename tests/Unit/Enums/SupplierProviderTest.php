<?php

namespace Tests\Unit\Enums;

use App\Enums\SupplierProvider;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class SupplierProviderTest extends TestCase
{
    #[Test]
    public function it_accepts_non_flight_infrastructure_provider_values(): void
    {
        $this->assertSame(SupplierProvider::Smtp, SupplierProvider::from('smtp'));
        $this->assertSame(SupplierProvider::GoogleOauth, SupplierProvider::from('google_oauth'));
        $this->assertSame(SupplierProvider::AlHaider, SupplierProvider::from('al_haider'));
    }
}
