<?php

namespace Tests\Feature\Admin;

use App\Enums\OtaNotificationEvent;
use App\Http\Controllers\Admin\AgencyMessageTemplateController;
use App\Models\Agency;
use App\Models\AgencyMessageTemplate;
use App\Models\User;
use App\Support\Emails\EmailTemplateRegistry;
use Database\Seeders\OtaFoundationSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Mail;
use Tests\Support\PlatformAdminTestHelpers;
use Tests\TestCase;

class EmailTemplateRegistryTest extends TestCase
{
    use PlatformAdminTestHelpers;
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        \App\Models\ClientProfile::query()->firstOrCreate(
            ['slug' => 'jetpk'],
            [
                'name' => 'Jet Pakistan',
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
        config(['ota.default_client_slug' => 'jetpk']);
    }

    public function test_platform_admin_can_view_email_template_registry(): void
    {
        Mail::fake();
        $admin = $this->platformAdmin();

        $this->actingAs($admin)
            ->get(route('admin.settings.communications.templates.index'))
            ->assertRedirect('/admin/dashboard/settings/notifications');

        $html = $this->templatesIndexHtml($admin);
        $this->assertStringContainsString('Email Event Content', $html);
        $this->assertStringContainsString('data-testid="email-event-content-list"', $html);
        $this->assertStringContainsString('booking_request_received', $html);
        $this->assertStringContainsString('Content blocks', $html);

        Mail::assertNothingSent();
    }

    public function test_platform_admin_can_open_rendered_preview(): void
    {
        Mail::fake();
        $admin = $this->platformAdmin();
        $agencyId = $admin->current_agency_id;

        AgencyMessageTemplate::query()->create([
            'agency_id' => $agencyId,
            'event' => 'booking_confirmed',
            'channel' => 'email',
            'subject' => 'Booking {{ booking_reference }} confirmed',
            'body' => 'Hello {{ customer_name }}, your booking is confirmed.',
            'is_enabled' => true,
        ]);

        $this->actingAs($admin)
            ->get(route('admin.settings.communications.templates.preview', ['registryKey' => 'ops-booking_confirmed']))
            ->assertOk()
            ->assertSee('Email Template Preview', false)
            ->assertSee('data-testid="preview-subject"', false)
            ->assertSee('data-testid="preview-email-iframe"', false)
            ->assertSee('GXJDHD8K', false)
            ->assertSee('Edit template', false)
            ->assertDontSee('planned for I4', false);

        Mail::assertNothingSent();
    }

    public function test_platform_admin_preview_shows_not_connected_for_future_migration(): void
    {
        $admin = $this->platformAdmin();

        $this->actingAs($admin)
            ->get(route('admin.settings.communications.templates.preview', ['registryKey' => 'customer-booking_confirmed']))
            ->assertOk()
            ->assertSee('data-testid="preview-not-connected-warning"', false)
            ->assertDontSee('Edit template', false);
    }

    public function test_guest_cannot_access_preview(): void
    {
        $this->get(route('admin.settings.communications.templates.preview', ['registryKey' => 'ops-booking_confirmed']))
            ->assertRedirect();
    }

    public function test_customer_agent_and_staff_cannot_access_preview(): void
    {
        $this->seed(OtaFoundationSeeder::class);
        $staff = User::query()->where('email', 'staff@ota.demo')->firstOrFail();
        $agent = User::query()->where('email', 'agent@ota.demo')->firstOrFail();
        $customer = User::factory()->create([
            'account_type' => 'customer',
            'current_agency_id' => $staff->current_agency_id,
        ]);
        $url = route('admin.settings.communications.templates.preview', ['registryKey' => 'ops-booking_confirmed']);

        $this->actingAs($staff)->get($url)->assertForbidden();
        $this->actingAs($agent)->get($url)->assertForbidden();
        $this->actingAs($customer)->get($url)->assertForbidden();
    }

    public function test_guest_cannot_access_registry(): void
    {
        $this->get(route('admin.settings.communications.templates.index'))->assertRedirect();
    }

    public function test_customer_agent_and_staff_cannot_access_registry(): void
    {
        $this->seed(OtaFoundationSeeder::class);
        $staff = User::query()->where('email', 'staff@ota.demo')->firstOrFail();
        $agent = User::query()->where('email', 'agent@ota.demo')->firstOrFail();
        $customer = User::factory()->create([
            'account_type' => 'customer',
            'current_agency_id' => $staff->current_agency_id,
        ]);

        $this->actingAs($staff)->get(route('admin.settings.communications.templates.index'))->assertForbidden();
        $this->actingAs($agent)->get(route('admin.settings.communications.templates.index'))->assertForbidden();
        $this->actingAs($customer)->get(route('admin.settings.communications.templates.index'))->assertForbidden();
    }

    public function test_platform_admin_can_open_edit_for_missing_template_row(): void
    {
        $admin = $this->platformAdmin();

        $this->actingAs($admin)
            ->get(route('admin.settings.communications.templates.edit', [
                'event' => 'booking_manual_review_required',
                'channel' => 'email',
            ]))
            ->assertRedirect('/admin/dashboard/settings/notifications');

        $html = $this->templatesEditHtml($admin, 'booking_manual_review_required', 'email');
        $this->assertStringContainsString('Edit Event Content', $html);
        $this->assertStringContainsString('data-testid="template-edit-create-notice"', $html);
        $this->assertStringContainsString('{{agency_name}}', $html);
        $this->assertStringContainsString('{{booking_reference}}', $html);
        $this->assertStringNotContainsString('&lt;?php', $html);
    }

    public function test_preview_iframe_uses_rendered_html_not_escaped_markup(): void
    {
        $admin = $this->platformAdmin();

        $response = $this->actingAs($admin)
            ->get(route('admin.settings.communications.templates.preview', ['registryKey' => 'ops-booking_confirmed']))
            ->assertOk();

        $html = $response->getContent();
        $this->assertStringContainsString('srcdoc="<!DOCTYPE html>', $html);
        $this->assertStringNotContainsString('srcdoc="&lt;!DOCTYPE html&gt;', $html);
        $this->assertStringContainsString('data-testid="preview-html-source"', $html);
    }

    public function test_registry_index_shows_admin_friendly_labels(): void
    {
        $admin = $this->platformAdmin();

        $this->actingAs($admin)
            ->get(route('admin.settings.communications.templates.index'))
            ->assertRedirect('/admin/dashboard/settings/notifications');

        $html = $this->templatesIndexHtml($admin);
        $this->assertStringContainsString('Customized', $html);
        $this->assertStringContainsString('Default', $html);
        $this->assertStringContainsString('Preview', $html);
        $this->assertStringContainsString('Customize content', $html);
    }

    public function test_registry_list_uses_platform_agency_templates(): void
    {
        $admin = $this->platformAdmin();
        $platformAgency = Agency::query()->where('slug', config('ota.default_agency_slug'))->firstOrFail();

        $this->assertSame($platformAgency->id, $admin->current_agency_id);

        $rows = EmailTemplateRegistry::listForAgency($platformAgency);
        $this->assertGreaterThan(count(OtaNotificationEvent::cases()), count($rows));
    }

    private function templatesIndexHtml(User $admin): string
    {
        $this->actingAs($admin);
        $response = app(AgencyMessageTemplateController::class)->index(Request::create('/admin/settings/communications/templates', 'GET'));

        return $response->render();
    }

    private function templatesEditHtml(User $admin, string $event, string $channel): string
    {
        $this->actingAs($admin);
        $response = app(AgencyMessageTemplateController::class)->edit(
            Request::create("/admin/settings/communications/templates/{$event}/{$channel}/edit", 'GET'),
            $event,
            $channel,
        );

        return $response->render();
    }
}
