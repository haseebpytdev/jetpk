<?php

namespace Tests\Unit\Deploy;

use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * Ensures the OLS short-URL rewrite remains a tracked, exact infrastructure artifact.
 */
class OlsFlightsShortUrlSnippetTest extends TestCase
{
    private function read(string $relative): string
    {
        $path = base_path($relative);
        $this->assertFileExists($path);

        return (string) file_get_contents($path);
    }

    #[Test]
    public function tracked_vhost_routes_conf_contains_short_url_and_exact_flight_rules(): void
    {
        $conf = $this->read('deploy/openlitespeed/jetpakistan-vhost-routes.conf');

        $this->assertStringContainsString('RewriteCond %{REQUEST_METHOD} ^(GET|HEAD)$', $conf);
        $this->assertStringContainsString(
            'RewriteRule ^/flights/(fare-selection|results|return-options)$ http://jetpk_public_next/flights/$1 [P,L,E=PROXY-HOST:jetpakistan.pk]',
            $conf
        );
        $this->assertStringContainsString(
            'RewriteRule ^/flights/s/([A-Za-z0-9]{8,32})$ http://jetpk_public_next/flights/s/$1 [P,L,E=PROXY-HOST:jetpakistan.pk]',
            $conf
        );
        $this->assertStringContainsString(
            'RewriteRule ^/groups$ http://jetpk_public_next/groups [P,L,E=PROXY-HOST:jetpakistan.pk]',
            $conf
        );
        $this->assertStringContainsString('jetpk_public_next', $conf);
        $this->assertStringContainsString('PROXY-HOST:jetpakistan.pk', $conf);
        // Must not proxy mutations by omitting method guard in the short rule block.
        $this->assertMatchesRegularExpression(
            '/RewriteCond %\{REQUEST_METHOD\} \^\(GET\|HEAD\)\$\s+RewriteRule \^\/flights\/s\//s',
            $conf
        );
    }

    #[Test]
    public function snippet_extract_matches_canonical_short_url_rule(): void
    {
        $snippet = $this->read('docs/jetpk/ols-snippets/flights-short-url.vhconf.snippet');
        $this->assertStringContainsString(
            'RewriteRule ^/flights/s/([A-Za-z0-9]{8,32})$ http://jetpk_public_next/flights/s/$1 [P,L,E=PROXY-HOST:jetpakistan.pk]',
            $snippet
        );
    }

    #[Test]
    public function assert_script_exists_and_names_gates(): void
    {
        $script = $this->read('scripts/jp-ols-assert-flights-short-url.sh');
        $this->assertStringContainsString('OLS_CONFIG_REPRODUCIBLE=PASS', $script);
        $this->assertStringContainsString('SHORT_URL_ROUTE_OWNER=NEXT', $script);
        $this->assertStringContainsString('ROUTE_OWNERSHIP_GUARD=PASS', $script);
        $this->assertStringContainsString('/flights/s/', $script);
        $this->assertStringContainsString('/groups/search', $script);
    }
}
