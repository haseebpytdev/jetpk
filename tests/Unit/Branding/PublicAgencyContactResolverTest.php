<?php

namespace Tests\Unit\Branding;

use App\Models\Agency;
use App\Models\AgencySetting;
use App\Support\Branding\PublicAgencyContactResolver;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PublicAgencyContactResolverTest extends TestCase
{
    use RefreshDatabase;

    public function test_resolves_contact_from_agency_settings(): void
    {
        $agency = Agency::factory()->create(['name' => 'Legal Name']);
        AgencySetting::query()->create([
            'agency_id' => $agency->id,
            'display_name' => 'Display Agency',
            'support_email' => 'help@agency.test',
            'support_phone' => '+92 300 1111111',
            'support_whatsapp' => '923001111111',
            'office_address' => 'Suite 9',
            'city' => 'Lahore',
        ]);

        $contact = PublicAgencyContactResolver::resolve(AgencySetting::query()->first());

        $this->assertSame('Display Agency', $contact->agencyName);
        $this->assertSame('help@agency.test', $contact->email);
        $this->assertSame('+92 300 1111111', $contact->phone);
        $this->assertSame('923001111111', $contact->whatsapp);
        $this->assertSame('https://wa.me/923001111111', $contact->whatsappUrl());
        $this->assertSame('Suite 9', $contact->address);
        $this->assertSame('Lahore', $contact->city);
    }

    public function test_falls_back_to_config_without_settings(): void
    {
        config()->set('ota-client', [
            'support_email' => 'client@example.test',
            'support_phone' => '+92 300 2222222',
            'support_whatsapp' => '923002222222',
            'office_city' => 'Karachi',
        ]);
        config()->set('ota-brand', [
            'support_email' => 'brand@example.test',
        ]);

        $contact = PublicAgencyContactResolver::resolve(null);

        $this->assertSame('client@example.test', $contact->email);
        $this->assertSame('+92 300 2222222', $contact->phone);
        $this->assertSame('923002222222', $contact->whatsapp);
        $this->assertSame('Karachi', $contact->city);
    }
}
