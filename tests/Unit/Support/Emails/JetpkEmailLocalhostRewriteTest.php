<?php

namespace Tests\Unit\Support\Emails;

use App\Support\Emails\JetpkEmailBrandingResolver;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class JetpkEmailLocalhostRewriteTest extends TestCase
{
    #[Test]
    public function public_asset_url_rewrites_loopback_and_8088_and_index_php(): void
    {
        $this->assertSame(
            'https://jetpakistan.pk/storage/agencies/1/branding/logo.png',
            JetpkEmailBrandingResolver::publicAssetUrl('https://127.0.0.1:8088/storage/agencies/1/branding/logo.png'),
        );
        $this->assertSame(
            'https://jetpakistan.pk/forgot-password',
            JetpkEmailBrandingResolver::publicAssetUrl('https://127.0.0.1:8088/index.php/forgot-password'),
        );
        $this->assertSame(
            'https://jetpakistan.pk/forgot-password',
            JetpkEmailBrandingResolver::publicForgotPasswordUrl(),
        );
    }
}
