<?php

namespace App\Support\Fixtures;

use App\Enums\ClientPageSettingStatus;
use App\Models\Agency;
use App\Models\Airport;
use App\Models\ClientPageSetting;
use App\Models\ClientProfile;
use App\Models\ClientProfileModule;
use App\Support\Client\ClientPageKeys;
use App\Support\Client\ClientProfileConfigReader;

/**
 * Local-only JetPK homepage fixture for content/media audit CLI gates.
 * Does not call suppliers, send email, or create bookings.
 */
final class JetpkHomepageAuditFixtureBuilder
{
    /**
     * @return array{profile: ClientProfile, published: ClientPageSetting, draft: ClientPageSetting}
     */
    public function seed(string $slug = 'jetpk'): array
    {
        $this->seedAirports();
        $this->seedAgency();
        $profile = $this->seedProfile($slug);
        $content = $this->representativeValidFourCardHomeContent();
        $draftContent = $this->representativeThreeCardHomeContent();

        $published = ClientPageSetting::query()->updateOrCreate(
            [
                'client_profile_id' => $profile->id,
                'page_key' => ClientPageKeys::HOME,
                'status' => ClientPageSettingStatus::Published,
            ],
            ['content_json' => $content],
        );

        $draft = ClientPageSetting::query()->updateOrCreate(
            [
                'client_profile_id' => $profile->id,
                'page_key' => ClientPageKeys::HOME,
                'status' => ClientPageSettingStatus::Draft,
            ],
            ['content_json' => $draftContent],
        );

        return compact('profile', 'published', 'draft');
    }

    private function seedAirports(): void
    {
        foreach (['KHI', 'LHE', 'ISB', 'DXB', 'JED', 'LHR', 'RUH', 'IST'] as $code) {
            Airport::query()->firstOrCreate(
                ['iata_code' => $code],
                [
                    'name' => $code.' Airport',
                    'city' => $code,
                    'country' => 'Test',
                    'country_code' => 'PK',
                    'is_active' => true,
                    'is_commercial' => true,
                ],
            );
        }
    }

    private function seedAgency(): Agency
    {
        config(['ota.default_agency_slug' => 'jetpk-agency']);

        return Agency::query()->firstOrCreate(
            ['slug' => 'jetpk-agency'],
            ['name' => 'JetPK Agency', 'is_active' => true],
        );
    }

    private function seedProfile(string $slug): ClientProfile
    {
        $profile = ClientProfile::query()->firstOrCreate(
            ['slug' => $slug],
            [
                'name' => 'Jet Pakistan',
                'domain' => null,
                'environment' => 'staging',
                'active_frontend_theme' => 'jetpakistan',
                'active_admin_theme' => 'jetpakistan',
                'active_staff_theme' => 'jetpakistan',
                'asset_profile' => 'jetpk-assets',
                'default_locale' => 'en',
                'timezone' => 'Asia/Karachi',
                'currency' => 'PKR',
                'is_master_profile' => false,
                'is_active' => true,
            ],
        );

        foreach (ClientProfileConfigReader::MODULE_KEYS as $moduleKey) {
            ClientProfileModule::query()->firstOrCreate(
                [
                    'client_profile_id' => $profile->id,
                    'module_key' => $moduleKey,
                ],
                ['enabled' => true],
            );
        }

        return $profile;
    }

    /**
     * @return array<string, mixed>
     */
    private function representativeThreeCardHomeContent(): array
    {
        return [
            'hero' => [
                'eyebrow' => 'Custom JetPK eyebrow',
                'headline' => 'Custom headline preserved',
            ],
            'routes' => [
                'enabled' => '1',
                'title' => 'Custom routes title',
                'items' => [
                    [
                        'id' => 'custom-route-1',
                        'from' => 'KHI',
                        'to' => 'DXB',
                        'enabled' => '1',
                        'sort_order' => 0,
                        'trip_type' => 'one_way',
                        'manual_fallback_price' => 11111,
                        'dynamic_fare_enabled' => '0',
                    ],
                    [
                        'id' => 'custom-route-2',
                        'from' => 'LHE',
                        'to' => 'JED',
                        'enabled' => '1',
                        'sort_order' => 1,
                        'trip_type' => 'one_way',
                        'manual_fallback_price' => 22222,
                        'dynamic_fare_enabled' => '0',
                    ],
                    [
                        'id' => 'custom-route-3',
                        'from' => 'ISB',
                        'to' => 'LHR',
                        'enabled' => '1',
                        'sort_order' => 2,
                        'trip_type' => 'one_way',
                        'manual_fallback_price' => 33333,
                        'dynamic_fare_enabled' => '0',
                    ],
                ],
            ],
            'destinations' => [
                'enabled' => '1',
                'title' => 'Custom destinations title',
                'items' => [
                    [
                        'id' => 'custom-dest-dxb',
                        'code' => 'DXB',
                        'title' => 'Custom Dubai',
                        'enabled' => '1',
                        'sort_order' => 0,
                        'manual_fallback_price' => 44444,
                    ],
                    [
                        'id' => 'custom-dest-jed',
                        'code' => 'JED',
                        'title' => 'Custom Jeddah',
                        'enabled' => '1',
                        'sort_order' => 1,
                        'manual_fallback_price' => 55555,
                    ],
                    [
                        'id' => 'custom-dest-lhr',
                        'code' => 'LHR',
                        'title' => 'Custom London',
                        'enabled' => '1',
                        'sort_order' => 2,
                        'manual_fallback_price' => 66666,
                    ],
                ],
            ],
            'support_cta' => [
                'enabled' => '1',
                'title' => 'Stuck mid-booking? Talk to a human.',
                'background_mode' => 'gradient',
                'call_enabled' => '0',
                'chat_enabled' => '0',
                'phone_value' => '',
                'chat_url' => '',
            ],
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function representativeValidFourCardHomeContent(): array
    {
        $base = $this->representativeThreeCardHomeContent();
        $base['routes']['items'][] = [
            'id' => 'custom-route-4',
            'from' => 'KHI',
            'to' => 'RUH',
            'enabled' => '1',
            'sort_order' => 3,
            'trip_type' => 'one_way',
            'manual_fallback_price' => 77777,
            'dynamic_fare_enabled' => '0',
        ];
        $base['destinations']['items'][] = [
            'id' => 'custom-dest-ist',
            'code' => 'IST',
            'title' => 'Custom Istanbul',
            'enabled' => '1',
            'sort_order' => 3,
            'manual_fallback_price' => 88888,
        ];
        $base['support_cta']['call_enabled'] = '1';
        $base['support_cta']['phone_value'] = '+92 21 111 000 000';
        $base['support_cta']['chat_enabled'] = '1';
        $base['support_cta']['chat_url'] = '/support';

        return $base;
    }
}
