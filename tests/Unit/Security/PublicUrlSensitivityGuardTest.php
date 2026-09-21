<?php

namespace Tests\Unit\Security;

use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * Static guard: short-ref routes and public URL builders must not reintroduce
 * sensitive query parameters into browser-facing URL helpers.
 */
class PublicUrlSensitivityGuardTest extends TestCase
{
    #[Test]
    public function short_ref_service_does_not_embed_search_id_in_public_path(): void
    {
        $path = base_path('app/Services/PublicShortRefService.php');
        if (! is_file($path)) {
            $this->markTestSkipped('PublicShortRefService absent');
        }

        $src = (string) file_get_contents($path);
        $this->assertStringNotContainsString('search_id=', $src);
        $this->assertMatchesRegularExpression('/flights\\/s\\//', $src);
    }

    #[Test]
    public function ols_short_rule_is_get_head_only(): void
    {
        $conf = (string) file_get_contents(base_path('deploy/openlitespeed/jetpakistan-vhost-routes.conf'));
        $this->assertStringContainsString('RewriteCond %{REQUEST_METHOD} ^(GET|HEAD)$', $conf);
        $this->assertStringContainsString('^/flights/s/([A-Za-z0-9]{8,32})$', $conf);
    }

    #[Test]
    public function release_lock_names_next_as_short_url_owner(): void
    {
        $lock = json_decode((string) file_get_contents(base_path('docs/closure/jetpakistan-release-lock.json')), true);
        $this->assertIsArray($lock);
        $this->assertSame('NEXT', $lock['critical_route_owners']['/flights/s/{ref}'] ?? null);
        $this->assertSame(
            'deploy/openlitespeed/jetpakistan-vhost-routes.conf',
            $lock['ols_configuration_source'] ?? null
        );
    }

    #[Test]
    public function release_lock_v2_application_release_matches_build_stamps(): void
    {
        $lock = json_decode((string) file_get_contents(base_path('docs/closure/jetpakistan-release-lock.json')), true);
        $this->assertIsArray($lock);
        $app = $lock['application_release_sha'] ?? null;
        $this->assertIsString($app);
        $this->assertMatchesRegularExpression('/^[0-9a-f]{40}$/', $app);
        $this->assertSame($app, $lock['production_runtime_sha'] ?? null);
        $this->assertSame($app, $lock['public_build_source_sha'] ?? null);
        $this->assertSame($app, $lock['dashboard_build_source_sha'] ?? null);
        $this->assertNotEmpty($lock['public_build_id'] ?? null);
        $this->assertNotEmpty($lock['dashboard_build_id'] ?? null);
        // Deprecated v1 self-hash fields / HEAD~1 authority must not reappear as sole keys.
        $this->assertArrayNotHasKey('release_sha', $lock);
    }
}