<?php

namespace Tests\Feature\Email;

use Database\Seeders\OtaFoundationSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\URL;
use Tests\TestCase;

class JetpkEmailContentAssertionTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(OtaFoundationSeeder::class);
        // Assert production-like URL authority (not local jetpk.test fixtures).
        Config::set('app.url', 'https://jetpakistan.pk');
        Config::set('app.asset_url', 'https://jetpakistan.pk');
        URL::forceRootUrl('https://jetpakistan.pk');
        URL::forceScheme('https');
    }

    public function test_booking_confirmed_user_preview_has_blocks_and_not_dear_team(): void
    {
        $this->assertSame(0, Artisan::call('jetpk:email-preview', [
            '--event' => 'booking_confirmed',
            '--role' => 'user',
        ]));

        $html = file_get_contents(storage_path('app/email-previews/jetpk/booking_confirmed_user.html'));
        $this->assertIsString($html);
        $this->assertStringNotContainsString('Dear Team', $html);
        $this->assertStringNotContainsString('hello@example.com', $html);
        $this->assertStringNotContainsString('+92 21 111 000 000', $html);
        $this->assertStringNotContainsString('support@jetpakistan.com', $html);
        $this->assertStringNotContainsString('jetpk.test', $html);
        $this->assertStringNotContainsString('localhost', $html);
        $this->assertDoesNotMatchRegularExpression('/Hi .+,\s*[\r\n].*Hello /s', $html);
        $this->assertStringContainsString('Booking summary', $html);
        $this->assertTrue(
            str_contains(strtolower($html), 'passenger') && str_contains(strtolower($html), 'itinerary'),
            'Expected itinerary and passenger content in booking_confirmed preview'
        );
    }

    public function test_security_password_reset_preview_omits_manage_booking(): void
    {
        $this->assertSame(0, Artisan::call('jetpk:email-preview', [
            '--event' => 'password_reset_requested',
            '--role' => 'user',
        ]));

        $html = file_get_contents(storage_path('app/email-previews/jetpk/password_reset_requested_user.html'));
        $this->assertIsString($html);
        $this->assertStringNotContainsString('Manage booking', $html);
        $this->assertStringNotContainsString('PNR', $html);
        $this->assertStringNotContainsString('Booking summary', $html);
        $this->assertStringNotContainsString('hello@example.com', $html);
        $this->assertStringNotContainsString('jetpk.test', $html);
    }

    public function test_email_verification_preview_omits_manage_booking(): void
    {
        if (! class_exists(\App\Support\Emails\JetpkEmailEventContentRegistry::class)) {
            $this->markTestSkipped('registry missing');
        }

        $exit = Artisan::call('jetpk:email-preview', [
            '--event' => 'email_verification',
            '--role' => 'user',
        ]);
        if ($exit !== 0) {
            $this->markTestSkipped('email_verification preview unavailable: '.Artisan::output());
        }

        $html = file_get_contents(storage_path('app/email-previews/jetpk/email_verification_user.html'));
        $this->assertIsString($html);
        $this->assertStringNotContainsString('Manage booking', $html);
        $this->assertStringNotContainsString('Payment status', $html);
        $this->assertStringNotContainsString('PNR', $html);
        $this->assertStringNotContainsString('jetpk.test', $html);
    }
}
