<?php

namespace Tests\Feature\Console;

use App\Models\ClientProfile;
use App\Models\ClientProfileBranding;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class OtaSyncCurrentClientProfileCommandTest extends TestCase
{
    use RefreshDatabase;

    public function test_sync_creates_haseeb_master_from_config(): void
    {
        config([
            'ota_client.slug' => '',
            'ota_client.theme' => 'v2-modern',
            'ota_client.asset_profile' => 'haseeb-master',
            'ota_client.modules' => [
                'sabre' => true,
                'al_haider_group_ticketing' => false,
                'accounting' => true,
                'hotels' => false,
                'visa' => false,
                'payment_gateway' => true,
                'dev_cp' => true,
                'staff_panel' => true,
                'admin_panel' => true,
            ],
            'ota-client.agency_name' => 'Haseeb Master Travel',
            'ota-client.domain_preview' => 'ota.haseebasif.com',
            'app.url' => 'https://ota.haseebasif.com',
            'app.env' => 'production',
            'app.locale' => 'en',
            'app.timezone' => 'Asia/Karachi',
        ]);

        $this->artisan('ota:sync-current-client-profile')
            ->assertSuccessful()
            ->expectsOutputToContain('haseeb-master');

        $this->assertDatabaseHas('client_profiles', [
            'slug' => 'haseeb-master',
            'name' => 'Haseeb Master Travel',
            'active_frontend_theme' => 'v2-modern',
            'asset_profile' => 'haseeb-master',
            'is_master_profile' => true,
            'is_active' => true,
        ]);

        $profile = ClientProfile::query()->where('slug', 'haseeb-master')->firstOrFail();

        $this->assertDatabaseHas('client_profile_branding', [
            'client_profile_id' => $profile->id,
            'company_name' => 'Haseeb Master Travel',
        ]);

        $this->assertDatabaseHas('client_profile_modules', [
            'client_profile_id' => $profile->id,
            'module_key' => 'sabre',
            'enabled' => true,
        ]);

        $this->assertDatabaseHas('client_profile_modules', [
            'client_profile_id' => $profile->id,
            'module_key' => 'al_haider_group_ticketing',
            'enabled' => false,
        ]);
    }

    public function test_sync_is_idempotent(): void
    {
        config([
            'ota_client.slug' => 'haseeb-master',
            'ota_client.theme' => 'v1-classic',
            'ota_client.asset_profile' => 'haseeb-master',
            'ota-client.agency_name' => 'Haseeb Master Travel',
            'app.url' => 'https://ota.haseebasif.com',
        ]);

        $this->artisan('ota:sync-current-client-profile')->assertSuccessful();
        $firstId = ClientProfile::query()->where('slug', 'haseeb-master')->value('id');

        config(['ota-client.agency_name' => 'Updated Master Name']);

        $this->artisan('ota:sync-current-client-profile')->assertSuccessful();
        $secondId = ClientProfile::query()->where('slug', 'haseeb-master')->value('id');

        $this->assertSame($firstId, $secondId);
        $this->assertDatabaseHas('client_profiles', [
            'id' => $firstId,
            'name' => 'Updated Master Name',
        ]);
    }

    public function test_dry_run_does_not_write(): void
    {
        config([
            'ota_client.slug' => 'haseeb-master',
            'ota-client.agency_name' => 'Dry Run Client',
            'app.url' => 'https://dry-run.test',
        ]);

        $this->artisan('ota:sync-current-client-profile', ['--dry-run' => true])
            ->assertSuccessful()
            ->expectsOutputToContain('Dry run');

        $this->assertSame(0, ClientProfile::query()->count());
    }

    public function test_sync_respects_slug_option(): void
    {
        config([
            'ota_client.slug' => 'ignored-slug',
            'ota-client.agency_name' => 'Custom Slug Client',
            'app.url' => 'https://custom-slug.test',
        ]);

        $this->artisan('ota:sync-current-client-profile', ['--slug' => 'custom-client'])
            ->assertSuccessful();

        $this->assertDatabaseHas('client_profiles', [
            'slug' => 'custom-client',
            'is_master_profile' => false,
        ]);

        $profile = ClientProfile::query()->where('slug', 'custom-client')->firstOrFail();
        $this->assertInstanceOf(ClientProfileBranding::class, $profile->branding);
        $this->assertGreaterThanOrEqual(9, $profile->modules()->count());
    }
}
