<?php

namespace Tests\Unit\Services\Suppliers;

use App\Models\SupplierConnection;
use App\Services\Suppliers\SupplierConnectionService;
use Tests\TestCase;

class SupplierConnectionServiceNullProviderTest extends TestCase
{
    public function test_credential_keys_present_returns_false_for_null_provider_without_throw(): void
    {
        $connection = new SupplierConnection;
        $connection->credentials = [
            'client_id' => 'test-client',
            'client_secret' => 'test-secret',
        ];

        $service = app(SupplierConnectionService::class);

        $this->assertFalse($service->credentialKeysPresent($connection));
    }
}
