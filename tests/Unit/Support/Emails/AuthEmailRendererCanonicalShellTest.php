<?php

namespace Tests\Unit\Support\Emails;

use App\Support\Emails\AuthEmailRenderer;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class AuthEmailRendererCanonicalShellTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        config([
            'ota_client.slug' => 'jetpk',
            'app.url' => 'http://127.0.0.1:8088',
        ]);
    }

    /**
     * @return list<array{0: string, 1: string}>
     */
    public static function loginEventProvider(): array
    {
        return [
            ['admin_login_success', 'auth_privileged_login_success'],
            ['staff_login_success', 'auth_privileged_login_success'],
            ['agent_login_success', 'auth_agent_login_success'],
            ['customer_login_success', 'auth_login_success'],
            ['auth_new_device_login', 'auth_new_device_login'],
            ['login_failed_alert', 'auth_failed_login_alert'],
        ];
    }

    #[DataProvider('loginEventProvider')]
    public function test_jetpk_login_security_uses_canonical_shell(string $event, string $type): void
    {
        $rendered = app(AuthEmailRenderer::class)->loginSecurity([
            'event' => $event,
            'type' => $type,
            'title' => $event === 'auth_new_device_login' ? 'New login detected' : 'Login successful',
            'status_label' => 'Security notice',
            'greeting_name' => 'Test User',
            'intro' => 'A login to your account was detected.',
            'notes' => [
                'Time: 07 Sep 2026 10:00 PKT',
                'IP address: 203.0.113.10',
                'Device / browser: TestBrowser/1.0',
            ],
            'cta' => [[
                'label' => 'Reset password',
                'url' => 'https://jetpakistan.pk/forgot-password',
            ]],
        ]);

        $html = $rendered->html;
        $this->assertStringContainsString('jetpk-container', $html);
        $this->assertStringContainsString('max-width:640px', $html);
        $this->assertStringNotContainsString('width="620"', $html);
        $this->assertStringNotContainsString('max-width:620px', $html);
        $this->assertStringNotContainsString('Booking snapshot', $html);
        $this->assertStringNotContainsString('localhost', $html);
        $this->assertStringNotContainsString('127.0.0.1', $html);
        $this->assertStringContainsString('https://jetpakistan.pk/forgot-password', $html);
        $this->assertStringContainsString('203.0.113.10', $html);
        $this->assertStringContainsString('TestBrowser/1.0', $html);
        $this->assertNotSame('', trim($rendered->plainBody));
        $this->assertStringContainsString('Reset password', $rendered->plainBody);
    }
}
