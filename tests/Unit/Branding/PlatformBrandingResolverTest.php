<?php

namespace Tests\Unit\Branding;

use App\Models\Agency;
use App\Models\AgencyCommunicationSetting;
use App\Models\AgencySetting;
use App\Models\Agent;
use App\Models\Booking;
use App\Support\Agencies\AgencyPrefixService;
use App\Support\Bookings\BookingReferencePresenter;
use App\Support\Branding\PlatformBrandingResolver;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PlatformBrandingResolverTest extends TestCase
{
    use RefreshDatabase;

    public function test_resolves_company_name_prefixes_and_email_sender_from_admin_settings(): void
    {
        $slug = 'brand-co-'.uniqid();
        config()->set('ota.default_agency_slug', $slug);
        config()->set('mail.from.name', 'OTA');
        config()->set('app.name', 'OTA');

        $agency = Agency::factory()->create(['slug' => $slug, 'name' => 'Parwaaz Travels']);
        AgencyPrefixService::savePrefix($agency, 'PR');

        AgencySetting::query()->create([
            'agency_id' => $agency->id,
            'display_name' => 'Parwaaz Travels',
            'meta' => [
                PlatformBrandingResolver::META_CUSTOMER_REFERENCE_PREFIX => 'CU',
                PlatformBrandingResolver::META_AGENT_REFERENCE_PREFIX => 'AG',
            ],
        ]);

        AgencyCommunicationSetting::query()->create([
            'agency_id' => $agency->id,
            'mail_from_name' => 'Parwaaz Travels',
        ]);

        $branding = PlatformBrandingResolver::forPlatform();

        $this->assertSame('Parwaaz Travels', $branding->companyName());
        $this->assertSame('PR', $branding->companyPrefix());
        $this->assertSame('CU', $branding->customerPrefix());
        $this->assertSame('AG', $branding->agentPrefix());
        $this->assertSame('Parwaaz Travels', $branding->emailFromName());
    }

    public function test_apply_runtime_config_sets_app_and_mail_from_names(): void
    {
        $slug = 'runtime-brand-'.uniqid();
        config()->set('ota.default_agency_slug', $slug);
        config()->set('mail.from.name', 'OTA');
        config()->set('app.name', 'OTA');

        $agency = Agency::factory()->create(['slug' => $slug]);
        AgencySetting::query()->create([
            'agency_id' => $agency->id,
            'display_name' => 'Parwaaz Travels',
        ]);
        AgencyCommunicationSetting::query()->create([
            'agency_id' => $agency->id,
            'mail_from_name' => 'Parwaaz Travels Support',
        ]);

        PlatformBrandingResolver::applyRuntimeConfig();

        $this->assertSame('Parwaaz Travels', config('app.name'));
        $this->assertSame('Parwaaz Travels Support', config('mail.from.name'));
    }

    public function test_existing_ota_stored_reference_displays_unchanged(): void
    {
        $agency = Agency::factory()->create();
        AgencyPrefixService::savePrefix($agency, 'PR');
        AgencySetting::query()->create(['agency_id' => $agency->id, 'display_name' => 'Parwaaz Travels']);

        $booking = Booking::factory()->create([
            'agency_id' => $agency->id,
            'source_channel' => 'public_guest',
            'booking_reference' => 'OTA-XJU4G8RH',
        ]);

        $this->assertSame('OTA-XJU4G8RH', BookingReferencePresenter::forPortal($booking));
    }

    public function test_agent_booking_displays_stored_reference_unchanged(): void
    {
        $agency = Agency::factory()->create();
        AgencyPrefixService::savePrefix($agency, 'PR');
        $agent = Agent::factory()->create(['agency_id' => $agency->id]);

        $booking = Booking::factory()->create([
            'agency_id' => $agency->id,
            'agent_id' => $agent->id,
            'source_channel' => 'agent_portal',
            'booking_reference' => 'OTA-XJU4G8RH',
        ]);

        $this->assertSame('OTA-XJU4G8RH', BookingReferencePresenter::forPortal($booking));
    }

    public function test_lookup_reference_candidates_include_display_and_stored_forms(): void
    {
        $slug = 'lookup-brand-'.uniqid();
        config()->set('ota.default_agency_slug', $slug);
        $agency = Agency::factory()->create(['slug' => $slug]);
        AgencyPrefixService::savePrefix($agency, 'PR');
        AgencySetting::query()->create(['agency_id' => $agency->id, 'display_name' => 'Parwaaz']);

        $candidates = PlatformBrandingResolver::lookupReferenceCandidates('PR-CU-XJU4G8RH');

        $this->assertContains('PR-CU-XJU4G8RH', $candidates);
        $this->assertContains('XJU4G8RH', $candidates);
        $this->assertContains('OTA-XJU4G8RH', $candidates);
        $this->assertContains('PR-XJU4G8RH', $candidates);
    }
}
