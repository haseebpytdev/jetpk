<?php

namespace Tests\Unit\Support\Emails;

use App\Support\Emails\JetpkEmailEventRenderer;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class JetpkSecurityEmailIsolationTest extends TestCase
{
    use RefreshDatabase;

    public function test_password_reset_render_strips_booking_payload_blocks(): void
    {
        $html = app(JetpkEmailEventRenderer::class)->render(
            'password_reset',
            null,
            null,
            [
                'customer_name' => 'QA User',
                'reset_url' => 'https://jetpakistan.pk/reset-password/token',
            ],
            [
                'booking' => [
                    'reference' => 'JPK-SECRET-REF',
                    'pnr' => 'ABC123',
                    'route' => 'LHE-DXB',
                    'amount' => '99999',
                ],
                'itinerary' => [['flight' => 'PK300']],
                'passengers' => [['name' => 'Secret Pax']],
                'payment' => ['status' => 'paid'],
            ],
        )->html;

        $this->assertStringNotContainsString('JPK-SECRET-REF', $html);
        $this->assertStringNotContainsString('ABC123', $html);
        $this->assertStringNotContainsString('LHE-DXB', $html);
        $this->assertStringNotContainsString('Secret Pax', $html);
        $this->assertStringNotContainsString('99999', $html);
    }

    public function test_email_verification_render_strips_booking_payload_blocks(): void
    {
        $html = app(JetpkEmailEventRenderer::class)->render(
            'email_verification',
            null,
            null,
            [
                'customer_name' => 'QA User',
                'verification_url' => 'https://jetpakistan.pk/verify-email/1/hash',
            ],
            [
                'booking' => ['reference' => 'JPK-BOOKING-LEAK'],
                'payment' => ['amount' => '12345'],
            ],
        )->html;

        $this->assertStringNotContainsString('JPK-BOOKING-LEAK', $html);
        $this->assertStringNotContainsString('12345', $html);
    }
}
