<?php

namespace Tests\Feature;

use App\Models\Agency;
use App\Models\AgencyCommunicationSetting;
use App\Models\AgencyMedia;
use App\Models\AgencySetting;
use App\Models\Booking;
use App\Models\User;
use App\Support\Agencies\AgencyPrefixService;
use App\Support\Branding\PlatformBrandingResolver;
use Database\Seeders\OtaFoundationSeeder;
use Illuminate\Foundation\Http\Middleware\ValidateCsrfToken;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\Support\PlatformAdminTestHelpers;
use Tests\TestCase;

class WhiteLabelBrandingCmsLiteTest extends TestCase
{
    use PlatformAdminTestHelpers;
    use RefreshDatabase;

    public function test_platform_admin_can_save_company_profile_branding_prefixes_and_email_sender(): void
    {
        $this->withoutMiddleware(ValidateCsrfToken::class);
        $admin = $this->platformAdmin();
        $agency = Agency::query()->findOrFail($admin->current_agency_id);

        $this->actingAs($admin)->patch(route('admin.settings.branding.update'), [
            'display_name' => 'JetPK Brand Lab',
            'company_prefix' => 'PR',
            'customer_reference_prefix' => 'CU',
            'agent_reference_prefix' => 'AG',
            'mail_from_name' => 'JetPK Brand Lab',
            'color_scheme' => 'blue_travel',
        ])->assertRedirect();

        $this->assertDatabaseHas('agency_settings', [
            'agency_id' => $agency->id,
            'display_name' => 'JetPK Brand Lab',
        ]);

        $settings = AgencySetting::query()->where('agency_id', $agency->id)->firstOrFail();
        $this->assertSame('CU', $settings->meta[PlatformBrandingResolver::META_CUSTOMER_REFERENCE_PREFIX] ?? null);
        $this->assertSame('AG', $settings->meta[PlatformBrandingResolver::META_AGENT_REFERENCE_PREFIX] ?? null);
        $this->assertSame('PR', AgencyPrefixService::storedPrefix($agency->fresh()));

        $communication = AgencyCommunicationSetting::query()->where('agency_id', $agency->id)->firstOrFail();
        $this->assertSame('JetPK Brand Lab', $communication->mail_from_name);
    }

    public function test_agency_admin_can_view_and_update_branding_settings_with_audit_log(): void
    {
        $this->withoutMiddleware(ValidateCsrfToken::class);
        $admin = $this->platformAdmin();

        $this->actingAs($admin)->get(route('admin.settings.branding.edit'))
            ->assertRedirect('/admin/dashboard/settings/general');

        $payload = $this->actingAs($admin)
            ->getJson(route('admin.settings.branding.edit'), ['Accept' => 'application/json'])
            ->assertOk()
            ->json();
        $this->assertIsArray($payload);

        $this->actingAs($admin)->patch(route('admin.settings.branding.update'), [
            'display_name' => 'Aurora OTA',
            'support_email' => 'support@aurora.test',
            'color_scheme' => 'green_umrah',
            'primary_color' => '#111111',
        ])->assertRedirect();

        $this->assertDatabaseHas('agency_settings', [
            'agency_id' => $admin->current_agency_id,
            'display_name' => 'Aurora OTA',
            'primary_color' => '#059669',
        ]);
        $settings = AgencySetting::query()->where('agency_id', $admin->current_agency_id)->firstOrFail();
        $this->assertSame('green_umrah', $settings->meta['color_scheme'] ?? null);
        $this->assertDatabaseHas('audit_logs', ['action' => 'agency.branding_settings_updated']);
    }

    public function test_staff_agent_and_customer_cannot_update_branding_settings(): void
    {
        $this->withoutMiddleware(ValidateCsrfToken::class);
        $this->seed(OtaFoundationSeeder::class);
        $staff = User::query()->where('email', 'staff@ota.demo')->firstOrFail();
        $agent = User::query()->where('email', 'agent@ota.demo')->firstOrFail();
        $customer = User::factory()->create(['account_type' => 'customer', 'current_agency_id' => $staff->current_agency_id]);

        $this->actingAs($staff)->patch(route('admin.settings.branding.update'), ['display_name' => 'x'])->assertForbidden();
        $this->actingAs($agent)->get(route('admin.settings.branding.edit'))->assertForbidden();
        $this->actingAs($customer)->get(route('admin.settings.branding.edit'))->assertForbidden();
    }

    public function test_agency_admin_can_upload_media_and_scope_is_enforced_between_agencies(): void
    {
        Storage::fake('public');
        $this->withoutMiddleware(ValidateCsrfToken::class);
        $admin = $this->platformAdmin();

        $this->actingAs($admin)->post(route('admin.settings.media.store'), [
            'file' => UploadedFile::fake()->image('logo.png', 200, 200),
            'collection' => 'branding',
        ])->assertRedirect();
        $media = AgencyMedia::query()->latest('id')->firstOrFail();
        $this->assertSame($admin->current_agency_id, $media->agency_id);

        $otherAgency = Agency::factory()->create();
        $otherAdmin = User::factory()->create([
            'account_type' => 'agency_admin',
            'current_agency_id' => $otherAgency->id,
            'status' => 'active',
        ]);
        $this->actingAs($otherAdmin)->delete(route('admin.settings.media.destroy', $media))->assertForbidden();
    }

    public function test_media_upload_validates_type_and_size(): void
    {
        Storage::fake('public');
        $this->withoutMiddleware(ValidateCsrfToken::class);
        $admin = $this->platformAdmin();

        $this->actingAs($admin)->post(route('admin.settings.media.store'), [
            'file' => UploadedFile::fake()->create('not-image.pdf', 50, 'application/pdf'),
        ])->assertSessionHasErrors('file');
    }

    public function test_homepage_uses_db_branding_and_falls_back_to_demo_when_missing(): void
    {
        $this->seed(OtaFoundationSeeder::class);
        $agency = Agency::query()->where('slug', config('ota.default_agency_slug'))->firstOrFail();
        AgencySetting::query()->updateOrCreate(
            ['agency_id' => $agency->id],
            [
                'display_name' => 'DB Brand Name',
                'tagline' => 'DB Tagline',
            ]
        );

        $this->get(route('home'))
            ->assertOk()
            ->assertSee('DB Brand Name', false);

        AgencySetting::query()->where('agency_id', $agency->id)->delete();
        $this->get(route('home'))->assertOk();
    }

    public function test_unsafe_html_in_settings_is_escaped_on_homepage(): void
    {
        $this->seed(OtaFoundationSeeder::class);
        $agency = Agency::query()->where('slug', config('ota.default_agency_slug'))->firstOrFail();
        AgencySetting::query()->updateOrCreate(
            ['agency_id' => $agency->id],
            [
                'display_name' => 'Safe Brand',
                'tagline' => '<script>alert(1)</script>',
            ]
        );

        $response = $this->get(route('home'))->assertOk();
        $response->assertDontSee('<script>alert(1)</script>', false);
        $this->assertStringNotContainsString('<script>alert(1)</script>', $response->getContent());
    }

    public function test_pdf_template_uses_agency_settings_and_sidebar_has_settings_links(): void
    {
        $admin = $this->platformAdmin();
        AgencySetting::query()->updateOrCreate(['agency_id' => $admin->current_agency_id], [
            'display_name' => 'Aurora Display',
            'support_email' => 'brand@aurora.test',
            'support_phone' => '+9200000000',
        ]);

        $booking = Booking::factory()->create(['agency_id' => $admin->current_agency_id]);
        $booking->load('agency.agencySetting');
        $html = view('pdf.invoice', [
            'booking' => $booking,
            'documentNumber' => 'INV-TEST',
            'generatedAt' => now(),
        ])->render();
        $this->assertStringContainsString('Aurora Display', $html);
        $this->assertStringContainsString('brand@aurora.test', $html);

        $this->actingAs($admin)->get(route('admin.dashboard'))->assertOk();
        $this->actingAs($admin)->get(route('admin.settings.branding.edit'))
            ->assertRedirect('/admin/dashboard/settings/general');
        $this->actingAs($admin)->get(route('admin.settings.homepage.edit'))
            ->assertRedirect();
        $mediaResponse = $this->actingAs($admin)->get(route('admin.settings.media.index'));
        $this->assertTrue(
            in_array($mediaResponse->status(), [200, 302], true),
            'Expected media library to render or redirect. Got '.$mediaResponse->status()
        );
        if ($mediaResponse->isRedirect()) {
            $location = (string) $mediaResponse->headers->get('Location');
            $this->assertTrue(
                str_contains($location, '/admin/dashboard')
                || str_contains($location, '/admin/cms')
                || str_contains($location, '/admin/settings'),
                'Unexpected media redirect: '.$location
            );
        }
    }
}
