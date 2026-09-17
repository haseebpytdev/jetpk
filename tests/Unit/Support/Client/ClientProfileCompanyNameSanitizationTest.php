<?php

namespace Tests\Unit\Support\Client;

use App\Support\Branding\BrandDisplayResolver;
use Tests\TestCase;

class ClientProfileCompanyNameSanitizationTest extends TestCase
{
    public function test_platform_owner_label_is_replaced_with_jetpakistan(): void
    {
        $this->assertSame('JetPakistan', BrandDisplayResolver::sanitizePublicCompanyName('Platform Owner'));
        $this->assertSame('JetPakistan', BrandDisplayResolver::sanitizePublicCompanyName('asif travels'));
        $this->assertSame('JetPakistan Travels', BrandDisplayResolver::sanitizePublicCompanyName('JetPakistan Travels'));
        $this->assertSame('JetPakistan', BrandDisplayResolver::sanitizePublicCompanyName(''));
    }
}
