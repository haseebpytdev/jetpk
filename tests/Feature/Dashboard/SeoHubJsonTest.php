<?php

namespace Tests\Feature\Dashboard;

use App\Enums\AccountType;
use App\Models\User;
use App\Support\BackOffice\BackOfficeCapabilitiesPresenter;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Support\JetpkHomepageFixture;
use Tests\TestCase;

class SeoHubJsonTest extends TestCase
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

    public function test_seo_overview_and_global_round_trip_json(): void
    {
        [$admin] = $this->platformAdmin();

        $this->actingAs($admin)
            ->getJson('/admin/seo?format=json')
            ->assertOk()
            ->assertJsonPath('ok', true)
            ->assertJsonStructure(['stats', 'pages']);

        $original = $this->actingAs($admin)
            ->getJson('/admin/seo/global?format=json')
            ->assertOk()
            ->assertJsonPath('ok', true)
            ->json('settings.title');

        $marker = 'QA SEO Title '.substr(sha1((string) microtime(true)), 0, 6);

        $this->actingAs($admin)
            ->patchJson('/admin/seo/global?format=json', [
                'title' => $marker,
            ])
            ->assertOk()
            ->assertJsonPath('ok', true)
            ->assertJsonPath('settings.title', $marker);

        $this->actingAs($admin)
            ->getJson('/admin/seo/global?format=json')
            ->assertOk()
            ->assertJsonPath('settings.title', $marker);

        $this->actingAs($admin)
            ->patchJson('/admin/seo/global?format=json', [
                'title' => is_string($original) ? $original : '',
            ])
            ->assertOk()
            ->assertJsonPath('ok', true);
    }

    public function test_seo_nav_targets_next(): void
    {
        [$admin] = $this->platformAdmin();
        $payload = app(BackOfficeCapabilitiesPresenter::class)->present($admin, 'admin');
        $item = collect($payload['navigation'] ?? [])->firstWhere('key', 'seo');

        $this->assertNotNull($item);
        $this->assertSame('/seo', $item['href']);
        $this->assertSame('dashboard', $item['target'] ?? null);
    }

    /**
     * @return array{0: User}
     */
    protected function platformAdmin(): array
    {
        $admin = User::factory()->create([
            'account_type' => AccountType::PlatformAdmin,
            'current_agency_id' => null,
        ]);

        return [$admin];
    }
}
