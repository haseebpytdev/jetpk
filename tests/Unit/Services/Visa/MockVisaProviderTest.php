<?php

namespace Tests\Unit\Services\Visa;

use App\Services\Visa\Providers\MockVisaLookupProvider;
use App\Services\Visa\DTO\VisaLookupRequest;
use App\Services\Visa\Exceptions\CaptchaInvalid;
use App\Services\Visa\Exceptions\VisaNotFound;
use App\Services\Visa\Support\SaudiMofaResultParser;
use App\Services\Visa\Support\VisaRedactor;
use App\Services\Visa\VisaExportService;
use App\Services\Visa\VisaLookupSessionStore;
use App\Services\Visa\VisaPolicyGate;
use Illuminate\Support\Facades\Config;
use Tests\TestCase;

class MockVisaProviderTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        Config::set('visa.module_enabled', true);
        Config::set('visa.allow_in_testing', true);
        Config::set('visa.default_provider', 'mock');
        $this->startSession();
    }

    public function test_mock_success_lookup_and_exports(): void
    {
        $provider = app(MockVisaLookupProvider::class);
        $store = app(VisaLookupSessionStore::class);
        $session = $provider->startLookup();
        $row = $store->get($session->id, $session->ownerToken);
        $answer = (string) ($row['state']['captcha'] ?? '');

        $result = $provider->lookup($session, new VisaLookupRequest(
            'passport_number', 'XX9999999', 'visa_number', '9999000011', 'PAK', $answer, $session->id,
        ));

        $this->assertSame('issued', $result->status);
        $this->assertNotEmpty($result->documentRef);
        $this->assertSame('Umrah', $result->fields['visa_type'] ?? null);

        $doc = $provider->getDocument($session, (string) $result->documentRef);
        $this->assertSame('HTML', $doc->sourceType);
        $this->assertStringContainsString('text/html', $doc->mimeType);

        $exports = app(VisaExportService::class);
        $pdf = $exports->exportPdf($session->id, (string) $result->documentRef);
        $this->assertSame('application/pdf', $pdf->mimeType);
        $this->assertStringStartsWith('%PDF', $pdf->bytes);
        $this->assertSame('Visa PDF copy', $pdf->label);

        $png = $exports->exportPng($session->id, (string) $result->documentRef);
        $this->assertSame('image/png', $png->mimeType);
        $this->assertSame('Image copy of official visa document', $png->label);
    }

    public function test_mock_captcha_invalid_and_not_found(): void
    {
        $provider = app(MockVisaLookupProvider::class);
        $provider->setScenario('captcha_invalid');
        $session = $provider->startLookup();
        $row = app(VisaLookupSessionStore::class)->get($session->id, $session->ownerToken);
        // Force scenario in state
        $state = $row['state'];
        $state['scenario'] = 'captcha_invalid';
        app(VisaLookupSessionStore::class)->update($session->id, $session->ownerToken, $state);

        $this->expectException(CaptchaInvalid::class);
        $provider->lookup($session, new VisaLookupRequest(
            'passport_number', 'XX', 'visa_number', 'YY', 'PAK', (string) $state['captcha'], $session->id,
        ));
    }

    public function test_parser_reads_fixture_html(): void
    {
        $html = file_get_contents(base_path('tests/Fixtures/Visa/mofa/printed-umrah-visa.html'));
        $fields = (new SaudiMofaResultParser)->parse((string) $html);
        $this->assertSame('9999000011', $fields['visa_number']);
        $this->assertSame('XX9999999', $fields['passport_number']);
        $this->assertSame('Umrah', $fields['visa_type']);
    }

    public function test_redactor_masks_and_strips_scripts(): void
    {
        $r = new VisaRedactor;
        $this->assertSame('AB*******89', $r->maskIdentifier('AB123456789', 2, 2));
        $html = $r->stripScripts('<div onclick="x()">ok</div><script>alert(1)</script>');
        $this->assertStringNotContainsString('<script', $html);
        $this->assertStringNotContainsString('onclick=', $html);
    }

    public function test_not_found_scenario(): void
    {
        $provider = app(MockVisaLookupProvider::class);
        $session = $provider->startLookup();
        $store = app(VisaLookupSessionStore::class);
        $row = $store->get($session->id, $session->ownerToken);
        $state = $row['state'];
        $state['scenario'] = 'not_found';
        $store->update($session->id, $session->ownerToken, $state);
        $this->expectException(VisaNotFound::class);
        $provider->lookup($session, new VisaLookupRequest(
            'passport_number', 'XX', 'visa_number', 'YY', 'PAK', (string) $state['captcha'], $session->id,
        ));
    }
}
