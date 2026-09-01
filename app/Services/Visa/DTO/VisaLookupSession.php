<?php

namespace App\Services\Visa\DTO;

final readonly class VisaLookupSession
{
    public function __construct(
        public string $id,
        public string $providerKey,
        public string $countryCode,
        public int $createdAt,
        public int $expiresAt,
        public string $ownerToken,
    ) {}

    public function isExpired(int $now = null): bool
    {
        return ($now ?? time()) >= $this->expiresAt;
    }
}
