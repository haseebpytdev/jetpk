<?php

namespace App\Enums;

enum AirBlueApiChannel: string
{
    /** @deprecated Hitit Crane NDC belongs to {@see SupplierProvider::PiaNdc}; retained for legacy row detection only. */
    case CraneNdc = 'crane_ndc';
    case ZapwaysOta = 'zapways_ota';

    public function label(): string
    {
        return match ($this) {
            self::CraneNdc => 'Crane NDC (deprecated — use PIA NDC)',
            self::ZapwaysOta => 'Zapways OTA',
        };
    }

    public function isDeprecated(): bool
    {
        return $this === self::CraneNdc;
    }

    public static function fromCredentials(?array $credentials): self
    {
        $channel = strtolower(trim((string) ($credentials['api_channel'] ?? '')));

        return match ($channel) {
            self::CraneNdc->value => self::CraneNdc,
            self::ZapwaysOta->value => self::ZapwaysOta,
            default => self::ZapwaysOta,
        };
    }
}
