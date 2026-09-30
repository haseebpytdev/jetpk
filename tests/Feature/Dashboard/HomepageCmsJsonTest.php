<?php

namespace Tests\Feature\Dashboard;

use App\Enums\AccountType;
use App\Models\User;
use App\Support\Client\ClientPageKeys;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Support\JetpkHomepageFixture;
use Tests\TestCase;

class HomepageCmsJsonTest extends TestCase
{
    use JetpkHomepageFixture;
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        config(['ota-developer.enabled' => true]);
        config(['client_route_parity.enabled' => false]);
        $this->makeJetpkProfile();
        $this->seedJetpkAgency();
    }

    public function test_homepage_page_settings_json_returns_content(): void
    {
        $admin = User::factory()->create([
            'account_type' => AccountType::PlatformAdmin,
            'current_agency_id' => null,
        ]);

        $this->actingAs($admin)
            ->getJson('/admin/page-settings/'.ClientPageKeys::HOME.'?format=json')
            ->assertOk()
            ->assertJsonPath('ok', true)
            ->assertJsonPath('pageKey', ClientPageKeys::HOME)
            ->assertJsonStructure(['content', 'editorMeta', 'previewUrl']);
    }

    public function test_homepage_draft_save_and_publish_json_round_trip(): void
    {
        $admin = User::factory()->create([
            'account_type' => AccountType::PlatformAdmin,
            'current_agency_id' => null,
        ]);
        $marker = 'JP-QA-HOME-'.substr(sha1((string) microtime(true)), 0, 8);

        $load = $this->actingAs($admin)
            ->getJson('/admin/page-settings/'.ClientPageKeys::HOME.'?format=json')
            ->assertOk();

        $content = $load->json('content') ?? [];
        $this->assertIsArray($content);
        $content['hero'] = array_merge(is_array($content['hero'] ?? null) ? $content['hero'] : [], [
            'eyebrow' => $marker,
        ]);
        // Avoid IATA validation noise from incomplete fixture route rows during JSON write proof.
        $content['routes'] = ['title' => 'Trending Routes', 'items' => []];
        $content['destinations'] = ['title' => 'Destinations', 'items' => []];
        $content['featured_deals'] = ['title' => 'Featured Deals', 'items' => []];

        $this->actingAs($admin)
            ->patchJson('/admin/page-settings/'.ClientPageKeys::HOME.'?format=json', [
                'content' => $content,
                'submitted_sections' => ['hero', 'routes', 'destinations', 'featured_deals'],
            ])
            ->assertOk()
            ->assertJsonPath('ok', true);

        $this->actingAs($admin)
            ->getJson('/admin/page-settings/'.ClientPageKeys::HOME.'?format=json')
            ->assertOk()
            ->assertJsonPath('content.hero.eyebrow', $marker);

        $this->actingAs($admin)
            ->postJson('/admin/page-settings/'.ClientPageKeys::HOME.'/publish?format=json')
            ->assertOk()
            ->assertJsonPath('ok', true);
    }
}
