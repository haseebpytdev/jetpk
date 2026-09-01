<?php

namespace App\Services\Visa;

use App\Support\Platform\PlatformModuleGate;

/**
 * Server-authoritative activation gate for live Saudi MOFA transport.
 */
final class VisaPolicyGate
{
    public function moduleEnabled(): bool
    {
        $configOn = (bool) config('visa.module_enabled', false);

        if (app()->environment('testing')) {
            return $configOn || (bool) config('visa.allow_in_testing', true);
        }

        try {
            $platformOn = PlatformModuleGate::visible('public_visa');
        } catch (\Throwable) {
            $platformOn = false;
        }

        return $configOn && $platformOn;
    }

    public function providerEnabled(): bool
    {
        return (bool) config('visa.saudi_mofa.provider_enabled', false);
    }

    public function policyApproved(): bool
    {
        return (bool) config('visa.saudi_mofa.policy_approved', false);
    }

    public function liveAllowed(): bool
    {
        return $this->moduleEnabled()
            && $this->providerEnabled()
            && $this->policyApproved()
            && (string) config('visa.saudi_mofa.transport', 'fixture') === 'live';
    }

    public function denyLiveReason(): ?string
    {
        if ($this->liveAllowed()) {
            return null;
        }
        if (! $this->policyApproved()) {
            return 'Written MOFA/policy approval has not been recorded.';
        }
        if (! $this->moduleEnabled()) {
            return 'Visa module is disabled.';
        }
        if (! $this->providerEnabled()) {
            return 'Saudi MOFA provider is disabled.';
        }

        return 'Live MOFA transport is not selected.';
    }
}
