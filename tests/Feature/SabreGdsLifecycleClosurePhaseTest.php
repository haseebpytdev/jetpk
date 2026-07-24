<?php

namespace Tests\Feature;

use App\Enums\BookingCommunicationEvent;
use App\Enums\BookingStatus;
use App\Enums\SupplierProvider;
use App\Models\Agency;
use App\Models\AuditLog;
use App\Models\Booking;
use App\Models\CommunicationLog;
use App\Models\SupplierBooking;
use App\Models\SupplierBookingAttempt;
use App\Models\SupplierConnection;
use App\Services\Communication\BookingCommunicationService;
use App\Services\Suppliers\Sabre\Cancel\SabreBookingCancelService;
use App\Services\Suppliers\Sabre\Cancel\SabreGdsCancellationReconciliationService;
use App\Services\Suppliers\Sabre\Cancel\SabreGdsCancelReadiness;
use App\Support\Sabre\Scenario\SabreGdsLiveScenarioRevalidationGate;
use App\Support\Sabre\Scenario\SabreGdsLiveScenarioRunner;
use App\Support\Sabre\Scenario\SabreGdsLiveScenarioRunnerPassengerLoader;
use Tests\Support\Sabre\BlockingScenarioRevalidationGate;
use Database\Seeders\OtaFoundationSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class SabreGdsLifecycleClosurePhaseTest extends TestCase
{
    use RefreshDatabase;

    public function test_cancellation_reconciliation_updates_booking_without_supplier_http(): void
    {
        Http::fake();
        $booking = $this->seedSabreBookingWithConfirmedCancelEvidence();

        $result = app(SabreGdsCancellationReconciliationService::class)->reconcileFromStoredEvidence($booking, [
            'source' => 'test',
        ]);

        $this->assertTrue($result['success'] ?? false);
        Http::assertNothingSent();

        $booking->refresh();
        $this->assertSame(BookingStatus::Cancelled, $booking->status);
        $this->assertSame('cancelled', $booking->supplier_booking_status);
        $this->assertNotNull($booking->cancelled_at);
        $this->assertSame('FEZJFP', $booking->pnr);

        $supplierBooking = SupplierBooking::query()->where('booking_id', $booking->id)->first();
        $this->assertNotNull($supplierBooking);
        $this->assertSame('cancelled', $supplierBooking->status);

        $this->assertTrue(
            AuditLog::query()
                ->where('auditable_id', $booking->id)
                ->where('action', SabreGdsCancellationReconciliationService::AUDIT_ACTION)
                ->exists()
        );
    }

    public function test_duplicate_cancellation_reconciliation_is_idempotent(): void
    {
        Http::fake();
        $booking = $this->seedSabreBookingWithConfirmedCancelEvidence();
        $service = app(SabreGdsCancellationReconciliationService::class);

        $service->reconcileFromStoredEvidence($booking, ['source' => 'test']);
        $auditCount = AuditLog::query()
            ->where('auditable_id', $booking->id)
            ->where('action', SabreGdsCancellationReconciliationService::AUDIT_ACTION)
            ->count();
        $commCount = CommunicationLog::query()
            ->where('booking_id', $booking->id)
            ->whereIn('event', [BookingCommunicationEvent::BookingCancelled->value, 'booking_cancelled'])
            ->count();

        $second = $service->reconcileFromStoredEvidence($booking->fresh(), ['source' => 'test']);

        $this->assertTrue($second['already_reconciled'] ?? false);
        $this->assertSame($auditCount, AuditLog::query()
            ->where('auditable_id', $booking->id)
            ->where('action', SabreGdsCancellationReconciliationService::AUDIT_ACTION)
            ->count());
        $this->assertSame($commCount, CommunicationLog::query()
            ->where('booking_id', $booking->id)
            ->whereIn('event', [BookingCommunicationEvent::BookingCancelled->value, 'booking_cancelled'])
            ->count());
        Http::assertNothingSent();
    }

    public function test_reconciliation_does_not_invoke_ticketing(): void
    {
        Http::fake();
        $booking = $this->seedSabreBookingWithConfirmedCancelEvidence();

        app(SabreGdsCancellationReconciliationService::class)->reconcileFromStoredEvidence($booking, [
            'source' => 'test',
        ]);

        Http::assertNothingSent();
        $this->assertSame(0, SupplierBookingAttempt::query()
            ->where('booking_id', $booking->id)
            ->where('action', 'ticket')
            ->count());
    }

    public function test_retrieve_success_true_when_sync_returns_synced(): void
    {
        $runner = app(SabreGdsLiveScenarioRunner::class);
        $method = new \ReflectionMethod(SabreGdsLiveScenarioRunner::class, 'isRetrieveSuccessful');
        $method->setAccessible(true);

        $this->assertTrue($method->invoke($runner, ['synced' => true]));
        $this->assertFalse($method->invoke($runner, ['synced' => false]));
        $this->assertTrue($method->invoke($runner, ['success' => true]));
        $this->assertFalse($method->invoke($runner, null));
    }

    public function test_duplicate_supplier_booking_created_communication_is_idempotent(): void
    {
        $this->seed(OtaFoundationSeeder::class);
        $agency = Agency::query()->firstOrFail();
        $booking = Booking::factory()->create([
            'agency_id' => $agency->id,
            'pnr' => 'TEST01',
            'supplier_booking_status' => 'pending_ticketing',
        ]);

        $service = app(BookingCommunicationService::class);
        $service->sendSupplierBookingCreated($booking);
        $count = CommunicationLog::query()
            ->where('booking_id', $booking->id)
            ->where('event', BookingCommunicationEvent::SupplierBookingCreated->value)
            ->count();

        $service->sendSupplierBookingCreated($booking->fresh());

        $this->assertSame($count, CommunicationLog::query()
            ->where('booking_id', $booking->id)
            ->where('event', BookingCommunicationEvent::SupplierBookingCreated->value)
            ->count());
    }

    public function test_scenario_runner_blocks_before_booking_when_freshness_not_satisfied(): void
    {
        Config::set('app.env', 'testing');
        Config::set('suppliers.sabre.booking_enabled', true);
        Config::set('suppliers.sabre.booking_live_call_enabled', true);
        Config::set('suppliers.sabre.pnr_create_enabled', true);
        Config::set('suppliers.sabre.ticketing_enabled', false);
        Config::set('suppliers.sabre.booking_mode', 'pnr_only');

        $this->app->instance(SabreGdsLiveScenarioRevalidationGate::class, new BlockingScenarioRevalidationGate);
        $this->app->forgetInstance(SabreGdsLiveScenarioRunner::class);

        $this->fakeSabreShopOnly();
        $conn = $this->seedConnection();
        $passengerPath = $this->writeMinimalPassengerJson();
        $before = Booking::query()->count();

        $summary = app(SabreGdsLiveScenarioRunner::class)->run([
            'mode' => 'book',
            'connection_id' => $conn->id,
            'departure_date' => '2026-08-15',
            'operator_approved' => true,
            'passenger_json' => $passengerPath,
            'max_bookings' => 1,
        ]);

        $this->assertSame($before, Booking::query()->count());
        $scenario = $summary['scenario_results'][0] ?? [];
        $this->assertSame('scenario_revalidation_failed', $scenario['error'] ?? null);
        $this->assertFalse($scenario['booking_created'] ?? true);
        $this->assertFalse($scenario['pnr_attempted'] ?? true);
    }

    public function test_invalid_passenger_json_returns_safe_reason_not_output_safety_failure(): void
    {
        Config::set('app.env', 'testing');
        Storage::fake('local');
        $conn = $this->seedConnection();
        $path = storage_path('app/test-invalid-passenger.json');
        file_put_contents($path, '{"email":"not-an-email"}');

        $summary = app(SabreGdsLiveScenarioRunner::class)->run([
            'mode' => 'book',
            'connection_id' => $conn->id,
            'departure_date' => '2026-08-15',
            'operator_approved' => true,
            'passenger_json' => $path,
        ]);

        $this->assertSame('passenger_json_invalid', $summary['error'] ?? null);
        $this->assertSame(
            SabreGdsLiveScenarioRunnerPassengerLoader::REASON_VALIDATION_FAILED,
            $summary['reason_code'] ?? null,
        );
        $this->assertNotSame('output_safety_check_failed', $summary['error'] ?? null);
    }

    public function test_reconcile_cancellation_command_outputs_result(): void
    {
        Http::fake();
        $booking = $this->seedSabreBookingWithConfirmedCancelEvidence();

        $exit = Artisan::call('sabre:gds-reconcile-cancellation', [
            '--booking' => (string) $booking->id,
        ]);

        $this->assertSame(0, $exit);
        $output = Artisan::output();
        $this->assertStringContainsString('"success": true', $output);
        $this->assertStringContainsString('"classification": "'.SabreBookingCancelService::CLASSIFICATION_CANCEL_CONFIRMED_AIR_SEGMENTS_REMOVED.'"', $output);
    }

    protected function seedSabreBookingWithConfirmedCancelEvidence(): Booking
    {
        $this->seed(OtaFoundationSeeder::class);
        $agency = Agency::query()->firstOrFail();
        $conn = SupplierConnection::query()
            ->where('provider', SupplierProvider::Sabre)
            ->firstOrFail();

        $booking = Booking::factory()->create([
            'agency_id' => $agency->id,
            'status' => BookingStatus::Confirmed,
            'supplier' => SupplierProvider::Sabre->value,
            'pnr' => 'FEZJFP',
            'supplier_reference' => 'FEZJFP',
            'supplier_booking_status' => 'pending_ticketing',
            'meta' => [
                'supplier_connection_id' => $conn->id,
                SabreGdsCancelReadiness::META_KEY => [
                    'status' => 'cancelled',
                    'classification' => SabreBookingCancelService::CLASSIFICATION_CANCEL_CONFIRMED_AIR_SEGMENTS_REMOVED,
                    'supplier_cancel_verified' => true,
                    'post_cancel_segment_count' => 0,
                ],
            ],
        ]);

        SupplierBooking::query()->create([
            'agency_id' => $agency->id,
            'booking_id' => $booking->id,
            'supplier_connection_id' => $conn->id,
            'provider' => SupplierProvider::Sabre->value,
            'pnr' => 'FEZJFP',
            'supplier_reference' => 'FEZJFP',
            'status' => 'pending_ticketing',
        ]);

        SupplierBookingAttempt::query()->create([
            'agency_id' => $agency->id,
            'booking_id' => $booking->id,
            'supplier_connection_id' => $conn->id,
            'provider' => SupplierProvider::Sabre->value,
            'action' => 'cancel_booking',
            'status' => 'success',
            'safe_summary' => [
                'classification' => SabreBookingCancelService::CLASSIFICATION_CANCEL_CONFIRMED_AIR_SEGMENTS_REMOVED,
            ],
            'attempted_at' => now(),
            'completed_at' => now(),
        ]);

        return $booking->fresh();
    }

    protected function seedConnection(): SupplierConnection
    {
        $this->seed(OtaFoundationSeeder::class);
        $agency = Agency::query()->where('slug', 'asif-travels')->firstOrFail();
        $conn = SupplierConnection::query()
            ->where('agency_id', $agency->id)
            ->where('provider', SupplierProvider::Sabre)
            ->firstOrFail();
        $conn->base_url = 'https://api.cert.platform.sabre.com';
        $conn->is_active = true;
        $conn->credentials = ['client_id' => 'cpnr_ci', 'client_secret' => 'cpnr_cs', 'pcc' => 'TEST'];
        $conn->save();

        return $conn;
    }

    protected function fakeSabreShopOnly(): void
    {
        $shopFixture = json_decode(
            file_get_contents(base_path('tests/Fixtures/sabre_bfm_v4_grouped_refs_response.json')),
            true,
        );
        $this->assertIsArray($shopFixture);
        data_set($shopFixture, 'groupedItineraryResponse.itineraryGroups.0.itineraries.0.pricingInformation.0.fare.bookingCode', 'Y');
        data_set($shopFixture, 'groupedItineraryResponse.itineraryGroups.0.itineraries.0.pricingInformation.0.fare.passengerInfoList.0.passengerInfo.fareComponents.0.segments.0.segment.bookingCode', 'Y');
        Http::fake([
            '*/v2/auth/token*' => Http::response(['access_token' => 'fake-token-for-tests-only', 'expires_in' => 1800], 200),
            '*v4/offers/shop*' => Http::response($shopFixture, 200),
        ]);
    }

    protected function writeMinimalPassengerJson(): string
    {
        $path = storage_path('app/test-passenger.json');
        file_put_contents($path, json_encode([
            'title' => 'MR',
            'given_name' => 'Test',
            'surname' => 'Traveler',
            'gender' => 'M',
            'dob' => '1990-01-01',
            'nationality' => 'PK',
            'country' => 'PK',
            'passport_number' => 'AB1234567',
            'passport_issue_date' => '2020-01-01',
            'passport_expiry_date' => '2030-01-01',
            'phone' => '3001234567',
            'email' => 'traveler@example.com',
        ]));

        return $path;
    }
}
