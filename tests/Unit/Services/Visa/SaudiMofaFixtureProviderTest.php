<?php

namespace Tests\Unit\Services\Visa;

use App\Services\Visa\DTO\VisaLookupRequest;
use App\Services\Visa\Providers\SaudiMofaVisaProvider;
use App\Services\Visa\Transport\FixtureVisaHttpTransport;
use App\Services\Visa\VisaLookupSessionStore;
use Illuminate\Support\Facades\Config;
use Tests\TestCase;

class SaudiMofaFixtureProviderTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        Config::set('visa.module_enabled', true);
        Config::set('visa.saudi_mofa.transport', 'fixture');
        Config::set('visa.saudi_mofa.policy_approved', false);
        Config::set('visa.default_provider', 'saudi_mofa');
        $this->startSession();
    }

    public function test_fixture_protocol_success_without_live_network(): void
    {
        $provider = app(SaudiMofaVisaProvider::class);
        $this->assertInstanceOf(FixtureVisaHttpTransport::class, app(\App\Services\Visa\Transport\VisaHttpTransport::class));

        $session = $provider->startLookup();
        $captcha = $provider->captcha($session);
        $this->assertNotSame('', $captcha->imageBase64);

        $result = $provider->lookup($session, new VisaLookupRequest(
            firstCriterion: 'passport_number',
            firstValue: 'XX9999999',
            secondCriterion: 'visa_number',
            secondValue: '9999000011',
            nationality: 'PAK',
            captchaAnswer: 'ignore',
            lookupSessionId: $session->id,
        ));

        $this->assertSame('issued', $result->status);
        $this->assertSame('9999000011', $result->fields['visa_number'] ?? null);
        $doc = $provider->getDocument($session, (string) $result->documentRef);
        $this->assertSame(hash('sha256', $doc->bytes), $doc->sha256);
    }

    public function test_session_isolation_rejects_other_owner(): void
    {
        $provider = app(SaudiMofaVisaProvider::class);
        $session = $provider->startLookup();
        $store = app(VisaLookupSessionStore::class);
        $this->assertNull($store->get($session->id, 'other-owner-token'));
    }
}
