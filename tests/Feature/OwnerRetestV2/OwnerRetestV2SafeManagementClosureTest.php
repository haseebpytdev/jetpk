<?php

namespace Tests\Feature\OwnerRetestV2;

use App\Enums\BookingStatus;
use App\Enums\MarkupRuleStatus;
use App\Enums\MarkupRuleType;
use App\Enums\MarkupValueType;
use App\Enums\SupplierConnectionStatus;
use App\Enums\SupplierEnvironment;
use App\Enums\SupplierProvider;
use App\Http\Resources\Dashboard\DashboardOverviewResource;
use App\Http\Resources\Dashboard\DashboardSettingsResource;
use App\Models\Agency;
use App\Models\AuditLog;
use App\Models\Booking;
use App\Models\BookingFareBreakdown;
use App\Models\CmsPage;
use App\Models\CommunicationLog;
use App\Models\MarkupRule;
use App\Models\SupplierConnection;
use App\Services\Booking\BookingService;
use App\Services\Bookings\FareHoldService;
use App\Services\Dashboard\AgencyDashboardService;
use App\Support\Dashboard\BookingOperationalMoneyResolver;
use App\Support\Dashboard\CommunicationFailureClassifier;
use App\Support\Dashboard\DashboardMoneyPresenter;
use Database\Seeders\OtaFoundationSeeder;
use Illuminate\Foundation\Http\Middleware\ValidateCsrfToken;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\Support\PlatformAdminTestHelpers;
use Tests\TestCase;

class OwnerRetestV2SafeManagementClosureTest extends TestCase
{
    use PlatformAdminTestHelpers;
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutMiddleware(ValidateCsrfToken::class);
    }

    public function test_usd_quote_hold_persists_commercial_pkr_without_copying_usd(): void
    {
        $this->seed(OtaFoundationSeeder::class);
        $agency = Agency::query()->where('slug', 'asif-travels')->firstOrFail();

        $session = app(FareHoldService::class)->refreshHoldSession(
            agency: $agency,
            booking: null,
            searchId: 'qa-pkr-usd-1',
            offerId: 'qa-usd-offer',
            normalizedOffer: [
                'offer_id' => 'qa-usd-offer',
                'currency' => 'USD',
                'total' => 590,
                'supplier_currency' => 'USD',
                'supplier_total' => 590,
                'pricing_components' => [
                    'supplier_total_source' => 590,
                    'supplier_currency' => 'USD',
                    'pricing_currency' => 'PKR',
                    'conversion_status' => 'converted',
                    'fx_rate' => 278.8136,
                    'final_total' => 164500,
                    'admin_markup' => 2000,
                ],
            ],
            user: null,
            holdStatus: 'not_started',
            safeError: null,
        );

        $this->assertSame('USD', strtoupper((string) $session->validated_total_currency));
        $this->assertEqualsWithDelta(590.0, (float) $session->validated_total_amount, 0.01);
        $this->assertEqualsWithDelta(164500.0, (float) $session->converted_total_pkr, 0.01);
        $this->assertSame('USD', data_get($session->meta, 'commercial_money.original_supplier_currency'));
        $this->assertEqualsWithDelta(590.0, (float) data_get($session->meta, 'commercial_money.original_supplier_amount'), 0.01);
        $this->assertNotEquals(590, (int) $session->converted_total_pkr);
    }

    public function test_sar_and_pkr_quote_holds_keep_authoritative_pkr(): void
    {
        $this->seed(OtaFoundationSeeder::class);
        $agency = Agency::query()->where('slug', 'asif-travels')->firstOrFail();
        $holds = app(FareHoldService::class);

        $sar = $holds->refreshHoldSession(
            agency: $agency,
            booking: null,
            searchId: 'qa-pkr-sar-1',
            offerId: 'qa-sar-offer',
            normalizedOffer: [
                'offer_id' => 'qa-sar-offer',
                'currency' => 'SAR',
                'total' => 1000,
                'pricing_components' => [
                    'supplier_currency' => 'SAR',
                    'pricing_currency' => 'PKR',
                    'conversion_status' => 'converted',
                    'final_total' => 74200,
                ],
            ],
            user: null,
            holdStatus: 'not_started',
            safeError: null,
        );
        $this->assertEqualsWithDelta(74200.0, (float) $sar->converted_total_pkr, 0.01);
        $this->assertSame('SAR', strtoupper((string) $sar->validated_total_currency));

        $pkr = $holds->refreshHoldSession(
            agency: $agency,
            booking: null,
            searchId: 'qa-pkr-pkr-1',
            offerId: 'qa-pkr-offer',
            normalizedOffer: [
                'offer_id' => 'qa-pkr-offer',
                'currency' => 'PKR',
                'total' => 50000,
                'pricing_components' => [
                    'supplier_currency' => 'PKR',
                    'pricing_currency' => 'PKR',
                    'conversion_status' => 'same_currency',
                    'final_total' => 50000,
                ],
            ],
            user: null,
            holdStatus: 'not_started',
            safeError: null,
        );
        $this->assertEqualsWithDelta(50000.0, (float) $pkr->converted_total_pkr, 0.01);
        $this->assertSame('PKR', strtoupper((string) $pkr->validated_total_currency));
    }

    public function test_booking_fare_keeps_usd_while_kpi_uses_pkr_snapshot(): void
    {
        $admin = $this->platformAdmin();
        $agencyId = (int) $admin->current_agency_id;

        $booking = Booking::factory()->create([
            'agency_id' => $agencyId,
            'status' => BookingStatus::Pending,
            'currency' => 'USD',
            'meta' => ['converted_total_pkr' => 164500, 'original_currency' => 'USD'],
        ]);

        $fare = app(BookingService::class)->attachFareBreakdown($booking->fresh(), [
            'total' => 590,
            'currency' => 'USD',
            'breakdown' => [],
        ]);

        $this->assertSame('USD', $fare->currency);
        $this->assertEqualsWithDelta(590.0, (float) $fare->total, 0.01);
        $this->assertEqualsWithDelta(164500.0, (float) BookingOperationalMoneyResolver::pkrSnapshotAmount($booking->fresh()), 0.01);

        $presented = DashboardMoneyPresenter::presentBookingTotal($booking->fresh(), 590);
        $this->assertSame('USD', $presented['currency']);

        $dashboard = app(AgencyDashboardService::class)->build($admin);
        $overview = DashboardOverviewResource::fromAgencyDashboard($dashboard, $admin);
        $grossCard = collect($overview['summaryStats'] ?? [])->firstWhere('key', 'gross_sales');
        $this->assertNotNull($grossCard);
        $this->assertStringContainsString('Rs.', (string) ($grossCard['value'] ?? ''));
        $this->assertStringContainsString('164,500', (string) ($grossCard['value'] ?? ''));
    }

    public function test_organization_profile_json_round_trip_writes_audit(): void
    {
        $admin = $this->platformAdmin();

        $get = $this->actingAs($admin)->getJson('/admin/settings/branding?format=json');
        $get->assertOk()->assertJsonPath('ok', true);
        $this->assertIsArray($get->json('organization'));

        $save = $this->actingAs($admin)->patchJson('/admin/settings/branding?format=json', [
            'display_name' => 'JetPakistan QA SoT',
            'support_email' => 'qa-sot@jetpakistan.test',
            'support_phone' => '+92 300 0000111',
            'timezone' => 'Asia/Karachi',
            'legal_name' => 'JetPakistan QA',
        ]);
        $save->assertOk()->assertJsonPath('ok', true);
        $this->assertSame('JetPakistan QA SoT', $save->json('organization.display_name'));
        $this->assertSame('qa-sot@jetpakistan.test', $save->json('organization.support_email'));
        $this->assertSame('+92 300 0000111', $save->json('organization.support_phone'));
        $this->assertSame('Asia/Karachi', $save->json('organization.timezone'));

        $general = DashboardSettingsResource::general($admin->fresh());
        $this->assertSame('JetPakistan QA SoT', $general['organizationDisplayName']);
        $this->assertSame('qa-sot@jetpakistan.test', $general['supportEmail']);
        $this->assertSame('+92 300 0000111', $general['supportPhone']);
        $this->assertSame('Asia/Karachi', $general['timezone']);

        $this->assertTrue(
            AuditLog::query()->where('action', 'agency.branding_settings_updated')->exists()
        );
    }

    public function test_notification_categories_json_update_and_failure_classification(): void
    {
        $admin = $this->platformAdmin();
        $agencyId = (int) $admin->current_agency_id;

        $this->actingAs($admin)->getJson('/admin/settings/communications/notification-events?format=json')
            ->assertOk()
            ->assertJsonPath('ok', true);

        $this->actingAs($admin)->patchJson('/admin/settings/communications/notification-events?format=json', [
            'categories' => [[
                'key' => 'booking',
                'enabled' => true,
                'emailChannel' => true,
                'dashboardChannel' => true,
                'severityThreshold' => 'warning',
                'deliveryMode' => 'digest',
                'recipientRoles' => ['admin', 'staff'],
                'eventKeys' => ['booking_confirmed'],
            ]],
        ])->assertOk()->assertJsonPath('ok', true);

        CommunicationLog::query()->create([
            'agency_id' => $agencyId,
            'channel' => 'email',
            'event' => 'settings_test_email',
            'recipient_email' => 'jp-dash-03-qa-admin@jetpakistan.pk',
            'status' => 'failed',
            'error_message' => 'QA fixture',
        ]);
        CommunicationLog::query()->create([
            'agency_id' => $agencyId,
            'channel' => 'email',
            'event' => 'booking_confirmed',
            'recipient_email' => 'customer@example.test',
            'status' => 'failed',
            'error_message' => 'SMTP timeout',
        ]);

        $operational = CommunicationLog::query()->where('status', 'failed');
        CommunicationFailureClassifier::constrain($operational, true, false);
        $qa = CommunicationLog::query()->where('status', 'failed');
        CommunicationFailureClassifier::constrain($qa, false, true);

        $this->assertSame(1, $operational->count());
        $this->assertSame(1, $qa->count());
        $this->assertSame('booking_confirmed', $operational->value('event'));
    }

    public function test_inactive_markup_json_create_toggle_delete_without_applies_to_json(): void
    {
        $admin = $this->platformAdmin();

        $create = $this->actingAs($admin)->postJson('/admin/markups?format=json', [
            'name' => 'QA inactive route markup',
            'rule_type' => MarkupRuleType::Route->value,
            'value' => 2000,
            'value_type' => MarkupValueType::Fixed->value,
            'priority' => 100,
            'status' => MarkupRuleStatus::Inactive->value,
            'origin' => 'LHE',
            'destination' => 'JED',
            'route_direction' => 'both',
        ]);
        $create->assertOk()->assertJsonPath('ok', true);
        $id = (string) $create->json('markup.id');
        $rule = MarkupRule::query()->findOrFail($id);
        $this->assertSame(['origin' => 'LHE', 'destination' => 'JED', 'direction' => 'both'], $rule->applies_to);
        $this->assertFalse((bool) $rule->is_active);

        $this->actingAs($admin)->patchJson('/admin/markups/'.$id.'/toggle-status?format=json')->assertOk();
        $this->actingAs($admin)->patchJson('/admin/markups/'.$id.'/toggle-status?format=json')->assertOk();
        $this->assertFalse((bool) $rule->fresh()->is_active);

        $this->actingAs($admin)->deleteJson('/admin/markups/'.$id.'?format=json')->assertOk();
        $this->assertDatabaseMissing('markup_rules', ['id' => $id]);
    }

    public function test_qa_cms_page_json_draft_preview_publish_unpublish(): void
    {
        $admin = $this->platformAdmin();
        $blocks = '<section data-jp-block="heading"><h2>QA heading</h2></section>'
            .'<section data-jp-block="paragraph"><p>QA copy.</p></section>';

        $create = $this->actingAs($admin)->postJson('/admin/cms-pages?format=json', [
            'title' => 'QA Owner Retest Page',
            'slug' => 'qa-owner-retest-v2-page',
            'content' => $blocks,
            'excerpt' => 'QA only',
            'seo_title' => 'QA SEO',
            'seo_description' => 'QA description',
            'robots' => CmsPage::ROBOTS_NOINDEX,
            'status' => CmsPage::STATUS_DRAFT,
        ]);
        $create->assertOk()->assertJsonPath('ok', true);
        $pageId = (int) data_get($create->json(), 'page.internalId', $create->json('page.id'));
        $page = CmsPage::query()->where('slug', 'qa-owner-retest-v2-page')->firstOrFail();

        $this->get(route('pages.show', $page->slug))->assertNotFound();
        $this->actingAs($admin)->get(route('admin.cms-pages.preview', $page))
            ->assertOk()
            ->assertSee('QA heading', false);

        $this->actingAs($admin)->patchJson('/admin/cms-pages/'.$page->id.'?format=json', [
            'title' => 'QA Owner Retest Page',
            'slug' => 'qa-owner-retest-v2-page',
            'content' => $blocks,
            'robots' => CmsPage::ROBOTS_NOINDEX,
            'status' => CmsPage::STATUS_ACTIVE,
        ])->assertOk();
        $this->get(route('pages.show', $page->slug))->assertOk()->assertSee('QA heading', false);

        $this->actingAs($admin)->patchJson('/admin/cms-pages/'.$page->id.'/archive?format=json')->assertOk();
        $this->get(route('pages.show', $page->slug))->assertNotFound();

        unset($pageId);
    }

    public function test_cms_list_and_duplicate_json(): void
    {
        $admin = $this->platformAdmin();
        $this->actingAs($admin)->postJson('/admin/cms-pages?format=json', [
            'title' => 'QA List Page',
            'slug' => 'qa-owner-retest-list-page',
            'content' => '<section data-jp-block="heading"><h2>List</h2></section>',
            'robots' => CmsPage::ROBOTS_NOINDEX,
            'status' => CmsPage::STATUS_DRAFT,
        ])->assertOk();

        $list = $this->actingAs($admin)->getJson(route('api.dashboard.cms.pages.index', ['status' => 'all']));
        $list->assertOk();
        $slugs = collect($list->json('data.pages'))->pluck('slug')->all();
        $this->assertContains('qa-owner-retest-list-page', $slugs);

        $page = CmsPage::query()->where('slug', 'qa-owner-retest-list-page')->firstOrFail();
        $this->actingAs($admin)->postJson('/admin/cms-pages/'.$page->id.'/duplicate?format=json')
            ->assertOk()
            ->assertJsonPath('ok', true);
        $copy = CmsPage::query()->where('slug', 'qa-owner-retest-list-page-copy')->firstOrFail();
        $this->assertSame(CmsPage::STATUS_DRAFT, $copy->status);
    }

    public function test_media_library_json_upload_alt_update_and_delete(): void
    {
        $admin = $this->platformAdmin();
        Storage::fake('public');

        $upload = $this->actingAs($admin)->post('/admin/settings/media?format=json', [
            'file' => UploadedFile::fake()->image('qa-media.png', 40, 40),
            'collection' => 'general',
            'alt_text' => 'QA original alt',
        ], ['Accept' => 'application/json']);
        $upload->assertOk()->assertJsonPath('ok', true);
        $id = (string) $upload->json('media.id');
        $this->assertNotSame('', $id);
        $this->assertSame('QA original alt', $upload->json('media.alt_text'));

        $this->actingAs($admin)->getJson('/admin/settings/media?format=json')
            ->assertOk()
            ->assertJsonPath('ok', true);

        $this->actingAs($admin)->patchJson('/admin/settings/media/'.$id.'?format=json', [
            'alt_text' => 'QA updated alt',
        ])->assertOk()->assertJsonPath('media.alt_text', 'QA updated alt');

        $this->assertTrue(
            AuditLog::query()->where('action', 'agency.media_updated')->exists()
        );

        $this->actingAs($admin)->deleteJson('/admin/settings/media/'.$id.'?format=json')->assertOk();
        $this->assertDatabaseMissing('agency_media', ['id' => $id]);
    }

    public function test_supplier_display_name_json_does_not_wipe_credentials_or_settings(): void
    {
        $admin = $this->platformAdmin();

        $create = $this->actingAs($admin)->postJson('/admin/api-settings?format=json', [
            'provider' => SupplierProvider::Duffel->value,
            'name' => 'QA vendor label original',
            'environment' => SupplierEnvironment::Sandbox->value,
            'status' => SupplierConnectionStatus::Inactive->value,
            'credentials' => ['access_token' => 'qa-keep-token'],
            'settings_json' => json_encode(['keep' => 'yes'], JSON_THROW_ON_ERROR),
        ]);
        $create->assertOk()->assertJsonPath('ok', true);
        $id = (string) $create->json('connection.id');
        $create->assertJsonMissing(['qa-keep-token']);

        $connection = SupplierConnection::query()->findOrFail($id);
        $connection->settings = array_merge(is_array($connection->settings) ? $connection->settings : [], ['keep' => 'yes']);
        $connection->save();

        $update = $this->actingAs($admin)->patchJson('/admin/api-settings/'.$id.'?format=json', [
            'name' => 'QA vendor label updated',
            'provider' => SupplierProvider::Duffel->value,
            'environment' => SupplierEnvironment::Sandbox->value,
        ]);
        $update->assertOk()->assertJsonPath('ok', true);
        $update->assertJsonPath('connection.name', 'QA vendor label updated');
        $update->assertJsonMissing(['qa-keep-token']);

        $fresh = $connection->fresh();
        $this->assertSame('qa-keep-token', data_get($fresh->credentials, 'access_token'));
        $this->assertSame('yes', data_get($fresh->settings, 'keep'));
        $this->assertSame(SupplierConnectionStatus::Inactive, $fresh->status);
        $this->assertFalse((bool) $fresh->is_active);
    }
}
