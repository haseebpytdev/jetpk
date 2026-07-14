<?php

namespace Tests\Feature;

use App\Enums\AccountType;
use App\Models\Agency;
use App\Models\AgencyCommunicationSetting;
use App\Models\Booking;
use App\Models\BookingContact;
use App\Models\CommunicationLog;
use App\Models\User;
use App\Services\Communication\AgencyCommunicationSettingsService;
use App\Services\Communication\BookingCommunicationService;
use Database\Seeders\OtaFoundationSeeder;
use Illuminate\Foundation\Http\Middleware\ValidateCsrfToken;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use Tests\Support\PlatformAdminTestHelpers;
use Tests\TestCase;

class CommunicationSettingsPhase195BTest extends TestCase
{
    use PlatformAdminTestHelpers;
    use RefreshDatabase;

    public function test_platform_admin_can_view_update_settings_and_secrets_are_masked_and_encrypted(): void
    {
        $this->withoutMiddleware(ValidateCsrfToken::class);
        $admin = $this->platformAdmin();

        $this->actingAs($admin)->get(route('admin.settings.communications.index'))->assertOk();
        $this->actingAs($admin)->patch(route('admin.settings.communications.update'), [
            'email_enabled' => 1,
            'smtp_enabled' => 1,
            'smtp_host' => 'smtp.example.test',
            'smtp_port' => 587,
            'smtp_username' => 'smtp-user',
            'smtp_password' => 'super-secret-pass',
            'smtp_encryption' => 'tls',
            'mail_from_name' => 'Aurora Mailer',
            'mail_from_email' => 'no-reply@aurora.test',
        ])->assertRedirect();

        $settings = AgencyCommunicationSetting::query()->where('agency_id', $admin->current_agency_id)->firstOrFail();
        $this->assertSame('********', $settings->maskedSmtpPassword());
        $raw = (string) DB::table('agency_communication_settings')->where('id', $settings->id)->value('smtp_password');
        $this->assertStringNotContainsString('super-secret-pass', $raw);
    }

    public function test_staff_agent_customer_cannot_update_and_cross_agency_scope_is_preserved(): void
    {
        $this->withoutMiddleware(ValidateCsrfToken::class);
        $this->seed(OtaFoundationSeeder::class);
        $staff = User::query()->where('email', 'staff@ota.demo')->firstOrFail();
        $agent = User::query()->where('email', 'agent@ota.demo')->firstOrFail();
        $customer = User::factory()->create(['account_type' => 'customer', 'current_agency_id' => $staff->current_agency_id]);

        $this->actingAs($staff)->patch(route('admin.settings.communications.update'), ['smtp_host' => 'x'])->assertForbidden();
        $this->actingAs($agent)->patch(route('admin.settings.communications.update'), ['smtp_host' => 'x'])->assertForbidden();
        $this->actingAs($customer)->patch(route('admin.settings.communications.update'), ['smtp_host' => 'x'])->assertForbidden();

        $otherAgency = Agency::factory()->create();
        $otherAdmin = User::factory()->create([
            'current_agency_id' => $otherAgency->id,
            'account_type' => AccountType::PlatformAdmin,
            'status' => 'active',
        ]);
        $otherAgency->users()->attach($otherAdmin->id, ['role' => 'platform_admin']);
        $this->actingAs($otherAdmin)->patch(route('admin.settings.communications.update'), ['smtp_host' => 'other.smtp.test'])->assertRedirect();
        $this->assertDatabaseHas('agency_communication_settings', ['agency_id' => $otherAgency->id, 'smtp_host' => 'other.smtp.test']);
        $this->assertDatabaseMissing('agency_communication_settings', ['agency_id' => $staff->current_agency_id, 'smtp_host' => 'other.smtp.test']);
    }

    public function test_legacy_agency_admin_cannot_access_communication_settings(): void
    {
        $this->seed(OtaFoundationSeeder::class);
        $legacy = $this->legacyAgencyAdminFromSeed();

        $this->actingAs($legacy)->get(route('admin.settings.communications.index'))->assertForbidden();
    }

    public function test_platform_admin_can_edit_template_and_safe_rendering_does_not_execute_php_or_blade(): void
    {
        $this->withoutMiddleware(ValidateCsrfToken::class);
        $admin = $this->platformAdmin();

        $this->actingAs($admin)->patch(route('admin.settings.communications.templates.update', ['event' => 'booking_confirmed', 'channel' => 'email']), [
            'subject' => 'Booking {{ booking_reference }} for {{ agency_name }}',
            'body' => 'Hi {{ passenger_name }} {{ strtoupper("x") }} <?php echo "bad"; ?>',
            'is_enabled' => 1,
            'variables' => ['agency_name', 'booking_reference', 'passenger_name'],
        ])->assertRedirect();

        $agency = Agency::query()->findOrFail($admin->current_agency_id);
        $rendered = app(AgencyCommunicationSettingsService::class)->renderTemplate($agency, 'booking_confirmed', 'email', [
            'agency_name' => 'Aurora',
            'booking_reference' => 'BK-1',
            'passenger_name' => 'Ali',
        ]);
        $this->assertStringContainsString('BK-1', $rendered['subject'] ?? '');
        $this->assertStringContainsString('Ali', $rendered['body']);
        $this->assertStringContainsString('{{ strtoupper("x") }}', $rendered['body']);
        $this->assertStringContainsString('<?php echo "bad"; ?>', $rendered['body']);
    }

    public function test_booking_communication_respects_email_disabled_and_template_disabled_rules(): void
    {
        Mail::fake();
        $admin = $this->platformAdmin();
        $agency = Agency::query()->findOrFail($admin->current_agency_id);
        $booking = Booking::factory()->create(['agency_id' => $agency->id, 'booking_reference' => 'BK-LOG-1']);
        BookingContact::query()->create(['booking_id' => $booking->id, 'email' => 'guest@example.test', 'phone' => '+9200000']);

        app(AgencyCommunicationSettingsService::class)->updateSettings($agency, $admin, ['email_enabled' => false]);
        app(BookingCommunicationService::class)->sendBookingConfirmed($booking);
        $this->assertDatabaseHas('communication_logs', [
            'agency_id' => $agency->id,
            'event' => 'booking_confirmed',
            'channel' => 'email',
            'status' => 'skipped',
        ]);

        app(AgencyCommunicationSettingsService::class)->updateSettings($agency, $admin, ['email_enabled' => true]);
        app(AgencyCommunicationSettingsService::class)->updateTemplate($agency, $admin, 'booking_confirmed', 'email', [
            'subject' => 'Disabled template',
            'body' => 'Body',
            'is_enabled' => false,
        ]);
        app(BookingCommunicationService::class)->sendBookingConfirmed($booking->fresh());
        $this->assertTrue(
            CommunicationLog::query()
                ->where('agency_id', $agency->id)
                ->where('event', 'booking_confirmed')
                ->where('channel', 'email')
                ->where('status', 'skipped')
                ->latest('id')
                ->exists()
        );
    }

    public function test_test_email_and_whatsapp_readiness_and_sidebar_links_work(): void
    {
        Mail::fake();
        $this->withoutMiddleware(ValidateCsrfToken::class);
        $admin = $this->platformAdmin();
        $agency = Agency::query()->findOrFail($admin->current_agency_id);

        $this->actingAs($admin)->post(route('admin.settings.communications.test-email'), [
            'recipient_email' => 'ops@example.test',
        ])->assertRedirect();
        $this->assertDatabaseHas('communication_logs', [
            'agency_id' => $agency->id,
            'event' => 'settings_test_email',
            'recipient_email' => 'ops@example.test',
        ]);

        $missing = app(AgencyCommunicationSettingsService::class)->testWhatsappReadiness($agency, $admin);
        $this->assertSame('missing_fields', $missing['status']);

        app(AgencyCommunicationSettingsService::class)->updateSettings($agency, $admin, [
            'whatsapp_provider' => 'meta_cloud_api',
            'whatsapp_phone_number_id' => 'pnid',
            'whatsapp_business_account_id' => 'baid',
            'whatsapp_access_token' => 'token-1',
        ]);
        $ready = app(AgencyCommunicationSettingsService::class)->testWhatsappReadiness($agency, $admin);
        $this->assertSame('ready_for_review', $ready['status']);

        $this->actingAs($admin)->get(route('admin.dashboard'))
            ->assertOk()
            ->assertSee('Communications')
            ->assertSee('Email Templates');
    }
}
