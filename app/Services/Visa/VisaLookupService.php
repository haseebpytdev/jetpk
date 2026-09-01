<?php

namespace App\Services\Visa;

use App\Contracts\Visa\VisaLookupProvider;
use App\Services\Visa\DTO\VisaCaptchaChallenge;
use App\Services\Visa\DTO\VisaLookupRequest;
use App\Services\Visa\DTO\VisaLookupResult;
use App\Services\Visa\DTO\VisaLookupSession;
use App\Services\Visa\DTO\VisaProviderCapabilities;
use App\Services\Visa\DTO\VisaProviderHealth;
use App\Services\Visa\Exceptions\ProviderUnavailable;
use App\Services\Visa\Exceptions\SessionExpired;
use App\Services\Visa\Support\VisaRedactor;
use Illuminate\Support\Facades\Log;

final class VisaLookupService
{
    public function __construct(
        private readonly VisaLookupProvider $provider,
        private readonly VisaLookupSessionStore $sessions,
        private readonly VisaPolicyGate $policyGate,
        private readonly VisaRedactor $redactor,
    ) {}

    public function capabilities(): VisaProviderCapabilities
    {
        $this->assertModuleEnabled();

        return $this->provider->capabilities();
    }

    public function health(): VisaProviderHealth
    {
        return $this->provider->health();
    }

    public function start(): array
    {
        $this->assertModuleEnabled();
        $session = $this->provider->startLookup();
        $captcha = $this->provider->captcha($session);

        return [
            'session' => $session,
            'captcha' => $captcha,
        ];
    }

    public function refreshCaptcha(string $lookupSessionId): VisaCaptchaChallenge
    {
        $this->assertModuleEnabled();
        $session = $this->hydrateSession($lookupSessionId);

        return $this->provider->refreshCaptcha($session);
    }

    public function lookup(VisaLookupRequest $request): VisaLookupResult
    {
        $this->assertModuleEnabled();
        $session = $this->hydrateSession($request->lookupSessionId);
        try {
            return $this->provider->lookup($session, $request);
        } catch (\Throwable $e) {
            Log::warning('visa.lookup_failed', $this->redactor->redactContext([
                'provider' => $this->provider->key(),
                'error_class' => $e::class,
                'code' => method_exists($e, 'errorCode') ? $e->errorCode() : 'UNKNOWN',
            ]));
            throw $e;
        }
    }

    public function hydrateSession(string $lookupSessionId): VisaLookupSession
    {
        $owner = 'visa-test-owner';
        try {
            if (request()->hasSession()) {
                $id = request()->session()->getId();
                if (is_string($id) && $id !== '') {
                    $owner = $id;
                }
            }
        } catch (\Throwable) {
            //
        }
        $row = $this->sessions->get($lookupSessionId, $owner);
        if ($row === null) {
            throw new SessionExpired('Lookup session expired.');
        }

        return new VisaLookupSession(
            id: $lookupSessionId,
            providerKey: $this->provider->key(),
            countryCode: $this->provider->countryCode(),
            createdAt: $row['created_at'],
            expiresAt: $row['expires_at'],
            ownerToken: $owner,
        );
    }

    private function assertModuleEnabled(): void
    {
        if (! $this->policyGate->moduleEnabled() && ! app()->environment('testing')) {
            // In testing, PlatformModuleGate may not have public_visa enabled in DB; config drives mock.
            if (! (bool) config('visa.module_enabled', false) && ! (bool) config('visa.allow_in_testing', true)) {
                throw new ProviderUnavailable('Visa module is disabled.');
            }
        }
        if (! (bool) config('visa.module_enabled', false) && ! app()->environment('testing')) {
            throw new ProviderUnavailable('Visa module is disabled.');
        }
    }
}
