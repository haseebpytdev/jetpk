<?php

namespace App\Enums;

enum IntegrationHealthStatus: string
{
    case Healthy = 'healthy';
    case Degraded = 'degraded';
    case AuthFailed = 'auth_failed';
    case NetworkFailed = 'network_failed';
    case ConfigurationIncomplete = 'configuration_incomplete';
    case ProviderError = 'provider_error';
    case Disabled = 'disabled';
    case NeverTested = 'never_tested';
}
