<?php

namespace Tests\Feature;

use App\Enums\AccountType;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Support\PlatformAdminTestHelpers;
use Tests\TestCase;

class GoLiveChecklistJsonTest extends TestCase
{
    use PlatformAdminTestHelpers;
    use RefreshDatabase;

    public function test_platform_admin_receives_validator_items_with_deep_links(): void
    {
        $admin = $this->platformAdmin();

        $response = $this->actingAs($admin)->getJson(route('api.dashboard.system.go-live'));
        $response->assertOk()
            ->assertJsonPath('data.managementMode', 'validator_with_deep_links');
        $this->assertNotEmpty($response->json('data.checklist'));
        $commercial = collect($response->json('data.checklist'))->firstWhere('key', 'commercial_uat');
        $this->assertIsArray($commercial);
        $this->assertFalse((bool) $commercial['ok']);
    }

    public function test_agency_admin_cannot_open_go_live_json(): void
    {
        $legacy = $this->legacyAgencyAdminFromSeed();

        $this->actingAs($legacy)->getJson(route('api.dashboard.system.go-live'))->assertForbidden();
    }
}
