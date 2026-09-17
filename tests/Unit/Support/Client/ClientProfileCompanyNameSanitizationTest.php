<?php

namespace Tests\Unit\Support\Client;

use App\Support\Client\ClientProfileConfigReader;
use ReflectionMethod;
use Tests\TestCase;

class ClientProfileCompanyNameSanitizationTest extends TestCase
{
    public function test_platform_owner_label_is_replaced_with_jetpakistan(): void
    {
        $reader = app(ClientProfileConfigReader::class);
        $method = new ReflectionMethod(ClientProfileConfigReader::class, 'sanitizePublicCompanyName');
        $method->setAccessible(true);

        $this->assertSame('JetPakistan', $method->invoke($reader, 'Platform Owner'));
        $this->assertSame('JetPakistan', $method->invoke($reader, 'asif travels'));
        $this->assertSame('JetPakistan Travels', $method->invoke($reader, 'JetPakistan Travels'));
        $this->assertSame('JetPakistan', $method->invoke($reader, ''));
    }
}
