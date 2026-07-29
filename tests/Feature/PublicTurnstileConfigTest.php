<?php

namespace Tests\Feature;

use App\Support\Security\TurnstileVerifier;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class PublicTurnstileConfigTest extends TestCase
{
    use RefreshDatabase;

    public function test_turnstile_config_returns_disabled_when_not_configured(): void
    {
        config([
            'services.turnstile.enabled' => false,
            'services.turnstile.site_key' => null,
            'services.turnstile.secret_key' => null,
        ]);

        $this->getJson(route('api.public.content.turnstile-config'))
            ->assertOk()
            ->assertJsonPath('enabled', false)
            ->assertJsonPath('site_key', null)
            ->assertJsonPath('response_field', TurnstileVerifier::RESPONSE_FIELD);
    }

    public function test_turnstile_config_exposes_public_site_key_when_enabled(): void
    {
        config([
            'services.turnstile.enabled' => true,
            'services.turnstile.site_key' => 'test-site-key',
            'services.turnstile.secret_key' => 'test-secret-key',
        ]);

        $this->getJson(route('api.public.content.turnstile-config'))
            ->assertOk()
            ->assertJsonPath('enabled', true)
            ->assertJsonPath('site_key', 'test-site-key')
            ->assertJsonPath('response_field', TurnstileVerifier::RESPONSE_FIELD);
    }

    public function test_booking_lookup_json_requires_turnstile_when_enabled(): void
    {
        config([
            'services.turnstile.enabled' => true,
            'services.turnstile.site_key' => 'test-site-key',
            'services.turnstile.secret_key' => 'test-secret-key',
        ]);

        $this->postJson(route('lookup-booking.submit'), [
            'booking_reference' => 'ABC123',
            'email' => 'guest@example.com',
        ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors([TurnstileVerifier::RESPONSE_FIELD]);
    }

    public function test_booking_lookup_accepts_valid_turnstile_token_when_enabled(): void
    {
        config([
            'services.turnstile.enabled' => true,
            'services.turnstile.site_key' => 'test-site-key',
            'services.turnstile.secret_key' => 'test-secret-key',
        ]);

        Http::fake([
            'challenges.cloudflare.com/*' => Http::response(['success' => true]),
        ]);

        $this->post(route('lookup-booking.submit'), [
            'booking_reference' => 'ABC123',
            'email' => 'guest@example.com',
            TurnstileVerifier::RESPONSE_FIELD => 'valid-mock-token',
        ])->assertSessionDoesntHaveErrors(TurnstileVerifier::RESPONSE_FIELD);
    }
}
