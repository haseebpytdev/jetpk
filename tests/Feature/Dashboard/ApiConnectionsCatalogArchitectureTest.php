<?php

namespace Tests\Feature\Dashboard;

use App\Enums\AccountType;
use App\Models\User;
use Database\Seeders\OtaFoundationSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ApiConnectionsCatalogArchitectureTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(OtaFoundationSeeder::class);
    }

    public function test_catalog_excludes_retired_and_ghost_providers(): void
    {
        $admin = $this->platformAdmin();
        $providers = collect($this->actingAs($admin)->getJson('/admin/api-settings?format=json')->json('providers'));
        $cards = collect($this->actingAs($admin)->getJson('/admin/api-settings?format=json')->json('providerCards'));
        $keys = $providers->pluck('key')->all();
        $cardKeys = $cards->pluck('key')->all();

        foreach (['amadeus', 'travelport', 'airline_direct', 'airsial', 'generic', 'smtp', 'google_oauth'] as $banned) {
            $this->assertNotContains($banned, $keys);
            $this->assertNotContains($banned, $cardKeys);
        }

        foreach (['sabre', 'pia_ndc', 'airblue', 'iati', 'duffel', 'one_api', 'al_haider', 'ameer_e_millat'] as $required) {
            $this->assertContains($required, $keys);
        }

        $airblue = $providers->firstWhere('key', 'airblue');
        $this->assertTrue((bool) ($airblue['installed'] ?? false));
        $this->assertSame('certification_pending', $airblue['implementation_state'] ?? null);
        $this->assertSame('configuration_validation', $airblue['check_type'] ?? null);
        $this->assertFalse((bool) ($airblue['baseUrlOverridable'] ?? true));
    }

    public function test_platform_integrations_are_env_backed_without_secrets(): void
    {
        $admin = $this->platformAdmin();
        config([
            'mail.default' => 'smtp',
            'mail.mailers.smtp.host' => 'smtp.example.test',
            'mail.mailers.smtp.port' => 587,
            'mail.mailers.smtp.username' => 'mail-user',
            'mail.mailers.smtp.password' => 'mail-secret-must-not-leak',
            'mail.from.address' => 'ops@example.test',
            'services.google.client_id' => '',
            'services.google.client_secret' => '',
        ]);

        $payload = $this->actingAs($admin)->getJson('/admin/api-settings?format=json')->assertOk()->json();
        $integrations = collect($payload['platformIntegrations'] ?? []);
        $smtp = $integrations->firstWhere('key', 'smtp');
        $google = $integrations->firstWhere('key', 'google_oauth');

        $this->assertNotNull($smtp);
        $this->assertTrue((bool) $smtp['configured']);
        $this->assertTrue((bool) $smtp['passwordPresent']);
        $this->assertSame('environment', $smtp['source']);
        $this->assertTrue((bool) $smtp['readOnly']);

        $this->assertNotNull($google);
        $this->assertFalse((bool) $google['configured']);
        $this->assertSame('Not configured', $google['statusLabel']);

        $encoded = json_encode($payload);
        $this->assertStringNotContainsString('mail-secret-must-not-leak', (string) $encoded);
        $this->assertStringNotContainsString('GOOGLE_CLIENT_SECRET', (string) $encoded);
    }

    public function test_agency_admin_is_denied_dashboard_api_session(): void
    {
        $admin = User::query()->where('email', 'admin@ota.demo')->firstOrFail();
        $admin->forceFill(['account_type' => AccountType::AgencyAdmin])->save();

        $this->actingAs($admin->fresh())
            ->getJson('/api/dashboard/session')
            ->assertForbidden();
    }

    protected function platformAdmin(): User
    {
        $admin = User::query()->where('email', 'admin@ota.demo')->firstOrFail();
        $admin->forceFill(['account_type' => AccountType::PlatformAdmin])->save();

        return $admin->fresh();
    }
}
