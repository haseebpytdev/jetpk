<?php

namespace Tests\Feature\Dashboard;

use App\Enums\AccountType;
use App\Enums\BookingStatus;
use App\Enums\PromoCodeAppliesTo;
use App\Enums\PromoCodeStatus;
use App\Enums\PromoCodeType;
use App\Enums\UserAccountStatus;
use App\Models\Agency;
use App\Models\Booking;
use App\Models\PromoCode;
use App\Models\User;
use App\Services\Documents\BookingDocumentService;
use Database\Seeders\OtaFoundationSeeder;
use Illuminate\Foundation\Http\Middleware\ValidateCsrfToken;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Mockery;
use Tests\TestCase;

class JpBo04MissingUiClosureRoundtripTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(OtaFoundationSeeder::class);
        $this->withoutMiddleware(ValidateCsrfToken::class);
    }

    public function test_branding_theme_footer_about_json_roundtrip_masks_and_reloads(): void
    {
        $admin = $this->platformAdmin();

        $load = $this->actingAs($admin)->getJson(route('admin.settings.branding.edit', ['format' => 'json']));
        $load->assertOk()
            ->assertJsonPath('ok', true)
            ->assertJsonStructure(['organization', 'theme', 'footer', 'about']);

        $theme = $this->actingAs($admin)->patchJson(route('admin.settings.branding.update', ['format' => 'json']), [
            'color_scheme' => 'blue_travel',
            'tagline' => 'jp-bo-04 theme',
        ]);
        $theme->assertOk()->assertJsonPath('ok', true)->assertJsonPath('theme.tagline', 'jp-bo-04 theme');

        $footer = $this->actingAs($admin)->patchJson(route('admin.settings.branding.footer.update', ['format' => 'json']), [
            'is_enabled' => true,
            'brand' => ['description' => 'jp-bo-04 footer'],
        ]);
        $footer->assertOk()->assertJsonPath('ok', true);

        $about = $this->actingAs($admin)->patchJson(route('admin.settings.branding.about-us.update', ['format' => 'json']), [
            'plain' => 'jp-bo-04 about',
            'html_active' => false,
        ]);
        $about->assertOk()->assertJsonPath('ok', true)->assertJsonPath('about.plain', 'jp-bo-04 about');
    }

    public function test_email_templates_list_and_update_json(): void
    {
        $admin = $this->platformAdmin();

        $index = $this->actingAs($admin)->getJson(route('admin.settings.communications.templates.index', ['format' => 'json']));
        $index->assertOk()->assertJsonPath('ok', true);
        $templates = $index->json('templates') ?? [];
        $this->assertIsArray($templates);

        if ($templates === []) {
            $this->markTestSkipped('No Jetpk email event content templates registered in this environment.');
        }

        $first = $templates[0];
        $update = $this->actingAs($admin)->patchJson(
            route('admin.settings.communications.templates.update', [
                'event' => $first['event'],
                'channel' => $first['channel'] ?? 'email',
                'format' => 'json',
            ]),
            [
                'subject' => 'JP-BO-04 subject',
                'heading' => 'JP-BO-04 heading',
                'body' => 'JP-BO-04 body content',
                'is_enabled' => true,
            ],
        );
        $update->assertOk()->assertJsonPath('ok', true);
    }

    public function test_promo_codes_crud_toggle_json(): void
    {
        $admin = $this->platformAdmin();

        $create = $this->actingAs($admin)->postJson(route('admin.promo-codes.store', ['format' => 'json']), [
            'code' => 'JPBO04PROMO',
            'name' => 'JP-BO-04 promo',
            'type' => PromoCodeType::Percent->value,
            'value' => 10,
            'applies_to' => PromoCodeAppliesTo::cases()[0]->value,
            'status' => PromoCodeStatus::Active->value,
        ]);
        $create->assertOk()->assertJsonPath('ok', true);
        $promoId = (string) $create->json('promo_code.id');
        $this->assertNotSame('', $promoId);

        $index = $this->actingAs($admin)->getJson(route('admin.promo-codes.index', ['format' => 'json']));
        $index->assertOk()->assertJsonPath('ok', true);

        $toggle = $this->actingAs($admin)->patchJson(
            route('admin.promo-codes.toggle-status', ['promoCode' => $promoId, 'format' => 'json']),
        );
        $toggle->assertOk()->assertJsonPath('ok', true);
        $this->assertSame(PromoCodeStatus::Inactive, PromoCode::query()->findOrFail($promoId)->status);
    }

    public function test_finance_statements_index_show_export_json(): void
    {
        $admin = $this->platformAdmin();
        $agency = Agency::query()->findOrFail($admin->current_agency_id);

        $index = $this->actingAs($admin)->getJson(route('admin.finance.statements.index', ['format' => 'json']));
        $index->assertOk()->assertJsonPath('ok', true)->assertJsonStructure(['rows']);

        $show = $this->actingAs($admin)->getJson(route('admin.finance.statements.show', [
            'agency' => $agency,
            'format' => 'json',
        ]));
        $show->assertOk()->assertJsonPath('ok', true)->assertJsonPath('agency.id', (string) $agency->id);

        $export = $this->actingAs($admin)->get(route('admin.finance.statements.export', ['agency' => $agency]));
        $export->assertOk();
    }

    public function test_booking_document_generate_json_contract(): void
    {
        $admin = $this->platformAdmin();
        $booking = Booking::factory()->create([
            'agency_id' => $admin->current_agency_id,
            'status' => BookingStatus::Confirmed,
        ]);

        $mock = Mockery::mock(BookingDocumentService::class);
        $mock->shouldReceive('generateBookingConfirmation')
            ->once()
            ->andReturnUsing(function () use ($booking, $admin) {
                return \App\Models\BookingDocument::query()->create([
                    'agency_id' => $booking->agency_id,
                    'booking_id' => $booking->id,
                    'document_type' => 'booking_confirmation',
                    'title' => 'Confirmation',
                    'status' => 'generated',
                    'generated_by' => $admin->id,
                    'generated_at' => now(),
                ]);
            });
        $this->app->instance(BookingDocumentService::class, $mock);

        $response = $this->actingAs($admin)->postJson(
            route('admin.bookings.documents.confirmation', ['booking' => $booking, 'format' => 'json']),
        );
        $response->assertOk()
            ->assertJsonPath('ok', true)
            ->assertJsonStructure(['document' => ['id', 'download_url']]);
    }

    protected function platformAdmin(): User
    {
        $agency = Agency::factory()->create();

        return User::factory()->create([
            'account_type' => AccountType::PlatformAdmin,
            'status' => UserAccountStatus::Active,
            'current_agency_id' => $agency->id,
        ]);
    }
}
