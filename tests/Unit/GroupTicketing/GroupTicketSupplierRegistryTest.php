<?php

namespace Tests\Unit\GroupTicketing;

use App\Services\GroupTicketing\GroupTicketSupplierRegistry;
use App\Services\Suppliers\AlHaider\AlHaiderGroupTicketAdapter;
use App\Services\Suppliers\AmeerEMillat\AmeerEMillatGroupTicketAdapter;
use Tests\TestCase;

class GroupTicketSupplierRegistryTest extends TestCase
{
    public function test_registry_resolves_both_providers_and_public_ids(): void
    {
        $registry = app(GroupTicketSupplierRegistry::class);

        $this->assertInstanceOf(AlHaiderGroupTicketAdapter::class, $registry->for('alhaider'));
        $this->assertInstanceOf(AmeerEMillatGroupTicketAdapter::class, $registry->for('ameer_e_millat'));

        $this->assertSame('alhaider', $registry->resolveFromPublicId('ALH-123')?->providerKey());
        $this->assertSame('ameer_e_millat', $registry->resolveFromPublicId('AEM-456')?->providerKey());
        $this->assertSame('alhaider', $registry->resolveFromPublicId('789')?->providerKey());
    }

    public function test_unknown_provider_throws(): void
    {
        $this->expectException(\InvalidArgumentException::class);

        app(GroupTicketSupplierRegistry::class)->for('unknown_provider');
    }
}
