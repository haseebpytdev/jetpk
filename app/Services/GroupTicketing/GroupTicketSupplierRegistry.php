<?php

namespace App\Services\GroupTicketing;

use App\Contracts\GroupTicketing\GroupTicketSupplierInterface;
use App\Models\GroupInventory;
use InvalidArgumentException;

/**
 * Registry of group ticketing supplier adapters (Al-Haider, Ameer-e-Millat, …).
 */
class GroupTicketSupplierRegistry
{
    /** @var array<string, GroupTicketSupplierInterface> */
    private array $adapters = [];

    public function register(GroupTicketSupplierInterface $adapter): void
    {
        $this->adapters[$adapter->providerKey()] = $adapter;
    }

    public function for(string $providerKey): GroupTicketSupplierInterface
    {
        $providerKey = trim($providerKey);
        if ($providerKey === '' || ! isset($this->adapters[$providerKey])) {
            throw new InvalidArgumentException("Unknown group ticket supplier: {$providerKey}");
        }

        return $this->adapters[$providerKey];
    }

    public function forInventory(GroupInventory $inventory): GroupTicketSupplierInterface
    {
        return $this->for((string) $inventory->supplier);
    }

    /**
     * @return list<GroupTicketSupplierInterface>
     */
    public function all(): array
    {
        return array_values($this->adapters);
    }

    /**
     * @return list<GroupTicketSupplierInterface>
     */
    public function enabledConfigured(): array
    {
        return array_values(array_filter(
            $this->adapters,
            static fn (GroupTicketSupplierInterface $adapter): bool => $adapter->isEnabled() && $adapter->isConfigured(),
        ));
    }

    public function resolveFromPublicId(string $publicId): ?GroupTicketSupplierInterface
    {
        $publicId = trim($publicId);
        if ($publicId === '') {
            return null;
        }

        $upper = strtoupper($publicId);
        if (str_starts_with($upper, 'AEM-')) {
            return $this->adapters['ameer_e_millat'] ?? null;
        }

        if (str_starts_with($upper, 'ALH-')) {
            return $this->adapters['alhaider'] ?? null;
        }

        // Legacy bare numeric ids resolve to Al-Haider only.
        if (ctype_digit($publicId)) {
            return $this->adapters['alhaider'] ?? null;
        }

        return null;
    }
}
