<?php

namespace Tests\Feature\Auth;

use App\Support\Auth\DemoFixedLoginOtpGate;
use Tests\TestCase;

class DemoFixedLoginOtpGateTest extends TestCase
{
    protected function tearDown(): void
    {
        config([
            'ota_otp_demo.fixed_enabled' => false,
            'ota_otp_demo.allow_production' => false,
            'ota_otp_demo.fixed_code' => '',
            'ota_otp_demo.allowed_emails' => [],
        ]);

        parent::tearDown();
    }

    public function test_demo_gate_disabled_in_production_without_explicit_override(): void
    {
        $this->app->detectEnvironment(fn () => 'production');

        config([
            'ota_otp_demo.fixed_enabled' => true,
            'ota_otp_demo.allow_production' => false,
            'ota_otp_demo.fixed_code' => '123456',
            'ota_otp_demo.allowed_emails' => ['demo@example.com'],
        ]);

        $this->assertFalse(DemoFixedLoginOtpGate::isEnabled());
        $this->assertFalse(DemoFixedLoginOtpGate::acceptsSubmittedCode('demo@example.com', '123456'));
    }

    public function test_demo_gate_available_in_production_only_when_explicitly_gated(): void
    {
        $this->app->detectEnvironment(fn () => 'production');

        config([
            'ota_otp_demo.fixed_enabled' => true,
            'ota_otp_demo.allow_production' => true,
            'ota_otp_demo.fixed_code' => '654321',
            'ota_otp_demo.allowed_emails' => ['qa@jetpakistan.com'],
        ]);

        $this->assertTrue(DemoFixedLoginOtpGate::isEnabled());
        $this->assertTrue(DemoFixedLoginOtpGate::isEmailAllowed('qa@jetpakistan.com'));
        $this->assertTrue(DemoFixedLoginOtpGate::acceptsSubmittedCode('qa@jetpakistan.com', '654321'));
        $this->assertFalse(DemoFixedLoginOtpGate::acceptsSubmittedCode('qa@jetpakistan.com', '000000'));
    }

    public function test_demo_gate_available_in_local_when_enabled(): void
    {
        $this->app->detectEnvironment(fn () => 'local');

        config([
            'ota_otp_demo.fixed_enabled' => true,
            'ota_otp_demo.allow_production' => false,
            'ota_otp_demo.fixed_code' => '112233',
            'ota_otp_demo.allowed_emails' => ['local@example.com'],
        ]);

        $this->assertTrue(DemoFixedLoginOtpGate::isEnabled());
        $this->assertTrue(DemoFixedLoginOtpGate::acceptsSubmittedCode('local@example.com', '112233'));
    }
}
