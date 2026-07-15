<?php

namespace Tests\Unit\Support\Suppliers;

use App\Enums\SupplierConnectionStatus;
use App\Enums\SupplierEnvironment;
use App\Enums\SupplierProvider;
use App\Models\SupplierConnection;
use App\Support\Suppliers\EmptySupplierConnectionPlaceholderInspector;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class EmptySupplierConnectionPlaceholderInspectorTest extends TestCase
{
    #[Test]
    public function test_detects_known_empty_placeholder(): void
    {
        $connection = new SupplierConnection([
            'name' => 'Duffel',
            'display_name' => 'Duffel',
            'provider' => SupplierProvider::Duffel,
            'environment' => SupplierEnvironment::Sandbox,
            'status' => SupplierConnectionStatus::Inactive,
            'is_active' => false,
            'credentials' => null,
        ]);

        $this->assertTrue(app(EmptySupplierConnectionPlaceholderInspector::class)->isRemovablePlaceholder($connection));
    }

    #[Test]
    public function test_rejects_configured_connection(): void
    {
        $connection = new SupplierConnection([
            'name' => 'IATI / OTA',
            'provider' => SupplierProvider::Iati,
            'status' => SupplierConnectionStatus::Active,
            'is_active' => true,
            'credentials' => ['auth_code' => 'code', 'organization_id' => '123'],
        ]);

        $this->assertFalse(app(EmptySupplierConnectionPlaceholderInspector::class)->isRemovablePlaceholder($connection));
    }
}
