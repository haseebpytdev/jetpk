<?php

namespace Database\Seeders;

use App\Enums\ClientPageSettingStatus;
use App\Models\ClientPageSetting;
use App\Models\ClientProfile;
use App\Services\Homepage\JetpkHomepageMediaAuthorityBackfill;
use App\Support\Client\ClientPageKeys;
use Illuminate\Database\Seeder;

/**
 * Local integrated QA seeder for production-parity homepage media authority.
 */
class JetpkHomepageMediaCertificationSeeder extends Seeder
{
    public function run(): void
    {
        $profile = ClientProfile::query()->firstOrCreate(
            ['slug' => 'jetpk'],
            [
                'name' => 'Jet Pakistan',
                'active_frontend_theme' => 'jetpakistan',
                'active_admin_theme' => 'jetpakistan',
                'active_staff_theme' => 'jetpakistan',
                'asset_profile' => 'jetpk-assets',
                'default_locale' => 'en',
                'timezone' => 'Asia/Karachi',
                'currency' => 'PKR',
                'is_active' => true,
            ],
        );

        $content = require database_path('fixtures/jetpk-homepage-media-cert-content.php');

        ClientPageSetting::query()->updateOrCreate(
            [
                'client_profile_id' => $profile->id,
                'page_key' => ClientPageKeys::HOME,
                'status' => ClientPageSettingStatus::Published,
            ],
            ['content_json' => $content],
        );

        config(['ota_client.slug' => 'jetpk']);
        app(\App\Services\Client\CurrentClientContext::class)->set($profile);

        app(JetpkHomepageMediaAuthorityBackfill::class)->runForProfile($profile);
    }
}
