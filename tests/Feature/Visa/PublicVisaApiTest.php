<?php

namespace Tests\Feature\Visa;

use Illuminate\Support\Facades\Config;
use Tests\TestCase;

class PublicVisaApiTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        Config::set('visa.module_enabled', true);
        Config::set('visa.allow_in_testing', true);
        Config::set('visa.default_provider', 'mock');
        Config::set('visa.saudi_mofa.policy_approved', false);
    }

    public function test_health_and_start_and_lookup_mock(): void
    {
        $health = $this->withSession([])->getJson('/api/public/visa/health');
        $health->assertOk()->assertJsonPath('provider.live_allowed', false);

        $start = $this->withSession([])->postJson('/api/public/visa/start');
        $start->assertOk();
        $sessionId = $start->json('lookup_session_id');
        $this->assertNotEmpty($sessionId);

        // Read captcha answer from cache via provider store is internal; use mock by decoding is hard.
        // Call unit path: second request with wrong captcha should 422 CAPTCHA_INVALID
        $bad = $this->withSession([])->postJson('/api/public/visa/lookup', [
            'lookup_session_id' => $sessionId,
            'first_criterion' => 'passport_number',
            'first_value' => 'XX9999999',
            'second_criterion' => 'visa_number',
            'second_value' => '9999000011',
            'nationality' => 'PAK',
            'captcha_answer' => 'WRONG',
        ]);
        // Session owner must match — same withSession empty id should work within test
        $bad->assertStatus(422);
    }

    public function test_lookup_rejects_get_query_string_usage_for_endpoint(): void
    {
        $this->get('/api/public/visa/lookup?passport=SECRET')->assertMethodNotAllowed();
    }
}
