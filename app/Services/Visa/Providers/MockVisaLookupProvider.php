<?php

namespace App\Services\Visa\Providers;

use App\Contracts\Visa\VisaLookupProvider;
use App\Services\Visa\DTO\VisaCaptchaChallenge;
use App\Services\Visa\DTO\VisaDocument;
use App\Services\Visa\DTO\VisaLookupRequest;
use App\Services\Visa\DTO\VisaLookupResult;
use App\Services\Visa\DTO\VisaLookupSession;
use App\Services\Visa\DTO\VisaProviderCapabilities;
use App\Services\Visa\DTO\VisaProviderHealth;
use App\Services\Visa\Exceptions\CaptchaExpired;
use App\Services\Visa\Exceptions\CaptchaInvalid;
use App\Services\Visa\Exceptions\ProviderChanged;
use App\Services\Visa\Exceptions\ProviderUnavailable;
use App\Services\Visa\Exceptions\SessionExpired;
use App\Services\Visa\Exceptions\VisaNotFound;
use App\Services\Visa\VisaLookupSessionStore;
use App\Services\Visa\VisaPolicyGate;
use Illuminate\Support\Str;

/**
 * Deterministic mock provider for local/dev/tests. Synthetic data only.
 */
final class MockVisaLookupProvider implements VisaLookupProvider
{
    public function __construct(
        private readonly VisaLookupSessionStore $sessions,
        private readonly VisaPolicyGate $policyGate,
        private string $scenario = 'success',
    ) {}

    public function setScenario(string $scenario): void
    {
        $this->scenario = $scenario;
    }

    public function key(): string
    {
        return 'mock_visa';
    }

    public function countryCode(): string
    {
        return 'SA';
    }

    public function capabilities(): VisaProviderCapabilities
    {
        return new VisaProviderCapabilities(
            providerKey: $this->key(),
            countryCode: 'SA',
            countryLabel: 'Saudi Arabia',
            serviceLabel: 'Saudi Visa Lookup (Mock)',
            criteria: [
                ['key' => 'passport_number', 'label' => 'Passport Number'],
                ['key' => 'visa_number', 'label' => 'Visa Number'],
                ['key' => 'application_number', 'label' => 'Application Number'],
                ['key' => 'moh_number', 'label' => 'MOH Number'],
                ['key' => 'first_name', 'label' => 'First Name'],
            ],
            nationalityRequired: true,
            captchaRequired: true,
            documentSourceType: 'HTML',
            exportFormats: ['pdf', 'png'],
            officialFallbackUrl: (string) config('visa.saudi_mofa.official_fallback_url'),
        );
    }

    public function health(): VisaProviderHealth
    {
        return new VisaProviderHealth(
            providerKey: $this->key(),
            moduleEnabled: $this->policyGate->moduleEnabled(),
            providerEnabled: true,
            policyApproved: $this->policyGate->policyApproved(),
            liveAllowed: false,
            lookupCapable: true,
            captchaCapable: true,
            documentCapable: true,
            pdfExportCapable: true,
            imageExportCapable: true,
            status: 'mock',
            detail: 'Deterministic mock provider active.',
        );
    }

    public function startLookup(): VisaLookupSession
    {
        $owner = $this->ownerToken();
        $ttl = (int) config('visa.session_ttl_seconds', 900);
        $state = [
            'provider' => $this->key(),
            'captcha' => 'MOCK1',
            'captcha_expires' => time() + 300,
            'scenario' => $this->scenario,
        ];
        $id = $this->sessions->put($owner, $state, $ttl);

        return new VisaLookupSession($id, $this->key(), 'SA', time(), time() + $ttl, $owner);
    }

    public function refreshCaptcha(VisaLookupSession $session): VisaCaptchaChallenge
    {
        return $this->rotateCaptcha($session);
    }

    public function captcha(VisaLookupSession $session): VisaCaptchaChallenge
    {
        $state = $this->requireState($session);
        if (($state['captcha_expires'] ?? 0) < time()) {
            return $this->rotateCaptcha($session);
        }

        return $this->challengeFromState($session->id, $state);
    }

    public function lookup(VisaLookupSession $session, VisaLookupRequest $request): VisaLookupResult
    {
        $state = $this->requireState($session);
        $scenario = (string) ($state['scenario'] ?? $this->scenario);

        if ($scenario === 'provider_unavailable') {
            throw new ProviderUnavailable('Mock provider unavailable.');
        }
        if ($scenario === 'provider_changed') {
            throw new ProviderChanged('Mock provider signature changed.');
        }
        if ($scenario === 'session_expired') {
            throw new SessionExpired('Mock session expired.');
        }
        if (($state['captcha_expires'] ?? 0) < time() || $scenario === 'captcha_expired') {
            throw new CaptchaExpired('Captcha expired.');
        }
        if (! hash_equals((string) ($state['captcha'] ?? ''), $request->captchaAnswer) || $scenario === 'captcha_invalid') {
            throw new CaptchaInvalid('Captcha invalid.');
        }
        if ($scenario === 'not_found') {
            throw new VisaNotFound('Visa not found.');
        }

        $docRef = 'doc_'.Str::random(24);
        $fields = [
            'visa_number' => '9999000011',
            'date_of_issue' => '01/01/2026',
            'valid_until' => '31/12/2026',
            'duration_of_stay' => '30 Days',
            'passport_number' => 'XX9999999',
            'place_of_issue' => 'Fixture Embassy',
            'name' => 'FIXTURE TRAVELER',
            'birth_date' => '01/01/1990',
            'nationality' => 'Pakistan',
            'visa_type' => 'Umrah',
            'umrah_operator' => 'Fixture Operator',
            'external_agent' => 'Fixture Agent',
            'application_number' => 'E000000001',
            'status' => 'issued',
        ];
        $html = $this->syntheticHtml($fields);
        $state['document'] = [
            'ref' => $docRef,
            'html' => $html,
            'fields' => $fields,
        ];
        $this->sessions->update($session->id, $session->ownerToken, $state);

        return new VisaLookupResult(
            lookupSessionId: $session->id,
            providerKey: $this->key(),
            countryCode: 'SA',
            status: 'issued',
            fields: $fields,
            documentRef: $docRef,
            sourceAttribution: 'Visa information supplied by the Saudi Ministry of Foreign Affairs (mock fixture).',
            expiresAt: $session->expiresAt,
        );
    }

    public function getDocument(VisaLookupSession $session, string $documentRef): VisaDocument
    {
        $state = $this->requireState($session);
        $doc = $state['document'] ?? null;
        if (! is_array($doc) || ($doc['ref'] ?? null) !== $documentRef) {
            throw new SessionExpired('Document token expired or invalid.');
        }
        $html = (string) $doc['html'];

        return new VisaDocument(
            ref: $documentRef,
            sourceType: 'HTML',
            mimeType: 'text/html; charset=utf-8',
            bytes: $html,
            sha256: hash('sha256', $html),
            attribution: 'Official MOFA visa result page (mock).',
        );
    }

    private function rotateCaptcha(VisaLookupSession $session): VisaCaptchaChallenge
    {
        $state = $this->requireState($session);
        $state['captcha'] = 'MOCK'.random_int(1, 9);
        $state['captcha_expires'] = time() + 300;
        $this->sessions->update($session->id, $session->ownerToken, $state);

        return $this->challengeFromState($session->id, $state);
    }

    /**
     * @param  array<string, mixed>  $state
     */
    private function challengeFromState(string $sessionId, array $state): VisaCaptchaChallenge
    {
        $png = base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAYAAAAfFcSJAAAADUlEQVR42mP8z8BQDwAEhQGAhKmMIQAAAABJRU5ErkJggg==');

        return new VisaCaptchaChallenge(
            lookupSessionId: $sessionId,
            mimeType: 'image/png',
            imageBase64: base64_encode($png !== false ? $png : 'x'),
            expiresAt: (int) ($state['captcha_expires'] ?? time()),
        );
    }

    /**
     * @return array<string, mixed>
     */
    private function requireState(VisaLookupSession $session): array
    {
        $row = $this->sessions->get($session->id, $session->ownerToken);
        if ($row === null) {
            throw new SessionExpired('Lookup session expired.');
        }

        return $row['state'];
    }

    private function ownerToken(): string
    {
        try {
            if (! request()->hasSession()) {
                return 'visa-test-owner';
            }
            $id = request()->session()->getId();
            if (is_string($id) && $id !== '') {
                return $id;
            }
        } catch (\Throwable) {
            //
        }

        return 'visa-test-owner';
    }

    /**
     * @param  array<string, string|null>  $fields
     */
    private function syntheticHtml(array $fields): string
    {
        $rows = '';
        foreach ($fields as $k => $v) {
            $rows .= '<div data-field="'.e($k).'">'.e((string) $v).'</div>';
        }

        return '<!DOCTYPE html><html><body>'.$rows.'</body></html>';
    }
}
