<?php

namespace App\Enums;

enum IntegrationOperationalStatus: string
{
    case Connected = 'connected';
    case Degraded = 'degraded';
    case AuthenticationFailed = 'authentication_failed';
    case NotConfigured = 'not_configured';
    case Disabled = 'disabled';
    case NeverTested = 'never_tested';
    case AdapterMissing = 'adapter_missing';
    case Draft = 'draft';

    public function label(): string
    {
        return match ($this) {
            self::Connected => 'Connected',
            self::Degraded => 'Degraded',
            self::AuthenticationFailed => 'Authentication failed',
            self::NotConfigured => 'Not configured',
            self::Disabled => 'Disabled',
            self::NeverTested => 'Never tested',
            self::AdapterMissing => 'Adapter not installed',
            self::Draft => 'Draft',
        };
    }
}
