<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class ProviderActiveAuthAuditCommandTest extends TestCase
{
    use RefreshDatabase;

    private const TEST_USERNAME = 'audit-alhaider-user@example.test';

    private const TEST_PASSWORD = 'audit-alhaider-password-secret';

    protected function setUp(): void
    {
        parent::setUp();

        Cache::flush();

        Config::set('suppliers.al_haider.enabled', true);
        Config::set('suppliers.al_haider.token', '');
        Config::set('suppliers.al_haider.username', self::TEST_USERNAME);
        Config::set('suppliers.al_haider.password', self::TEST_PASSWORD);
        Config::set('suppliers.al_haider.default_base_url', 'https://alhaider.test');
    }

    public function test_default_audit_is_read_only_and_masks_secrets(): void
    {
        Http::fake();

        $exit = Artisan::call('provider:active-auth-audit', ['--provider' => 'alhaider']);
        $out = Artisan::output();

        $this->assertSame(0, $exit);
        $this->assertStringContainsString('classification=READ-ONLY', $out);
        $this->assertStringContainsString('live_http=false', $out);
        $this->assertStringContainsString('token_cache_key=alhaider:auth_token', $out);
        $this->assertStringContainsString('credential_source=env', $out);
        $this->assertStringContainsString('forces_fresh_token_on_public_search=false', $out);
        $this->assertNoSecretsPrinted($out);

        Http::assertNothingSent();
    }

    public function test_send_requires_confirm_phrase(): void
    {
        $exit = Artisan::call('provider:active-auth-audit', [
            '--provider' => 'alhaider',
            '--send' => true,
        ]);

        $this->assertSame(1, $exit);
    }

    public function test_send_probe_reports_status_without_printing_token(): void
    {
        Http::fake([
            'alhaider.test/api/login' => Http::response(['token' => 'probe-token-must-not-print'], 200),
        ]);

        $exit = Artisan::call('provider:active-auth-audit', [
            '--provider' => 'alhaider',
            '--send' => true,
            '--confirm' => 'READONLY-PROVIDER-AUTH-AUDIT',
        ]);
        $out = Artisan::output();

        $this->assertSame(0, $exit);
        $this->assertStringContainsString('token_obtained=true', $out);
        $this->assertStringContainsString('http_status=200', $out);
        $this->assertNoSecretsPrinted($out);
    }

    private function assertNoSecretsPrinted(string $output): void
    {
        $this->assertStringNotContainsString(self::TEST_PASSWORD, $output);
        $this->assertStringNotContainsString(self::TEST_USERNAME, $output);
        $this->assertStringNotContainsString('probe-token-must-not-print', $output);
        $this->assertStringNotContainsString('Bearer ', $output);
    }
}
