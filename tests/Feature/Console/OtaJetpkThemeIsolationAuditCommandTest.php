<?php

namespace Tests\Feature\Console;

use App\Models\ClientProfile;
use App\Models\ClientProfileModule;
use App\Support\Client\ClientProfileConfigReader;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Http;
use Tests\Support\JetpkHomepageFixture;
use Tests\TestCase;

class OtaJetpkThemeIsolationAuditCommandTest extends TestCase
{
    use JetpkHomepageFixture;
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Http::fake();
        $this->seedJetpkAirports();
        $this->seedJetpkAgency();
        $this->makeJetpkProfile();
        Config::set('ota_client.single_client_mode', true);
        Config::set('ota_client.single_client_root', true);
        Config::set('ota_client.slug', 'jetpk');
    }

    public function test_audit_passes_home_and_login_without_stylesheet_warnings_on_single_client_root(): void
    {
        $this->artisan('ota:jetpk-theme-isolation-audit', ['--client' => 'jetpk'])
            ->expectsOutputToContain('Classification: READ-ONLY JetPK theme isolation audit.')
            ->expectsOutputToContain('Summary: fail=0 warn=0')
            ->assertSuccessful();

        Http::assertNothingSent();
    }

    public function test_homepage_dispatch_includes_versioned_v58_stylesheet(): void
    {
        $html = $this->get('/')->assertOk()->getContent();
        $this->assertIsString($html);
        $this->assertStringContainsString('/themes/frontend/jetpakistan/css/theme.css?v=58', $html);
        $this->assertStringContainsString('/themes/frontend/jetpakistan/css/tokens.css?v=58', $html);
        Http::assertNothingSent();
    }

    public function test_login_dispatch_includes_jetpakistan_auth_stylesheets(): void
    {
        $html = $this->get('/login')->assertOk()->getContent();
        $this->assertIsString($html);
        $this->assertStringContainsString('/themes/frontend/jetpakistan/css/theme.css?v=58', $html);
        $this->assertStringContainsString('/themes/frontend/jetpakistan/css/forms.css?v=58', $html);
        $this->assertStringContainsString('name="csrf-token"', $html);
        $this->assertStringContainsString('name="_token"', $html);
        $this->assertStringContainsString('method="POST"', $html);
        $this->assertStringContainsString('data-jp-login-form', $html);
        Http::assertNothingSent();
    }

    public function test_stylesheet_classifier_accepts_versioned_absolute_urls(): void
    {
        $command = app(\App\Console\Commands\OtaJetpkThemeIsolationAuditCommand::class);
        $method = new \ReflectionMethod($command, 'hasJetpkStylesheetReference');
        $method->setAccessible(true);

        $html = '<link rel="stylesheet" href="https://jetpakistan.com/themes/frontend/jetpakistan/css/theme.css?v=58">';

        $this->assertTrue($method->invoke($command, $html));
    }

    public function test_stylesheet_classifier_still_detects_master_client_css(): void
    {
        $command = app(\App\Console\Commands\OtaJetpkThemeIsolationAuditCommand::class);
        $count = new \ReflectionMethod($command, 'countPatterns');
        $count->setAccessible(true);

        $html = '<link rel="stylesheet" href="/css/bootstrap.min.css">';

        $this->assertGreaterThan(0, $count->invoke($command, $html, [
            'ota-public.css',
            '/css/bootstrap',
        ]));
    }

    public function test_stylesheet_classifier_still_detects_other_client_theme_paths(): void
    {
        $command = app(\App\Console\Commands\OtaJetpkThemeIsolationAuditCommand::class);
        $method = new \ReflectionMethod($command, 'countOtherClientAssets');
        $method->setAccessible(true);

        $html = '<link rel="stylesheet" href="/themes/frontend/parwaaz/css/theme.css">';

        $this->assertGreaterThan(0, $method->invoke($command, $html));
    }

    public function test_missing_stylesheet_detection_flags_pages_without_jetpk_css(): void
    {
        $command = app(\App\Console\Commands\OtaJetpkThemeIsolationAuditCommand::class);
        $method = new \ReflectionMethod($command, 'missingJetpkStylesheet');
        $method->setAccessible(true);

        $this->assertTrue($method->invoke($command, '<html><body>no styles</body></html>', false));
        $this->assertFalse($method->invoke(
            $command,
            '<link rel="stylesheet" href="/themes/frontend/jetpakistan/css/forms.css?v=58">',
            false,
        ));
    }

    public function test_broken_entity_detection_flags_double_encoded_entities_only(): void
    {
        $command = app(\App\Console\Commands\OtaJetpkThemeIsolationAuditCommand::class);
        $method = new \ReflectionMethod($command, 'countBrokenEntities');
        $method->setAccessible(true);

        $this->assertSame(0, $method->invoke($command, '<span title="&#039;">ok</span>'));
        $this->assertGreaterThan(0, $method->invoke($command, '<span>&amp;nbsp;visible</span>'));
    }

    public function test_prefixed_home_alias_resolves_to_root_on_single_client_root(): void
    {
        $command = app(\App\Console\Commands\OtaJetpkThemeIsolationAuditCommand::class);
        $method = new \ReflectionMethod($command, 'resolveAuditUri');
        $method->setAccessible(true);

        $this->assertSame('/', $method->invoke($command, 'jetpk', '/home'));
        $this->assertSame('/login', $method->invoke($command, 'jetpk', '/login'));
    }
}
