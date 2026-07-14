<?php

namespace Tests\Feature;

use App\Enums\MarkupRuleStatus;
use App\Enums\MarkupRuleType;
use App\Enums\MarkupValueType;
use App\Models\Agency;
use App\Models\Agent;
use App\Models\Booking;
use App\Models\MarkupRule;
use App\Models\User;
use App\Services\Pricing\PricingRuleService;
use Database\Seeders\OtaFoundationSeeder;
use Illuminate\Foundation\Http\Middleware\ValidateCsrfToken;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Support\PublicBookingPassengersPayload;
use Tests\Support\PublicCheckoutTestDoubles;
use Tests\TestCase;

class MarkupPricingEngineTest extends TestCase
{
    use RefreshDatabase;

    public function test_agency_admin_can_view_markup_rules(): void
    {
        $this->seed(OtaFoundationSeeder::class);
        $admin = User::query()->where('email', 'admin@ota.demo')->firstOrFail();

        $this->actingAs($admin)->get('/admin/markups')->assertOk();
    }

    public function test_agency_admin_can_create_markup_rule(): void
    {
        $this->seed(OtaFoundationSeeder::class);
        $admin = User::query()->where('email', 'admin@ota.demo')->firstOrFail();

        $this->actingAs($admin)->post('/admin/markups', [
            'name' => 'Test global markup',
            'rule_type' => MarkupRuleType::Global->value,
            'value' => 4.5,
            'value_type' => MarkupValueType::Percentage->value,
            'priority' => 90,
            'status' => MarkupRuleStatus::Active->value,
        ])->assertRedirect('/admin/markups');

        $this->assertDatabaseHas('markup_rules', [
            'name' => 'Test global markup',
            'status' => MarkupRuleStatus::Active->value,
        ]);
    }

    public function test_agency_admin_can_edit_own_markup_rule(): void
    {
        $this->seed(OtaFoundationSeeder::class);
        $admin = User::query()->where('email', 'admin@ota.demo')->firstOrFail();
        $rule = MarkupRule::query()->firstOrFail();

        $this->actingAs($admin)->patch('/admin/markups/'.$rule->id, [
            'name' => 'Updated rule',
            'rule_type' => $rule->rule_type->value,
            'value' => 8,
            'value_type' => $rule->value_type->value,
            'priority' => 50,
            'status' => MarkupRuleStatus::Active->value,
        ])->assertRedirect('/admin/markups');

        $rule->refresh();
        $this->assertSame('Updated rule', $rule->name);
    }

    public function test_agency_admin_cannot_edit_another_agency_markup_rule(): void
    {
        $this->seed(OtaFoundationSeeder::class);
        $admin = User::query()->where('email', 'admin@ota.demo')->firstOrFail();
        $otherAgency = Agency::factory()->create();
        $rule = MarkupRule::factory()->create(['agency_id' => $otherAgency->id]);

        $this->actingAs($admin)
            ->patch('/admin/markups/'.$rule->id, [
                'name' => 'Should fail',
                'rule_type' => MarkupRuleType::Global->value,
                'value' => 2,
                'value_type' => MarkupValueType::Percentage->value,
                'priority' => 100,
                'status' => MarkupRuleStatus::Active->value,
            ])
            ->assertForbidden();
    }

    public function test_staff_cannot_access_admin_markups(): void
    {
        $this->seed(OtaFoundationSeeder::class);
        $staff = User::query()->where('email', 'staff@ota.demo')->firstOrFail();

        $this->actingAs($staff)->get('/admin/markups')->assertForbidden();
    }

    public function test_inactive_markup_rule_is_not_applied(): void
    {
        $this->seed(OtaFoundationSeeder::class);
        $agency = Agency::query()->where('slug', 'asif-travels')->firstOrFail();
        $service = app(PricingRuleService::class);

        MarkupRule::factory()->create([
            'agency_id' => $agency->id,
            'rule_type' => MarkupRuleType::Global,
            'value_type' => MarkupValueType::Fixed,
            'value' => 50000,
            'status' => MarkupRuleStatus::Inactive,
            'is_active' => false,
        ]);

        $result = $service->calculateMarkup($agency, ['base_fare' => 100000, 'carrier_code' => 'PK'], [
            'route' => 'LHE-DXB',
            'airline' => 'pk',
            'supplier' => 'duffel',
            'source_channel' => 'public_guest',
        ]);

        $this->assertLessThan(50000, $result['admin_markup']);
    }

    public function test_no_active_markup_rules_apply_zero_markup_and_service_fee(): void
    {
        $agency = Agency::factory()->create();
        $service = app(PricingRuleService::class);

        $result = $service->calculateMarkup($agency, [
            'base_fare' => 100000.0,
            'taxes' => 10000.0,
            'supplier_total' => 110000.0,
            'currency' => 'PKR',
        ], [
            'route' => 'LHE-DXB',
            'airline' => 'pk',
            'supplier' => 'duffel',
            'source_channel' => 'public_guest',
        ]);

        $this->assertSame(0.0, (float) $result['admin_markup']);
        $this->assertSame(0.0, (float) $result['route_markup']);
        $this->assertSame(0.0, (float) $result['airline_markup']);
        $this->assertSame(0.0, (float) $result['agent_markup_or_commission']);
        $this->assertSame(0.0, (float) $result['service_fee']);
        $this->assertEqualsWithDelta(110000.0, (float) $result['final_total'], 0.01);
        $this->assertSame([], $result['applied_rules']);
    }

    public function test_active_global_markup_rule_still_applies_when_configured(): void
    {
        $agency = Agency::factory()->create();
        MarkupRule::factory()->create([
            'agency_id' => $agency->id,
            'rule_type' => MarkupRuleType::Global,
            'value_type' => MarkupValueType::Percentage,
            'value' => 5,
            'status' => MarkupRuleStatus::Active,
            'is_active' => true,
        ]);

        $service = app(PricingRuleService::class);
        $result = $service->calculateMarkup($agency, [
            'base_fare' => 100000.0,
            'taxes' => 10000.0,
            'supplier_total' => 110000.0,
            'currency' => 'PKR',
        ], [
            'route' => 'LHE-DXB',
            'airline' => 'pk',
            'supplier' => 'duffel',
            'source_channel' => 'public_guest',
        ]);

        $this->assertGreaterThan(0, (float) $result['admin_markup']);
        $this->assertEqualsWithDelta(5500.0, (float) $result['admin_markup'], 0.01);
        $this->assertEqualsWithDelta(115500.0, (float) $result['final_total'], 0.01);
        $this->assertNotEmpty($result['applied_rules']);
    }

    public function test_public_booking_applies_only_admin_markup_not_route_or_airline(): void
    {
        $this->seed(OtaFoundationSeeder::class);
        $this->withoutMiddleware(ValidateCsrfToken::class);

        $depart = now()->addDays(12)->toDateString();
        PublicCheckoutTestDoubles::bind($this, $depart, 'LHE', 'DXB');

        $this->post('/booking/passengers', array_merge(
            PublicBookingPassengersPayload::merge([
                'flight_id' => PublicCheckoutTestDoubles::OFFER_ID,
                'offer_id' => PublicCheckoutTestDoubles::OFFER_ID,
                'title' => 'Mr',
                'first_name' => 'Guest',
                'last_name' => 'User',
                'email' => 'guest@example.com',
                'from' => 'LHE',
                'to' => 'DXB',
                'depart' => $depart,
            ]),
            PublicBookingPassengersPayload::internationalDocuments(),
        ))->assertRedirect(route('booking.review'));

        $booking = Booking::query()->latest('id')->firstOrFail();
        $fare = $booking->fareBreakdown()->firstOrFail();
        $snapshot = $booking->meta['pricing_snapshot'] ?? [];
        $ruleTypes = collect($snapshot['applied_rules'] ?? [])->pluck('rule_type')->all();
        $ruleBuckets = collect($snapshot['applied_rules'] ?? [])->pluck('bucket')->all();

        $this->assertContains(MarkupRuleType::Global->value, $ruleTypes);
        $this->assertNotContains(MarkupRuleType::Route->value, $ruleTypes);
        $this->assertNotContains(MarkupRuleType::Airline->value, $ruleTypes);
        $this->assertContains('admin_markup', $ruleBuckets);
        $this->assertTrue((bool) ($snapshot['public_pricing_sanitized'] ?? false));
        $this->assertGreaterThan(0, (float) ($snapshot['public_pricing_rejected_markup'] ?? 0));
        $this->assertEqualsWithDelta(5500.0, (float) $fare->markup, 0.01);
        $this->assertEqualsWithDelta(115500.0, (float) $fare->total, 0.01);
    }

    public function test_route_markup_applies_only_to_matching_route(): void
    {
        $this->seed(OtaFoundationSeeder::class);
        $agency = Agency::query()->where('slug', 'asif-travels')->firstOrFail();
        $service = app(PricingRuleService::class);

        $matched = $service->calculateMarkup($agency, ['base_fare' => 120000, 'carrier_code' => 'PK'], [
            'route' => 'LHE-DXB',
            'airline' => 'pk',
            'supplier' => 'duffel',
            'source_channel' => 'public_guest',
        ]);
        $unmatched = $service->calculateMarkup($agency, ['base_fare' => 120000, 'carrier_code' => 'PK'], [
            'route' => 'KHI-JED',
            'airline' => 'pk',
            'supplier' => 'duffel',
            'source_channel' => 'public_guest',
        ]);

        $this->assertGreaterThan($unmatched['route_markup'], $matched['route_markup']);
    }

    public function test_airline_markup_applies_only_matching_airline(): void
    {
        $this->seed(OtaFoundationSeeder::class);
        $agency = Agency::query()->where('slug', 'asif-travels')->firstOrFail();
        $service = app(PricingRuleService::class);

        $pk = $service->calculateMarkup($agency, ['base_fare' => 120000, 'carrier_code' => 'PK'], [
            'route' => 'LHE-DXB',
            'airline' => 'pk',
            'supplier' => 'duffel',
            'source_channel' => 'public_guest',
        ]);
        $ek = $service->calculateMarkup($agency, ['base_fare' => 120000, 'carrier_code' => 'EK'], [
            'route' => 'LHE-DXB',
            'airline' => 'ek',
            'supplier' => 'duffel',
            'source_channel' => 'public_guest',
        ]);

        $this->assertGreaterThan($ek['airline_markup'], $pk['airline_markup']);
    }

    public function test_agent_source_channel_rule_applies_to_agent_booking(): void
    {
        $this->seed(OtaFoundationSeeder::class);
        $agentUser = User::query()->where('email', 'agent@ota.demo')->firstOrFail();

        $agentDepart = now()->addDays(16)->toDateString();
        $agentRow = Agent::query()->where('user_id', $agentUser->id)->firstOrFail();
        PublicCheckoutTestDoubles::bind($this, $agentDepart, 'LHE', 'DXB', 'agent_portal', $agentRow->id);

        $this->actingAs($agentUser)->post('/agent/bookings', [
            'from' => 'LHE',
            'to' => 'DXB',
            'depart' => $agentDepart,
            'flight_id' => PublicCheckoutTestDoubles::OFFER_ID,
            'title' => 'Mr',
            'first_name' => 'Ali',
            'last_name' => 'Khan',
            'dob' => now()->subYears(30)->toDateString(),
            'nationality' => 'PK',
            'email' => 'ali.customer@example.com',
            'phone' => '+923001112233',
            'country' => 'Pakistan',
        ])->assertRedirect();

        $booking = Booking::query()->latest('id')->firstOrFail();
        $snapshot = $booking->meta['pricing_snapshot'] ?? [];
        $types = collect($snapshot['applied_rules'] ?? [])->pluck('rule_type')->all();

        $this->assertContains(MarkupRuleType::Agent->value, $types);
        $this->assertGreaterThan(0, (float) ($booking->fareBreakdown?->fees ?? 0));
    }

    public function test_applied_rules_are_stored_on_fare_snapshot_meta(): void
    {
        $this->seed(OtaFoundationSeeder::class);
        $this->withoutMiddleware(ValidateCsrfToken::class);

        $departSnap = now()->addDays(12)->toDateString();
        PublicCheckoutTestDoubles::bind($this, $departSnap, 'LHE', 'DXB');

        $this->post('/booking/passengers', array_merge(
            PublicBookingPassengersPayload::merge([
                'flight_id' => PublicCheckoutTestDoubles::OFFER_ID,
                'offer_id' => PublicCheckoutTestDoubles::OFFER_ID,
                'title' => 'Mr',
                'first_name' => 'Snapshot',
                'last_name' => 'Test',
                'email' => 'snap@example.com',
                'from' => 'LHE',
                'to' => 'DXB',
                'depart' => $departSnap,
            ]),
            PublicBookingPassengersPayload::internationalDocuments(),
        ))->assertRedirect();

        $booking = Booking::query()->latest('id')->firstOrFail();
        $snapshot = $booking->meta['pricing_snapshot'] ?? [];

        $this->assertIsArray($snapshot['applied_rules'] ?? null);
        $this->assertNotEmpty($snapshot['applied_rules'] ?? []);
    }

    public function test_public_guest_booking_uses_db_markup_pricing(): void
    {
        $this->seed(OtaFoundationSeeder::class);
        $this->withoutMiddleware(ValidateCsrfToken::class);

        $departPub = now()->addDays(20)->toDateString();
        PublicCheckoutTestDoubles::bind($this, $departPub, 'LHE', 'DXB');

        $this->post('/booking/passengers', array_merge(
            PublicBookingPassengersPayload::merge([
                'flight_id' => PublicCheckoutTestDoubles::OFFER_ID,
                'offer_id' => PublicCheckoutTestDoubles::OFFER_ID,
                'title' => 'Mr',
                'first_name' => 'Public',
                'last_name' => 'Pricing',
                'email' => 'public@example.com',
                'from' => 'LHE',
                'to' => 'DXB',
                'depart' => $departPub,
            ]),
            PublicBookingPassengersPayload::internationalDocuments(),
        ))->assertRedirect(route('booking.review'));

        $booking = Booking::query()->latest('id')->firstOrFail();
        $this->assertGreaterThan(0, (float) ($booking->fareBreakdown?->markup ?? 0));
    }

    public function test_agent_booking_uses_db_markup_pricing(): void
    {
        $this->seed(OtaFoundationSeeder::class);
        $agentUser = User::query()->where('email', 'agent@ota.demo')->firstOrFail();
        $agent = Agent::query()->where('user_id', $agentUser->id)->firstOrFail();

        $agentDepartMk = now()->addDays(16)->toDateString();
        $agentRowMk = Agent::query()->where('user_id', $agentUser->id)->firstOrFail();
        PublicCheckoutTestDoubles::bind($this, $agentDepartMk, 'LHE', 'DXB', 'agent_portal', $agentRowMk->id);

        $this->actingAs($agentUser)->post('/agent/bookings', [
            'from' => 'LHE',
            'to' => 'DXB',
            'depart' => $agentDepartMk,
            'flight_id' => PublicCheckoutTestDoubles::OFFER_ID,
            'title' => 'Mr',
            'first_name' => 'Agent',
            'last_name' => 'Pricing',
            'dob' => now()->subYears(30)->toDateString(),
            'nationality' => 'PK',
            'email' => 'agent.customer@example.com',
            'phone' => '+923001112233',
            'country' => 'Pakistan',
        ])->assertRedirect();

        $booking = Booking::query()->latest('id')->firstOrFail();
        $this->assertSame($agent->id, $booking->agent_id);
        $this->assertGreaterThan(0, (float) ($booking->fareBreakdown?->markup ?? 0));
    }
}
