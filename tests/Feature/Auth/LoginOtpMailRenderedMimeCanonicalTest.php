<?php

namespace Tests\Feature\Auth;

use App\Mail\LoginOtpMail;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Config;
use Tests\TestCase;

/**
 * CONTINUE-04: assert rendered OTP MIME never embeds localhost / stale hosts.
 */
class LoginOtpMailRenderedMimeCanonicalTest extends TestCase
{
    use RefreshDatabase;

    public function test_rendered_otp_html_uses_canonical_https_logo_and_ctas(): void
    {
        Config::set('app.url', 'http://localhost');
        Config::set('client.canonical_client.domain', 'jetpakistan.pk');
        Config::set('ota_client.slug', 'jetpk');
        Config::set('mail.from.address', 'ota@jetpakistan.pk');
        Config::set('mail.from.name', 'JetPakistan');
        Config::set('jetpk_email.brand', []);

        $user = User::factory()->create([
            'email' => 'qa.otp.mime@example.test',
            'name' => 'QA OTP',
        ]);

        $html = (new LoginOtpMail(
            user: $user,
            brandName: 'JetPakistan',
            otpCode: '654321',
            expiryMinutes: 10,
            clientSlug: 'jetpk',
        ))->render();

        $this->assertStringContainsString('https://jetpakistan.pk', $html);
        $this->assertStringContainsString('https://jetpakistan.pk/lookup-booking', $html);
        $this->assertStringNotContainsString('http://localhost', $html);
        $this->assertStringNotContainsString('https://localhost', $html);
        $this->assertStringNotContainsString('www.jetpakistan.com', $html);
        $this->assertStringNotContainsString('/jetpk/lookup-booking', $html);
        $this->assertMatchesRegularExpression(
            '#src=["\']https://jetpakistan\.pk/[^"\']*logo[^"\']*["\']#i',
            $html
        );
        $this->assertStringContainsString('654321', $html);
    }
}
