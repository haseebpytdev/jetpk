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
use App\Services\Visa\Exceptions\CaptchaInvalid;
use App\Services\Visa\Exceptions\PolicyBlocked;
use App\Services\Visa\Exceptions\ProviderChanged;
use App\Services\Visa\Exceptions\ProviderUnavailable;
use App\Services\Visa\Exceptions\SessionExpired;
use App\Services\Visa\Exceptions\VisaNotFound;
use App\Services\Visa\Support\SaudiMofaProviderSignature;
use App\Services\Visa\Support\SaudiMofaResultParser;
use App\Services\Visa\Support\VisaRedactor;
use App\Services\Visa\Transport\FixtureVisaHttpTransport;
use App\Services\Visa\Transport\VisaHttpTransport;
use App\Services\Visa\VisaLookupSessionStore;
use App\Services\Visa\VisaPolicyGate;
use Illuminate\Support\Str;

/**
 * Saudi MOFA Visa Platform provider (HTML printable result model).
 * Live network requires VisaPolicyGate::liveAllowed().
 */
final class SaudiMofaVisaProvider implements VisaLookupProvider
{
    private const CRITERION_MAP = [
        'visa_number' => 'VisaNo',
        'passport_number' => 'PassPortNo',
        'application_number' => 'AppNo',
        'moh_number' => 'MohNo',
        'first_name' => 'fName',
    ];

    public function __construct(
        private readonly VisaHttpTransport $transport,
        private readonly VisaLookupSessionStore $sessions,
        private readonly VisaPolicyGate $policyGate,
        private readonly SaudiMofaProviderSignature $signature,
        private readonly SaudiMofaResultParser $parser,
        private readonly VisaRedactor $redactor,
    ) {}

    public function key(): string
    {
        return 'saudi_mofa';
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
            serviceLabel: 'Saudi Visa Lookup',
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
        $live = $this->policyGate->liveAllowed();

        return new VisaProviderHealth(
            providerKey: $this->key(),
            moduleEnabled: $this->policyGate->moduleEnabled(),
            providerEnabled: $this->policyGate->providerEnabled(),
            policyApproved: $this->policyGate->policyApproved(),
            liveAllowed: $live,
            lookupCapable: true,
            captchaCapable: true,
            documentCapable: true,
            pdfExportCapable: true,
            imageExportCapable: true,
            status: $live ? 'live_ready' : 'policy_blocked_or_fixture',
            detail: $this->policyGate->denyLiveReason() ?? 'Live transport allowed.',
        );
    }

    public function startLookup(): VisaLookupSession
    {
        $this->assertTransportPermitted();
        $owner = $this->ownerToken();
        $ttl = (int) config('visa.session_ttl_seconds', 900);
        $base = rtrim((string) config('visa.saudi_mofa.base_url'), '/');
        $page = $this->transport->request('GET', $base.SaudiMofaProviderSignature::LOOKUP_PATH);
        $this->signature->assertLookupPage($page['body']);
        $csrf = $this->extractCsrf($page['body']);
        $cookies = $this->extractSetCookieNamesOnly($page['headers']);
        $captcha = $this->fetchCaptcha($base, $cookies);

        $state = [
            'provider' => $this->key(),
            'csrf' => $csrf,
            'cookies' => $cookies,
            'captcha_fetched_at' => time(),
            'captcha_expires' => time() + 300,
            'base' => $base,
        ];
        $id = $this->sessions->put($owner, $state, $ttl);
        // stash captcha bytes under session
        $state['captcha_mime'] = $captcha['mime'];
        $state['captcha_bytes'] = base64_encode($captcha['bytes']);
        $this->sessions->update($id, $owner, $state);

        return new VisaLookupSession($id, $this->key(), 'SA', time(), time() + $ttl, $owner);
    }

    public function refreshCaptcha(VisaLookupSession $session): VisaCaptchaChallenge
    {
        $this->assertTransportPermitted();
        $state = $this->requireState($session);
        $base = (string) ($state['base'] ?? config('visa.saudi_mofa.base_url'));
        $captcha = $this->fetchCaptcha($base, (array) ($state['cookies'] ?? []));
        $state['captcha_mime'] = $captcha['mime'];
        $state['captcha_bytes'] = base64_encode($captcha['bytes']);
        $state['captcha_expires'] = time() + 300;
        $this->sessions->update($session->id, $session->ownerToken, $state);

        return new VisaCaptchaChallenge($session->id, $captcha['mime'], base64_encode($captcha['bytes']), (int) $state['captcha_expires']);
    }

    public function captcha(VisaLookupSession $session): VisaCaptchaChallenge
    {
        $state = $this->requireState($session);
        if (empty($state['captcha_bytes'])) {
            return $this->refreshCaptcha($session);
        }

        return new VisaCaptchaChallenge(
            $session->id,
            (string) ($state['captcha_mime'] ?? 'image/jpeg'),
            (string) $state['captcha_bytes'],
            (int) ($state['captcha_expires'] ?? time()),
        );
    }

    public function lookup(VisaLookupSession $session, VisaLookupRequest $request): VisaLookupResult
    {
        $this->assertTransportPermitted();
        $state = $this->requireState($session);
        if (($state['captcha_expires'] ?? 0) < time()) {
            throw new CaptchaInvalid('Captcha expired.');
        }

        $first = self::CRITERION_MAP[$request->firstCriterion] ?? null;
        $second = self::CRITERION_MAP[$request->secondCriterion] ?? null;
        if ($first === null || $second === null || $first === $second) {
            throw new ProviderChanged('Unsupported or duplicate lookup criteria.');
        }

        $base = (string) ($state['base'] ?? config('visa.saudi_mofa.base_url'));
        $body = http_build_query([
            'ReaderType' => '1',
            'ddlFirstValue' => $first,
            'tbFirstValue' => $request->firstValue,
            'ddlSecondValue' => $second,
            'tbSecondValue' => $request->secondValue,
            'NationalityId' => $request->nationality,
            'Captcha' => $request->captchaAnswer,
            '__RequestVerificationToken' => (string) ($state['csrf'] ?? ''),
            'submit' => '1',
        ]);

        try {
            $post = $this->transport->request(
                'POST',
                $base.SaudiMofaProviderSignature::LOOKUP_PATH,
                [
                    'Content-Type' => 'application/x-www-form-urlencoded',
                    'Origin' => $base,
                    'Referer' => $base.SaudiMofaProviderSignature::LOOKUP_PATH,
                ],
                $body,
                false,
            );
        } catch (PolicyBlocked $e) {
            throw $e;
        } catch (\Throwable $e) {
            throw new ProviderUnavailable('MOFA lookup failed.');
        }

        if ((int) $post['status'] !== 302) {
            // Fixture/mock styles may return 200 form again for captcha/not-found.
            if ((int) $post['status'] === 200 && str_contains($post['body'], 'id="myform"')) {
                throw new CaptchaInvalid('Lookup rejected; captcha or fields invalid.');
            }
            throw new ProviderChanged('Unexpected MOFA lookup response status.');
        }

        $location = (string) ($post['headers']['location'] ?? '');
        $path = parse_url($location, PHP_URL_PATH) ?: $location;
        if ($path !== SaudiMofaProviderSignature::RESULT_PATH) {
            throw new ProviderChanged('Unexpected MOFA redirect destination.');
        }

        $result = $this->transport->request('GET', $base.$path, [], null, false);
        if ((int) $result['status'] !== 200) {
            throw new VisaNotFound('Visa document page unavailable.');
        }
        $this->signature->assertResultPage($result['body'], $path);
        $fields = $this->parser->parse($result['body']);
        if (empty(array_filter($fields))) {
            throw new ProviderChanged('Unable to parse MOFA result fields.');
        }

        $docRef = 'doc_'.Str::random(24);
        $safeHtml = $this->redactor->stripScripts($result['body']);
        $state['document'] = [
            'ref' => $docRef,
            'html' => $safeHtml,
            'fields' => $fields,
            'sha256' => hash('sha256', $safeHtml),
        ];
        // Clear captcha answer material
        unset($state['captcha_bytes']);
        $this->sessions->update($session->id, $session->ownerToken, $state);

        return new VisaLookupResult(
            lookupSessionId: $session->id,
            providerKey: $this->key(),
            countryCode: 'SA',
            status: 'issued',
            fields: $fields + ['status' => 'issued'],
            documentRef: $docRef,
            sourceAttribution: 'Visa information supplied by the Saudi Ministry of Foreign Affairs.',
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
            sha256: (string) ($doc['sha256'] ?? hash('sha256', $html)),
            attribution: 'Official MOFA visa result page.',
        );
    }

    private function assertTransportPermitted(): void
    {
        if ($this->transport instanceof FixtureVisaHttpTransport) {
            return;
        }
        if (! $this->policyGate->liveAllowed()) {
            throw new PolicyBlocked($this->policyGate->denyLiveReason() ?? 'Live MOFA denied.');
        }
    }

    /**
     * @param  array<string, mixed>  $cookies
     * @return array{mime:string,bytes:string}
     */
    private function fetchCaptcha(string $base, array $cookies): array
    {
        $url = $base.SaudiMofaProviderSignature::CAPTCHA_PATH_PREFIX.'?'.mt_rand() / mt_getrandmax();
        $res = $this->transport->request('GET', $url);
        if ((int) $res['status'] !== 200) {
            throw new ProviderUnavailable('Captcha unavailable.');
        }

        return [
            'mime' => $res['headers']['content-type'] ?? 'image/jpeg',
            'bytes' => $res['body'],
        ];
    }

    private function extractCsrf(string $html): string
    {
        if (preg_match('/name="__RequestVerificationToken"[^>]*value="([^"]+)"/', $html, $m)) {
            return $m[1];
        }
        throw new ProviderChanged('CSRF token missing from MOFA page.');
    }

    /**
     * @param  array<string, string>  $headers
     * @return array<string, string>
     */
    private function extractSetCookieNamesOnly(array $headers): array
    {
        // Cookie values are not persisted into evidence; session jar stays encrypted in cache state via transport abstraction.
        return ['present' => isset($headers['set-cookie']) ? 'yes' : 'no'];
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
}
