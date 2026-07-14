<?php

namespace Tests\Unit\Branding;

use App\Enums\AccountType;
use App\Models\Agency;
use App\Models\AgencyCommunicationSetting;
use App\Models\AgencySetting;
use App\Models\User;
use App\Support\Branding\CompanyEmailProfileResolver;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CompanyEmailProfileResolverTest extends TestCase
{
    use RefreshDatabase;

    public function test_resolves_default_platform_agency_settings(): void
    {
        $slug = 'platform-co-'.uniqid();
        config()->set('ota.default_agency_slug', $slug);

        $agency = Agency::factory()->create([
            'slug' => $slug,
            'name' => 'Agency Legal Fallback',
        ]);

        AgencySetting::query()->create([
            'agency_id' => $agency->id,
            'display_name' => 'Platform Co Display',
            'legal_name' => 'Platform Co Ltd',
            'support_email' => 'help@platform.test',
            'support_phone' => '+92 300 1111111',
            'website_url' => 'https://platform.test',
            'office_address' => 'Suite 1',
            'city' => 'Karachi',
            'country' => 'Pakistan',
            'primary_color' => '#112233',
            'secondary_color' => '#445566',
            'logo_path' => 'branding/platform-logo.png',
            'footer_copyright' => '© Platform Co',
            'meta' => ['color_scheme' => 'custom'],
        ]);

        $profile = CompanyEmailProfileResolver::resolveForPlatform();

        $this->assertSame('Platform Co Display', $profile->name);
        $this->assertSame('Platform Co Ltd', $profile->legal_name);
        $this->assertSame('help@platform.test', $profile->support_email);
        $this->assertSame('+92 300 1111111', $profile->support_phone);
        $this->assertSame('https://platform.test', $profile->website_url);
        $this->assertSame('Suite 1, Karachi, Pakistan', $profile->address);
        $this->assertSame('#112233', $profile->primary_color);
        $this->assertSame('#445566', $profile->secondary_color);
        $this->assertStringContainsString('platform-logo.png', (string) $profile->logo_url);
        $this->assertSame('© Platform Co', $profile->footer_text);
    }

    public function test_falls_back_to_config_when_db_settings_missing(): void
    {
        config()->set('ota.default_agency_slug', 'missing-agency-'.uniqid());
        config()->set('ota-client', [
            'agency_name' => 'Config Client Name',
            'support_email' => 'client-support@example.test',
            'support_phone' => '+92 300 2222222',
            'office_city' => 'Lahore',
            'footer_text' => 'Config footer note',
            'domain_preview' => 'preview.example.test',
        ]);
        config()->set('ota-brand', [
            'product_name' => 'Config Brand Name',
            'support_email' => 'brand-support@example.test',
            'company_note' => 'Brand company note',
        ]);
        config()->set('mail.from', [
            'address' => 'mailer@example.test',
            'name' => 'Mail Config Name',
        ]);

        $profile = CompanyEmailProfileResolver::resolveForPlatform();

        $this->assertSame('Config Client Name', $profile->name);
        $this->assertSame('client-support@example.test', $profile->support_email);
        $this->assertSame('+92 300 2222222', $profile->support_phone);
        $this->assertSame('https://preview.example.test', $profile->website_url);
        $this->assertSame('Lahore', $profile->address);
        $this->assertSame('mailer@example.test', $profile->mail_from_email);
        $this->assertSame('Mail Config Name', $profile->mail_from_name);
        $this->assertSame('Config footer note', $profile->footer_text);
        $this->assertNull($profile->logo_url);
    }

    public function test_uses_agency_communication_settings_mail_from_and_reply_to(): void
    {
        $slug = 'comm-agency-'.uniqid();
        config()->set('ota.default_agency_slug', $slug);

        $agency = Agency::factory()->create(['slug' => $slug, 'name' => 'Comm Agency']);

        AgencySetting::query()->create([
            'agency_id' => $agency->id,
            'display_name' => 'Comm Agency',
            'support_email' => 'agency-support@example.test',
        ]);

        AgencyCommunicationSetting::query()->create([
            'agency_id' => $agency->id,
            'mail_from_name' => 'OTA Mailer',
            'mail_from_email' => 'noreply@ota.test',
            'reply_to_email' => 'replies@ota.test',
            'smtp_password' => 'super-secret-smtp',
            'whatsapp_access_token' => 'super-secret-wa',
        ]);

        $profile = CompanyEmailProfileResolver::resolveForPlatform();

        $this->assertSame('OTA Mailer', $profile->mail_from_name);
        $this->assertSame('noreply@ota.test', $profile->mail_from_email);
        $this->assertSame('replies@ota.test', $profile->reply_to_email);

        $array = $profile->toArray();
        $this->assertArrayNotHasKey('smtp_password', $array);
        $this->assertArrayNotHasKey('whatsapp_access_token', $array);
        $this->assertStringNotContainsString('super-secret', json_encode($array, JSON_THROW_ON_ERROR));
    }

    public function test_does_not_depend_on_logged_in_platform_admin_user(): void
    {
        $slug = 'admin-independent-'.uniqid();
        config()->set('ota.default_agency_slug', $slug);

        $agency = Agency::factory()->create(['slug' => $slug, 'name' => 'Shared Platform Agency']);

        AgencySetting::query()->create([
            'agency_id' => $agency->id,
            'display_name' => 'Shared Platform Brand',
            'support_email' => 'shared@platform.test',
        ]);

        $adminA = User::factory()->create([
            'name' => 'Admin Alice Personal',
            'account_type' => AccountType::PlatformAdmin,
            'current_agency_id' => $agency->id,
        ]);

        $adminB = User::factory()->create([
            'name' => 'Admin Bob Personal',
            'account_type' => AccountType::PlatformAdmin,
            'current_agency_id' => $agency->id,
        ]);

        $this->actingAs($adminA);
        $profileAsAlice = CompanyEmailProfileResolver::resolveForPlatform();

        $this->actingAs($adminB);
        $profileAsBob = CompanyEmailProfileResolver::resolveForPlatform();

        $this->assertSame($profileAsAlice->toArray(), $profileAsBob->toArray());
        $this->assertSame('Shared Platform Brand', $profileAsAlice->name);
        $this->assertNotSame('Admin Alice Personal', $profileAsAlice->name);
        $this->assertNotSame('Admin Bob Personal', $profileAsBob->name);
    }

    public function test_to_array_exposes_only_safe_identity_fields(): void
    {
        $profile = CompanyEmailProfileResolver::resolveForPlatform();
        $keys = array_keys($profile->toArray());

        sort($keys);

        $this->assertSame([
            'address',
            'footer_text',
            'legal_name',
            'logo_url',
            'mail_from_email',
            'mail_from_name',
            'name',
            'primary_color',
            'reply_to_email',
            'secondary_color',
            'support_email',
            'support_phone',
            'website_url',
        ], $keys);
    }

    public function test_resolve_without_agency_uses_platform_slug_not_current_user_agency(): void
    {
        $platformSlug = 'platform-only-'.uniqid();
        $otherSlug = 'other-agency-'.uniqid();

        config()->set('ota.default_agency_slug', $platformSlug);

        $platformAgency = Agency::factory()->create(['slug' => $platformSlug, 'name' => 'Platform']);
        $otherAgency = Agency::factory()->create(['slug' => $otherSlug, 'name' => 'Other']);

        AgencySetting::query()->create([
            'agency_id' => $platformAgency->id,
            'display_name' => 'Platform Brand From Slug',
        ]);

        AgencySetting::query()->create([
            'agency_id' => $otherAgency->id,
            'display_name' => 'Other Agency Brand',
        ]);

        $userOnOtherAgency = User::factory()->create([
            'name' => 'Wrong User Agency Context',
            'current_agency_id' => $otherAgency->id,
        ]);

        $this->actingAs($userOnOtherAgency);

        $profile = CompanyEmailProfileResolver::resolveForPlatform();

        $this->assertSame('Platform Brand From Slug', $profile->name);
    }
}
