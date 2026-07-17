<?php

namespace Tests\Feature\Jetpk;

use App\Support\Client\ClientPublicWebrootPath;
use App\Support\Emails\JetpkEmailBrandingResolver;
use Illuminate\Support\Facades\File;
use Tests\TestCase;

/**
 * JETPK-STANDALONE-RESIDUAL-LEGACY-REFERENCE-CLEANUP verification.
 */
class JetpkResidualLegacyReferenceCleanupTest extends TestCase
{
    public function test_jetpk_email_branding_never_returns_haseebasif_support_email(): void
    {
        $brand = JetpkEmailBrandingResolver::resolve('jetpk');

        $this->assertNotSame('support@haseebasif.com', strtolower((string) ($brand['support_email'] ?? '')));
        $this->assertSame('ota@jetpakistan.pk', strtolower((string) $brand['support_email']));
    }

    public function test_deprecated_operational_emails_remap_to_canonical_support(): void
    {
        config([
            'client.deprecated_operational_emails' => ['support@jetpakistan.com'],
            'client.canonical_support_email' => 'ota@jetpakistan.pk',
        ]);

        $method = new \ReflectionMethod(JetpkEmailBrandingResolver::class, 'canonicalBusinessEmail');
        $method->setAccessible(true);

        $this->assertSame(
            'ota@jetpakistan.pk',
            $method->invoke(null, 'support@jetpakistan.com'),
        );
    }

    public function test_public_webroot_default_contains_no_haseebasif_path(): void
    {
        $default = (string) config('ota_client.public_webroot_path');

        $this->assertStringNotContainsString('ota.haseebasif.com', $default);
        $this->assertStringNotContainsString('haseebasif.com', $default);
        $this->assertSame(rtrim(str_replace('\\', '/', public_path()), '/'), ClientPublicWebrootPath::resolve());
    }

    public function test_prohibited_brand_markers_are_rejection_lists_not_fallbacks(): void
    {
        $markers = config('jetpk_email.prohibited_brand_markers', []);

        $this->assertContains('Parwaaz', $markers);
        $this->assertNotContains('JetPakistan', $markers);

        $brand = JetpkEmailBrandingResolver::resolve('jetpk');
        $this->assertStringNotContainsString('parwaaz', strtolower((string) ($brand['brand_name'] ?? '')));
    }

    public function test_active_jetpk_theme_views_contain_no_legacy_brand_strings(): void
    {
        $roots = [
            resource_path('views/themes/customer/jetpakistan'),
            resource_path('views/themes/agent/jetpakistan'),
            resource_path('views/themes/frontend/jetpakistan'),
        ];

        $pattern = '/Parwaaz|haseeb-master|YoursDomain|YD Travel|ota\.haseebasif\.com|support@haseebasif\.com/i';

        foreach ($roots as $root) {
            foreach (File::allFiles($root) as $file) {
                if ($file->getExtension() !== 'php') {
                    continue;
                }
                $this->assertDoesNotMatchRegularExpression(
                    $pattern,
                    (string) file_get_contents($file->getPathname()),
                    'Legacy reference in '.$file->getRelativePathname(),
                );
            }
        }
    }

    public function test_standalone_mode_uses_canonical_client_defaults(): void
    {
        $this->assertTrue(config('client.standalone'));
        $this->assertSame('jetpk', config('client.canonical_client.slug'));
        $this->assertFalse(config('client.fallback_policy.allow_cross_client_views'));
    }
}
