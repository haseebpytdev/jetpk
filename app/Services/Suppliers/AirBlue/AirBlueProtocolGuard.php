<?php

namespace App\Services\Suppliers\AirBlue;

use App\Enums\AirBlueZapwaysProtocolVersion;
use App\Models\SupplierConnection;
use App\Services\Suppliers\AirBlue\Exceptions\AirBlueValidationException;

/**
 * Ensures offer/booking lifecycle stays on the same Zapways protocol version.
 */
class AirBlueProtocolGuard
{
    public function __construct(
        private readonly AirBlueConfigResolver $configResolver,
    ) {}

    /**
     * @param  array<string, mixed>  $providerContext
     */
    public function assertCompatible(SupplierConnection $connection, array $providerContext, string $operation): void
    {
        $expected = trim((string) ($providerContext['protocol_version'] ?? ''));
        if ($expected === '') {
            $expected = AirBlueZapwaysProtocolVersion::V2->value;
        }

        $resolved = $this->configResolver->resolveOta($connection);
        $actual = (string) ($resolved['protocol_version'] ?? AirBlueZapwaysProtocolVersion::V2->value);
        if ($expected === $actual) {
            return;
        }

        throw new AirBlueValidationException(
            'protocol_version_mismatch',
            422,
            sprintf(
                'AirBlue %s requires Zapways protocol %s but connection uses %s.',
                $operation,
                $expected,
                $actual,
            ),
        );
    }

    public function protocolVersion(SupplierConnection $connection): AirBlueZapwaysProtocolVersion
    {
        $credentials = is_array($connection->credentials) ? $connection->credentials : [];

        return AirBlueZapwaysProtocolVersion::fromCredentials($credentials);
    }
}
