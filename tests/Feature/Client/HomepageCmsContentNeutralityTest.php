<?php

namespace Tests\Feature\Client;

use App\Enums\ClientPageSettingStatus;
use App\Models\ClientPageSetting;
use App\Models\ClientPageSettingDefault;
use App\Models\ClientPageSettingRevision;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Mail;
use App\Support\Client\ClientPageKeys;
use Tests\Support\JetpkHomepageFixture;
use Tests\TestCase;

/**
 * Proves migrate/boot-only paths do not mutate CMS content or create defaults/revisions.
 */
class HomepageCmsContentNeutralityTest extends TestCase
{
    use JetpkHomepageFixture;
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seedJetpkAirports();
        $this->seedJetpkAgency();
        $this->makeJetpkProfile();
        $this->seedCmsTestUsers();
    }

    public function test_migrate_boot_only_preserves_published_and_draft_hashes(): void
    {
        Mail::fake();
        Http::fake();

        $this->seedCmsTestUsers(1);
        $profile = $this->makeJetpkProfile();
        $publishedContent = [
            'hero' => ['headline' => 'Restored headline', 'subtitle' => 'Neutral proof'],
            'groups' => ['enabled' => '1'],
        ];
        $draftContent = [
            'hero' => ['headline' => 'Draft headline'],
        ];

        $published = $this->seedPublishedHome($profile, $publishedContent);
        $draft = $this->seedDraftHome($profile, $draftContent);

        $beforePublished = hash('sha256', json_encode($published->fresh()->content_json, JSON_THROW_ON_ERROR));
        $beforeDraft = hash('sha256', json_encode($draft->fresh()->content_json, JSON_THROW_ON_ERROR));
        $defaultsBefore = ClientPageSettingDefault::query()->count();
        $revisionsBefore = ClientPageSettingRevision::query()->count();

        Artisan::call('migrate', [
            '--path' => 'database/migrations/2026_07_16_120000_create_client_page_setting_revisions_table.php',
            '--force' => true,
        ]);
        Artisan::call('migrate', [
            '--path' => 'database/migrations/2026_07_16_130000_create_client_page_setting_defaults_table.php',
            '--force' => true,
        ]);

        $this->get('/')->assertOk();

        $afterPublished = hash('sha256', json_encode($published->fresh()->content_json, JSON_THROW_ON_ERROR));
        $afterDraft = hash('sha256', json_encode($draft->fresh()->content_json, JSON_THROW_ON_ERROR));

        $this->assertSame($beforePublished, $afterPublished);
        $this->assertSame($beforeDraft, $afterDraft);
        $this->assertSame($defaultsBefore, ClientPageSettingDefault::query()->count());
        $this->assertSame($revisionsBefore, ClientPageSettingRevision::query()->count());
        $this->assertSame(0, DB::table('client_page_setting_defaults')->count());
        $this->assertSame(0, DB::table('client_page_setting_revisions')->count());
        Mail::assertNothingSent();
        Http::assertNothingSent();
    }
}
