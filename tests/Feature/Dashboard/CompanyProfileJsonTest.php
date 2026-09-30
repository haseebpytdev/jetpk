<?php

namespace Tests\Feature\Dashboard;

use App\Enums\AccountType;
use App\Models\User;
use App\Support\BackOffice\BackOfficeCapabilitiesPresenter;
use Database\Seeders\OtaFoundationSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CompanyProfileJsonTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(OtaFoundationSeeder::class);
    }

    public function test_branding_json_returns_organization_payload(): void
    {
        [$admin] = $this->platformAdmin();

        $this->actingAs($admin)
            ->getJson('/admin/settings/branding?format=json')
            ->assertOk()
            ->assertJsonPath('ok', true)
            ->assertJsonStructure([
                'organization' => [
                    'display_name',
                    'legal_name',
                    'support_email',
                    'support_phone',
                    'website_url',
                    'office_address',
                    'city',
                    'country',
                    'timezone',
                    'logo_url',
                    'favicon_url',
                ],
            ]);
    }

    public function test_branding_json_patch_persists_and_readback(): void
    {
        [$admin] = $this->platformAdmin();
        $marker = 'JP-QA-ORG-'.substr(sha1((string) microtime(true)), 0, 8);

        $this->actingAs($admin)
            ->patchJson('/admin/settings/branding?format=json', [
                'display_name' => $marker,
                'support_phone' => '+92-300-0000000',
                'timezone' => 'Asia/Karachi',
            ])
            ->assertOk()
            ->assertJsonPath('ok', true)
            ->assertJsonPath('organization.display_name', $marker);

        $this->actingAs($admin)
            ->getJson('/admin/settings/branding?format=json')
            ->assertOk()
            ->assertJsonPath('organization.display_name', $marker);
    }

    public function test_company_profile_nav_targets_next_general_settings(): void
    {
        [$admin] = $this->platformAdmin();
        $payload = app(BackOfficeCapabilitiesPresenter::class)->present($admin, 'admin');
        $item = collect($payload['navigation'] ?? [])->firstWhere('key', 'company-profile');

        $this->assertNotNull($item);
        $this->assertSame('/settings/general', $item['href']);
        $this->assertSame('dashboard', $item['target'] ?? null);
    }

    /**
     * @return array{0: User}
     */
    protected function platformAdmin(): array
    {
        $admin = User::query()->where('email', 'admin@ota.demo')->firstOrFail();
        if ($admin->account_type !== AccountType::PlatformAdmin) {
            $admin->forceFill(['account_type' => AccountType::PlatformAdmin])->save();
            $admin = $admin->fresh();
        }

        return [$admin];
    }
}
