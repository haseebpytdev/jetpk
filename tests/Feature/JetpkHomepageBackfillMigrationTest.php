<?php

namespace Tests\Feature;

use App\Enums\ClientPageSettingStatus;
use App\Models\ClientPageSetting;
use App\Models\ClientProfile;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Support\JetpkHomepageFixture;
use Tests\TestCase;

class JetpkHomepageBackfillMigrationTest extends TestCase
{
    use JetpkHomepageFixture;
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seedJetpkAirports();
    }

    public function test_backfill_adds_fourth_cards_without_overwriting_custom_three_card_state(): void
    {
        $profile = $this->makeJetpkProfile();
        $original = $this->representativeThreeCardHomeContent();
        $draftOriginal = array_merge($original, ['hero' => ['headline' => 'Draft-only headline']]);

        $this->seedPublishedHome($profile, $original);
        $this->seedDraftHome($profile, $draftOriginal);

        $this->runHomepageBackfillMigration();

        $published = ClientPageSetting::query()
            ->where('client_profile_id', $profile->id)
            ->where('status', ClientPageSettingStatus::Published)
            ->first();

        $content = $published->content_json;
        $this->assertSame('Custom JetPK eyebrow', data_get($content, 'hero.eyebrow'));
        $this->assertSame('Custom routes title', data_get($content, 'routes.title'));
        $this->assertCount(4, $content['routes']['items']);
        $this->assertCount(4, $content['destinations']['items']);

        $this->assertSame(11111, (int) data_get($content, 'routes.items.0.manual_fallback_price'));
        $this->assertSame(22222, (int) data_get($content, 'routes.items.1.manual_fallback_price'));
        $this->assertSame(33333, (int) data_get($content, 'routes.items.2.manual_fallback_price'));
        $this->assertSame(44444, (int) data_get($content, 'destinations.items.0.manual_fallback_price'));
        $this->assertSame(55555, (int) data_get($content, 'destinations.items.1.manual_fallback_price'));
        $this->assertSame(66666, (int) data_get($content, 'destinations.items.2.manual_fallback_price'));

        $addedRoute = collect($content['routes']['items'])->first(fn ($item) => ($item['from'] ?? '') === 'KHI' && ($item['to'] ?? '') === 'RUH');
        $this->assertNotNull($addedRoute);
        $addedDest = collect($content['destinations']['items'])->first(fn ($item) => ($item['code'] ?? '') === 'IST');
        $this->assertNotNull($addedDest);

        foreach ($content['routes']['items'] as $item) {
            $price = (int) ($item['manual_fallback_price'] ?? 0);
            if ($price > 0) {
                $this->assertGreaterThan(0, $price);
            }
        }

        $draft = ClientPageSetting::query()
            ->where('client_profile_id', $profile->id)
            ->where('status', ClientPageSettingStatus::Draft)
            ->first();
        $this->assertSame('Draft-only headline', data_get($draft->content_json, 'hero.headline'));
        $this->assertSame(ClientPageSettingStatus::Published, $published->status);
    }

    public function test_backfill_is_idempotent_and_does_not_duplicate_on_rerun(): void
    {
        $profile = $this->makeJetpkProfile();
        $this->seedPublishedHome($profile, $this->representativeThreeCardHomeContent());

        $this->runHomepageBackfillMigration();
        $afterFirst = ClientPageSetting::query()
            ->where('client_profile_id', $profile->id)
            ->where('status', ClientPageSettingStatus::Published)
            ->first();
        $countAfterFirst = count($afterFirst->content_json['routes']['items']);

        $this->runHomepageBackfillMigration();
        $afterSecond = ClientPageSetting::query()
            ->where('client_profile_id', $profile->id)
            ->where('status', ClientPageSettingStatus::Published)
            ->first();

        $this->assertSame($countAfterFirst, count($afterSecond->content_json['routes']['items']));
        $this->assertSame($countAfterFirst, count($afterSecond->content_json['destinations']['items']));
    }

    public function test_backfill_does_not_add_when_five_cards_already_exist(): void
    {
        $profile = $this->makeJetpkProfile();
        $content = $this->representativeThreeCardHomeContent();
        $content['routes']['items'][] = [
            'id' => 'route-4', 'from' => 'KHI', 'to' => 'RUH', 'enabled' => '1', 'sort_order' => 3, 'manual_fallback_price' => 70000,
        ];
        $content['routes']['items'][] = [
            'id' => 'route-5', 'from' => 'LHE', 'to' => 'DXB', 'enabled' => '1', 'sort_order' => 4, 'manual_fallback_price' => 80000,
        ];
        $content['destinations']['items'][] = [
            'id' => 'dest-4', 'code' => 'IST', 'title' => 'Istanbul', 'enabled' => '1', 'sort_order' => 3, 'manual_fallback_price' => 90000,
        ];
        $content['destinations']['items'][] = [
            'id' => 'dest-5', 'code' => 'RUH', 'title' => 'Riyadh', 'enabled' => '1', 'sort_order' => 4, 'manual_fallback_price' => 95000,
        ];

        $this->seedPublishedHome($profile, $content);
        $this->runHomepageBackfillMigration();

        $published = ClientPageSetting::query()
            ->where('client_profile_id', $profile->id)
            ->where('status', ClientPageSettingStatus::Published)
            ->first();

        $this->assertCount(5, $published->content_json['routes']['items']);
        $this->assertCount(5, $published->content_json['destinations']['items']);
        $this->assertSame(80000, (int) data_get($published->content_json, 'routes.items.4.manual_fallback_price'));
        $this->assertSame('1', (string) data_get($published->content_json, 'routes.items.4.enabled'));
    }

    public function test_backfill_scopes_to_profiles_with_existing_homepage_rows(): void
    {
        $jetpk = $this->makeJetpkProfile();
        $other = ClientProfile::query()->create([
            'name' => 'Other Client',
            'slug' => 'other-client',
            'environment' => 'staging',
            'active_frontend_theme' => 'v1-classic',
            'active_admin_theme' => 'v1-classic',
            'active_staff_theme' => 'v1-classic',
            'asset_profile' => 'other',
            'default_locale' => 'en',
            'timezone' => 'Asia/Karachi',
            'currency' => 'PKR',
            'is_master_profile' => false,
            'is_active' => true,
        ]);

        $this->seedPublishedHome($jetpk, $this->representativeThreeCardHomeContent());

        $this->runHomepageBackfillMigration();

        $jetpkPublished = ClientPageSetting::query()
            ->where('client_profile_id', $jetpk->id)
            ->where('status', ClientPageSettingStatus::Published)
            ->first();
        $otherPublished = ClientPageSetting::query()
            ->where('client_profile_id', $other->id)
            ->where('status', ClientPageSettingStatus::Published)
            ->first();

        $this->assertCount(4, $jetpkPublished->content_json['routes']['items']);
        $this->assertNull($otherPublished);
        $this->assertSame('custom-route-1', $jetpkPublished->content_json['routes']['items'][0]['id']);
    }
}
