<?php

namespace App\Enums;

/**
 * Zapways OTA wire protocol version (distinct from document revision and RQ Version attribute).
 */
enum AirBlueZapwaysProtocolVersion: string
{
    case V2 = '2.0';
    case V3 = '3.0';

    public static function fromCredentials(array $credentials): self
    {
        $raw = strtolower(trim((string) ($credentials['protocol_version'] ?? '')));
        if ($raw === '' || $raw === '2' || $raw === '2.0' || $raw === 'v2') {
            return self::V2;
        }
        if ($raw === '3' || $raw === '3.0' || $raw === 'v3') {
            return self::V3;
        }

        return self::V2;
    }

    public function isV3(): bool
    {
        return $this === self::V3;
    }

    public function namespaceUri(): string
    {
        return match ($this) {
            self::V2 => 'http://zapways.com/air/ota/2.0',
            self::V3 => 'http://zapways.com/air/ota/3.0',
        };
    }

    public function searchPriority(): int
    {
        return match ($this) {
            self::V3 => 300,
            self::V2 => 200,
        };
    }
}
