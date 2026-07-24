<?php

namespace Tests\Unit\Support\Client\Homepage;

use App\Enums\ClientPageSettingStatus;
use App\Models\ClientPageSetting;
use App\Models\ClientProfile;
use App\Services\Client\CurrentClientContext;
use App\Support\Client\Homepage\JetpkHomepageContextDiagnostic;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Tests\TestCase;

class JetpkHomepageContextDiagnosticTest extends TestCase
{
    use RefreshDatabase;

    private function bindProfile(ClientProfile $profile): void
    {
        app(CurrentClientContext::class)->set($profile);
        config(['ota_client.slug' => $profile->slug]);
    }

    private function makeProfile(string $theme = 'jetpakistan'): ClientProfile
    {
        return ClientProfile::query()->create([
            'name' => 'Diagnostic Test Client',
            'slug' => 'diagnostic-test-'.uniqid(),
            'environment' => 'staging',
            'active_frontend_theme' => $theme,
            'asset_profile' => 'test-assets',
            'default_locale' => 'en',
            'timezone' => 'Asia/Karachi',
            'currency' => 'PKR',
            'is_master_profile' => false,
            'is_active' => true,
        ]);
    }

    /** Disabled by default: no config set at all (relies on the env() default of false). */
    public function test_disabled_by_default_logs_nothing(): void
    {
        $profile = $this->makeProfile();
        $this->bindProfile($profile);
        // Deliberately not setting jetpk_homepage.context_diagnostic_enabled — must default false.

        Log::spy();

        app(JetpkHomepageContextDiagnostic::class)->logIfEnabled(Request::create('https://jetpakistan.pk/'));

        Log::shouldNotHaveReceived('info');
    }

    public function test_explicitly_disabled_logs_nothing_even_with_a_matching_jetpk_profile(): void
    {
        config(['jetpk_homepage.context_diagnostic_enabled' => false]);
        $profile = $this->makeProfile('jetpakistan');
        $this->bindProfile($profile);

        Log::spy();
        app(JetpkHomepageContextDiagnostic::class)->logIfEnabled(Request::create('https://jetpakistan.pk/'));
        Log::shouldNotHaveReceived('info');
    }

    /** JetPK homepage only — enabling the flag must not fire for a non-JetPakistan-themed tenant sharing this codebase. */
    public function test_enabled_but_non_jetpakistan_theme_logs_nothing(): void
    {
        config(['jetpk_homepage.context_diagnostic_enabled' => true]);
        $profile = $this->makeProfile('v1-classic');
        $this->bindProfile($profile);

        Log::spy();
        app(JetpkHomepageContextDiagnostic::class)->logIfEnabled(Request::create('https://some-other-client.example/'));
        Log::shouldNotHaveReceived('info');
    }

    public function test_enabled_and_jetpakistan_theme_logs_exactly_once(): void
    {
        config(['jetpk_homepage.context_diagnostic_enabled' => true]);
        $profile = $this->makeProfile('jetpakistan');
        $this->bindProfile($profile);
        ClientPageSetting::query()->create([
            'client_profile_id' => $profile->id,
            'page_key' => \App\Support\Client\ClientPageKeys::HOME,
            'status' => ClientPageSettingStatus::Published,
            'content_json' => ['hero' => ['headline' => 'Should never appear in the log']],
        ]);

        Log::spy();
        app(JetpkHomepageContextDiagnostic::class)->logIfEnabled(Request::create('https://jetpakistan.pk/'));
        Log::shouldHaveReceived('info')->once();
    }

    /** The exact safe field set — nothing more, nothing forbidden. */
    public function test_logs_only_the_exact_safe_field_set(): void
    {
        config(['jetpk_homepage.context_diagnostic_enabled' => true]);
        $profile = $this->makeProfile('jetpakistan');
        $this->bindProfile($profile);
        $row = ClientPageSetting::query()->create([
            'client_profile_id' => $profile->id,
            'page_key' => \App\Support\Client\ClientPageKeys::HOME,
            'status' => ClientPageSettingStatus::Published,
            'content_json' => ['hero' => ['headline' => 'SECRET-CONTENT-MUST-NOT-LEAK']],
        ]);

        $captured = null;
        Log::listen(function ($event) use (&$captured) {
            if ($event->message === 'jetpk_cms_context_diagnostic') {
                $captured = $event->context;
            }
        });

        app(JetpkHomepageContextDiagnostic::class)->logIfEnabled(Request::create('https://jetpakistan.pk/'));

        $this->assertNotNull($captured);

        $expectedKeys = [
            'request_host', 'resolved_client_profile_id', 'resolved_client_slug', 'page_key',
            'published_row_status', 'published_row_id', 'published_row_client_profile_id',
            'content_exists', 'content_top_level_keys', 'content_checksum', 'schema_version',
        ];
        sort($expectedKeys);
        $actualKeys = array_keys($captured);
        sort($actualKeys);
        $this->assertSame($expectedKeys, $actualKeys, 'Exactly this field set, no more, no less');

        // Positive assertions on a few values.
        $this->assertSame('jetpakistan.pk', $captured['request_host']);
        $this->assertSame($profile->id, $captured['resolved_client_profile_id']);
        $this->assertSame($row->id, $captured['published_row_id']);
        $this->assertTrue($captured['content_exists']);
        $this->assertSame(['hero'], $captured['content_top_level_keys']);

        // Negative assertions: the forbidden categories must not appear anywhere in the payload.
        $serialized = json_encode($captured);
        $this->assertStringNotContainsString('SECRET-CONTENT-MUST-NOT-LEAK', $serialized, 'Full content must never be logged, only top-level key names');
        $this->assertStringNotContainsString('password', strtolower($serialized));
        $this->assertStringNotContainsString('cookie', strtolower($serialized));
        $this->assertStringNotContainsString('session', strtolower($serialized));
    }

    public function test_checksum_and_schema_version_present_and_correct_type(): void
    {
        config(['jetpk_homepage.context_diagnostic_enabled' => true]);
        $profile = $this->makeProfile('jetpakistan');
        $this->bindProfile($profile);
        ClientPageSetting::query()->create([
            'client_profile_id' => $profile->id,
            'page_key' => \App\Support\Client\ClientPageKeys::HOME,
            'status' => ClientPageSettingStatus::Published,
            'content_json' => ['hero' => ['headline' => 'x']],
        ]);

        $captured = null;
        Log::listen(function ($event) use (&$captured) {
            if ($event->message === 'jetpk_cms_context_diagnostic') {
                $captured = $event->context;
            }
        });

        app(JetpkHomepageContextDiagnostic::class)->logIfEnabled(Request::create('https://jetpakistan.pk/'));

        $this->assertNotNull($captured);
        $this->assertIsString($captured['content_checksum']);
        $this->assertSame(64, strlen($captured['content_checksum']), 'sha256 hex digest is 64 chars');
        $this->assertSame(\App\Support\Client\Homepage\HomepageContentNormalizer::SCHEMA_VERSION, $captured['schema_version']);
    }

    public function test_no_published_row_reports_not_found_without_error(): void
    {
        config(['jetpk_homepage.context_diagnostic_enabled' => true]);
        $profile = $this->makeProfile('jetpakistan');
        $this->bindProfile($profile);
        // No ClientPageSetting row created at all.

        $captured = null;
        Log::listen(function ($event) use (&$captured) {
            if ($event->message === 'jetpk_cms_context_diagnostic') {
                $captured = $event->context;
            }
        });

        app(JetpkHomepageContextDiagnostic::class)->logIfEnabled(Request::create('https://jetpakistan.pk/'));

        $this->assertNotNull($captured);
        $this->assertSame('not_found', $captured['published_row_status']);
        $this->assertNull($captured['published_row_id']);
        $this->assertFalse($captured['content_exists']);
    }
}
