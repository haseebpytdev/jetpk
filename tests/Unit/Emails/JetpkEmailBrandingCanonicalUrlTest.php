<?php

namespace Tests\Unit\Emails;

use App\Support\Emails\JetpkEmailBrandingResolver;
use Tests\TestCase;

class JetpkEmailBrandingCanonicalUrlTest extends TestCase
{
    public function test_seed_defaults_use_canonical_pk_host_and_unprefixed_manage_url(): void
    {
        config([
            'app.url' => 'https://jetpakistan.pk',
            'client.canonical_client.domain' => 'jetpakistan.pk',
            'jetpk_email.brand' => [],
        ]);

        $brand = JetpkEmailBrandingResolver::resolve('jetpk');

        $this->assertSame('https://jetpakistan.pk', rtrim((string) $brand['home_url'], '/'));
        $this->assertSame('https://jetpakistan.pk/lookup-booking', (string) $brand['manage_url']);
        $this->assertStringNotContainsString('jetpakistan.com', (string) $brand['home_url']);
        $this->assertStringNotContainsString('/jetpk/lookup-booking', (string) $brand['manage_url']);
        $this->assertStringNotContainsString('localhost', strtolower((string) json_encode($brand)));
    }

    public function test_localhost_app_url_still_yields_https_public_logo_and_ctas(): void
    {
        config([
            'app.url' => 'http://localhost',
            'client.canonical_client.domain' => 'jetpakistan.pk',
            'jetpk_email.brand' => [
                'logo_url' => 'http://localhost/client-assets/jetpk-assets/logo/logo.svg',
                'home_url' => 'http://localhost/',
                'manage_url' => 'http://localhost/jetpk/lookup-booking',
            ],
        ]);

        $brand = JetpkEmailBrandingResolver::resolve('jetpk');
        $encoded = strtolower((string) json_encode($brand));

        $this->assertSame('https://jetpakistan.pk', rtrim((string) $brand['home_url'], '/'));
        $this->assertSame('https://jetpakistan.pk/lookup-booking', (string) $brand['manage_url']);
        $this->assertSame(
            'https://jetpakistan.pk/client-assets/jetpk-assets/logo/logo.svg',
            (string) $brand['logo_url']
        );
        $this->assertStringNotContainsString('localhost', $encoded);
        $this->assertStringNotContainsString('127.0.0.1', $encoded);
        $this->assertStringNotContainsString('jetpakistan.com', $encoded);
        $this->assertStringNotContainsString('/jetpk/lookup-booking', $encoded);
        $this->assertStringStartsWith('https://', (string) $brand['logo_url']);
    }
}
