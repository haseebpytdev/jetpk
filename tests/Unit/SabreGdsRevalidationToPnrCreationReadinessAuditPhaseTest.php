<?php

namespace Tests\Unit;

use App\Enums\SupplierConnectionStatus;
use App\Enums\SupplierProvider;
use App\Models\Agency;
use App\Models\SupplierConnection;
use App\Services\Suppliers\Sabre\Booking\SabreBookingService;
use App\Services\Suppliers\Sabre\Cancel\SabreBookingCancelService;
use App\Support\Sabre\Revalidation\SabreGdsRevalidationSanitizedOutcomeContract;
use App\Support\Sabre\Scenario\SabreGdsLiveScenarioRevalidationGate;
use Database\Seeders\OtaFoundationSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class SabreGdsRevalidationToPnrCreationReadinessAuditPhaseTest extends TestCase
{
    use RefreshDatabase;

    private const LINKAGE_FIXTURE = 'tests/fixtures/sabre/revalidation/http-200-informational-warning-31-candidates-linkage.json';

    public function test_readiness_plan_command_is_registered(): void
    {
        Artisan::call('list');
        $this->assertStringContainsString('sabre:gds-revalidation-to-pnr-readiness-plan', Artisan::output());
    }

    public function test_successful_unique_linkage_revalidation_permits_downstream_pnr_authorization_path(): void
    {
        $this->seed(OtaFoundationSeeder::class);
        $conn = $this->seedSabreConnection();
        $fixture = json_decode((string) file_get_contents(base_path(self::LINKAGE_FIXTURE)), true);
        $draft = $fixture['api_draft'];
        $draft['supplier_connection_id'] = $conn->id;

        $this->fakeRevalidateOnly(Http::response($fixture['response'], 200));

        $out = app(SabreBookingService::class)->runRevalidationBeforeBooking($draft, $conn);
        $this->assertTrue($out['success'] ?? false);
        $this->assertSame('sabre_revalidation_ok', $out['reason_code'] ?? null);
        $this->assertTrue($out['usable_fare_linkage'] ?? false);
        $this->assertSame(1, $out['response_linkage_diagnostics']['unique_usable_linkage_match_count'] ?? null);
        $this->assertSame(2, $out['response_linkage_diagnostics']['selected_response_candidate_ordinal'] ?? null);
        $this->assertFalse($out['blocking_application_warning_present'] ?? true);
        $this->assertTrue($out['informational_warning_present'] ?? false);
        $this->assertFalse($out['retry_safe'] ?? true);
    }

    public function test_ambiguous_linkage_blocks_before_pnr_and_does_not_use_candidate_index_zero(): void
    {
        $this->seed(OtaFoundationSeeder::class);
        $conn = $this->seedSabreConnection();
        $fixture = json_decode((string) file_get_contents(base_path(self::LINKAGE_FIXTURE)), true);
        $response = $fixture['response'];
        $second = $response['groupedItineraryResponse']['itineraryGroups'][0]['itineraries'][1];
        $response['groupedItineraryResponse']['itineraryGroups'][0]['itineraries'][] = $second;
        $draft = $fixture['api_draft'];
        $draft['supplier_connection_id'] = $conn->id;

        $this->fakeRevalidateOnly(Http::response($response, 200));

        $out = app(SabreBookingService::class)->runRevalidationBeforeBooking($draft, $conn);
        $this->assertFalse($out['success'] ?? true);
        $this->assertSame('sabre_revalidation_empty_or_unusable_response', $out['reason_code'] ?? null);
        $this->assertNotSame(1, (int) ($out['response_linkage_diagnostics']['unique_usable_linkage_match_count'] ?? 1));
        $this->assertFalse($out['response_linkage_diagnostics']['usable_fare_linkage'] ?? true);
    }

    public function test_blocking_application_warning_blocks_revalidation_success(): void
    {
        $this->seed(OtaFoundationSeeder::class);
        $conn = $this->seedSabreConnection();
        $fixture = json_decode((string) file_get_contents(base_path(self::LINKAGE_FIXTURE)), true);
        $response = $fixture['response'];
        $response['groupedItineraryResponse']['messages'] = [[
            'severity' => 'Warning',
            'type' => 'WARNING',
            'code' => 'BLOCK',
            'text' => 'NO FARES FOR REQUESTED ITINERARY',
        ]];
        $draft = $fixture['api_draft'];
        $draft['supplier_connection_id'] = $conn->id;

        $this->fakeRevalidateOnly(Http::response($response, 200));

        $out = app(SabreBookingService::class)->runRevalidationBeforeBooking($draft, $conn);
        $this->assertFalse($out['success'] ?? true);
        $this->assertContains($out['reason_code'] ?? '', [
            'sabre_revalidation_application_warning_or_error',
            'sabre_revalidation_empty_or_unusable_response',
        ]);
    }

    public function test_changed_fare_tripwire_fails_closed_without_silent_acceptance(): void
    {
        $this->seed(OtaFoundationSeeder::class);
        $conn = $this->seedSabreConnection();
        $fixture = json_decode((string) file_get_contents(base_path(self::LINKAGE_FIXTURE)), true);
        $response = $fixture['response'];
        $response['groupedItineraryResponse']['itineraryGroups'][0]['itineraries'][1]['pricingInformation'][0]['fare']['totalFare']['totalPrice'] = 9999.99;
        $draft = $fixture['api_draft'];
        $draft['supplier_connection_id'] = $conn->id;

        $this->fakeRevalidateOnly(Http::response($response, 200));

        $out = app(SabreBookingService::class)->runRevalidationBeforeBooking($draft, $conn);
        $this->assertFalse($out['success'] ?? true);
        $this->assertSame('sabre_revalidation_empty_or_unusable_response', $out['reason_code'] ?? null);
    }

    public function test_failed_revalidation_produces_zero_pnr_http_calls(): void
    {
        $this->seed(OtaFoundationSeeder::class);
        $sabreBase = rtrim((string) config('suppliers.sabre.default_base_url'), '/');
        $tokenPath = (string) config('suppliers.sabre.token_path', '/v2/auth/token');
        $revalidatePath = '/v4/shop/flights/revalidate';
        $bookingPath = '/v1/trip/orders/createBooking';

        Http::fake([
            $sabreBase.$tokenPath => Http::response(['access_token' => 'tok', 'expires_in' => 3600], 200),
            $sabreBase.$revalidatePath => Http::response(['message' => 'failed'], 422),
            $sabreBase.$bookingPath => Http::response(['recordLocator' => 'SHOULD_NOT'], 200),
        ]);

        $conn = $this->seedSabreConnection();
        $fixture = json_decode((string) file_get_contents(base_path(self::LINKAGE_FIXTURE)), true);
        $draft = $fixture['api_draft'];
        $draft['supplier_connection_id'] = $conn->id;

        $out = app(SabreBookingService::class)->runRevalidationBeforeBooking($draft, $conn);
        $this->assertFalse($out['success'] ?? true);

        Http::assertNotSent(fn ($request) => $request instanceof Request && str_contains($request->url(), $bookingPath));
    }

    public function test_exact_linked_candidate_reaches_linkage_payload_with_revalidated_total(): void
    {
        $this->seed(OtaFoundationSeeder::class);
        $conn = $this->seedSabreConnection();
        $fixture = json_decode((string) file_get_contents(base_path(self::LINKAGE_FIXTURE)), true);
        $draft = $fixture['api_draft'];
        $draft['supplier_connection_id'] = $conn->id;

        $this->fakeRevalidateOnly(Http::response($fixture['response'], 200));

        $out = app(SabreBookingService::class)->runRevalidationBeforeBooking($draft, $conn);
        $linkage = is_array($out['linkage'] ?? null) ? $out['linkage'] : [];
        $this->assertSame(520.83, (float) ($linkage['revalidated_total'] ?? 0));
        $this->assertSame('USD', strtoupper((string) ($linkage['revalidated_currency'] ?? '')));
        $this->assertSame(2, (int) ($out['response_linkage_diagnostics']['selected_response_candidate_ordinal'] ?? 0));
    }

    public function test_scenario_gate_fare_change_requires_acceptance_constant_is_explicit(): void
    {
        $this->assertSame(
            'scenario_fare_change_requires_acceptance',
            SabreGdsLiveScenarioRevalidationGate::REASON_FARE_CHANGE_REQUIRES_ACCEPTANCE,
        );
    }

    public function test_ticketing_remains_disabled_on_issue_ticket(): void
    {
        config(['suppliers.sabre.ticketing_enabled' => false]);
        $result = app(SabreBookingService::class)->issueTicket(
            \App\Models\Booking::factory()->make(),
            \App\Models\User::factory()->make(),
        );
        $this->assertFalse($result['success'] ?? true);
        $this->assertFalse($result['live_call_attempted'] ?? true);
        $this->assertSame('disabled', $result['status'] ?? null);
    }

    public function test_cancel_requires_operator_confirmation_when_live_cancel_disabled(): void
    {
        $this->seed(OtaFoundationSeeder::class);
        config([
            'suppliers.sabre.cancel_enabled' => false,
            'suppliers.sabre.cancel_live_call_enabled' => false,
        ]);
        $gate = app(SabreBookingCancelService::class)->workflowLiveCancelGates(
            $this->seedSabreConnection(),
            false,
        );
        $this->assertFalse($gate['allowed'] ?? true);
        $this->assertFalse($gate['operator_confirmed_admin_staff_action'] ?? true);
        $this->assertSame('sabre_cancel_disabled', $gate['block_reason'] ?? null);
    }

    public function test_contract_does_not_promote_usable_linkage_when_linker_reports_false(): void
    {
        $outcome = SabreGdsRevalidationSanitizedOutcomeContract::wrap([
            'success' => true,
            'http_status' => 200,
            'reason_code' => 'sabre_revalidation_ok',
            'response_linkage_diagnostics' => [
                'usable_fare_linkage' => false,
                'unique_usable_linkage_match_count' => 2,
                'pricing_complete' => true,
            ],
            'linkage_digest' => [
                'per_segment_fare_basis_complete' => true,
                'has_revalidated_fare' => true,
                'has_revalidated_currency' => true,
            ],
        ], true, true);

        $this->assertFalse($outcome['usable_fare_linkage'] ?? true);
    }

    protected function seedSabreConnection(): SupplierConnection
    {
        $agency = Agency::query()->where('slug', 'asif-travels')->firstOrFail();

        return tap(
            SupplierConnection::query()
                ->where('agency_id', $agency->id)
                ->where('provider', SupplierProvider::Sabre->value)
                ->firstOrFail(),
            fn (SupplierConnection $conn) => $conn->update([
                'status' => SupplierConnectionStatus::Active,
                'credentials' => ['client_id' => 'cid', 'client_secret' => 'sec', 'pcc' => 'TEST'],
            ]),
        );
    }

    protected function fakeRevalidateOnly(mixed $revalidateResponse): void
    {
        $sabreBase = rtrim((string) config('suppliers.sabre.default_base_url'), '/');
        $tokenPath = (string) config('suppliers.sabre.token_path', '/v2/auth/token');
        $revalidatePath = '/v4/shop/flights/revalidate';

        Http::fake([
            $sabreBase.$tokenPath => Http::response(['access_token' => 'tok', 'expires_in' => 3600], 200),
            $sabreBase.$revalidatePath => $revalidateResponse,
        ]);
    }
}
