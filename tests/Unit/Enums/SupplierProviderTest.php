<?php

namespace Tests\Unit\Enums;

use App\Enums\SupplierProvider;
use App\Support\Suppliers\RetiredSupplierProviders;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class SupplierProviderTest extends TestCase
{
    #[Test]
    public function active_set_excludes_platform_and_retired_providers(): void
    {
        $values = SupplierProvider::activeValues();
        $this->assertContains('sabre', $values);
        $this->assertContains('airblue', $values);
        $this->assertNotContains('smtp', $values);
        $this->assertNotContains('google_oauth', $values);
        $this->assertNotContains('amadeus', $values);
        $this->assertNotContains('travelport', $values);
        $this->assertNotContains('airline_direct', $values);
        $this->assertNull(SupplierProvider::tryFrom('smtp'));
        $this->assertTrue(RetiredSupplierProviders::isRetired('smtp'));
        $this->assertTrue(RetiredSupplierProviders::isRetired('google_oauth'));
    }
}
