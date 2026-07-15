<?php

namespace App\Enums;

enum AirBlueApiChannel: string
{
    case CraneNdc = 'crane_ndc';
    case ZapwaysOta = 'zapways_ota';

    public function label(): string
    {
        return match ($this) {
            self::CraneNdc => 'Crane NDC',
            self::ZapwaysOta => 'Zapways OTA',
        };
    }

    public static function fromCredentials(?array $credentials): self
    {
        $channel = strtolower(trim((string) ($credentials['api_channel'] ?? '')));

        return $channel === self::ZapwaysOta->value
            ? self::ZapwaysOta
            : self::CraneNdc;
    }
}
