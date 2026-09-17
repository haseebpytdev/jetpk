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
}
