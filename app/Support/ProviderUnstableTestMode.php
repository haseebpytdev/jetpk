<?php

namespace App\Support;

/**
 * Gates the local/testing-only cached-pricing checkout fallback when live supplier validation fails.
 */
final class ProviderUnstableTestMode
{
    /**
     * Testing always allows (unless overridden). Local requires OTA_ALLOW_PROVIDER_UNSTABLE_LOCAL / config.
     * Staging and production never allow.
     */
    public static function isCheckoutFallbackAllowed(?string $environment = null): bool
    {
        $env = $environment ?? (string) app()->environment();
        if (in_array($env, ['staging', 'production'], true)) {
            return false;
        }
        if ($env === 'testing') {
            return true;
        }
        if ($env === 'local') {
            return (bool) config('ota.allow_provider_unstable_local');
        }

        return false;
    }
}
