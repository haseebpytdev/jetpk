<?php

namespace App\Services\Integrations;

use App\Contracts\Integrations\IntegrationManager;
use App\Services\Integrations\Managers\AbhiPayIntegrationManager;
use App\Services\Integrations\Managers\DraftIntegrationManager;
use App\Services\Integrations\Managers\SupplierIntegrationManager;
use App\Support\Integrations\IntegrationDefinition;
use App\Support\Integrations\IntegrationRegistry;
use InvalidArgumentException;

class IntegrationManagerResolver
{
    public function resolve(string $code): IntegrationManager
    {
        $definition = IntegrationRegistry::find($code);
        if ($definition === null) {
            throw new InvalidArgumentException('Unknown integration: '.$code);
        }

        return $this->forDefinition($definition);
    }

    public function forDefinition(IntegrationDefinition $definition): IntegrationManager
    {
        return match ($definition->manager) {
            'abhipay' => app(AbhiPayIntegrationManager::class),
            'supplier' => app(SupplierIntegrationManager::class, ['definition' => $definition]),
            default => new DraftIntegrationManager($definition),
        };
    }
}
