<?php

namespace Tests\Feature\Suppliers\AirBlue;

use App\Data\SupplierBookingResultData;
use App\Enums\BookingStatus;
use App\Enums\SupplierConnectionStatus;
use App\Enums\SupplierEnvironment;
use App\Enums\SupplierProvider;
use App\Models\Agency;
use App\Models\AuditLog;
use App\Models\Booking;
use App\Models\SupplierBooking;
use App\Models\SupplierBookingAttempt;
use App\Models\SupplierConnection;
use App\Models\User;
use App\Services\Booking\BookingProviderRouter;
use App\Services\Communication\BookingCommunicationService;
use App\Services\Suppliers\AirBlue\AirBlueBookingService;
use App\Services\Suppliers\AirBlue\AirBlueClient;
use App\Services\Suppliers\AirBlue\AirBlueRetrieveService;
use App\Services\Suppliers\AirBlue\Exceptions\AirBlueUnavailableException;
use App\Support\Bookings\SupplierBookingAttemptGuard;
use Database\Seeders\OtaFoundationSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Foundation\Testing\RefreshDatabaseState;
use Illuminate\Support\Facades\Http;
use Mockery;
use RuntimeException;
use Tests\Support\AirBlue\AirBlueOfflineHttp;
use Tests\Support\PlatformAdminTestHelpers;
use Tests\TestCase;

class AirBlueGenericBookingPipelineTest extends TestCase
{
    use AirBlueOfflineHttp;
    use PlatformAdminTestHelpers;
    use RefreshDatabase;

    /**
     * No wrapping test transaction so DB::afterCommit (uncertain notify) runs when
     * SupplierBookingService's transaction commits. Remigrate each test because
     * :memory: sqlite is empty after application refresh without a shared tx.
     *
     * @var list<string>
     */
    protected $connectionsToTransact = [];

    protected function refreshTestDatabase(): void
    {
        $this->artisan('migrate:fresh', $this->migrateFreshUsing());
        RefreshDatabaseState::$migrated = true;
    }

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(OtaFoundationSeeder::class);
        $this->setUpAirBlueOfflineHttp();
        config(['suppliers.airblue.external_calls_enabled' => false]);
    }

    public function test_success_path_persists_exactly_one_attempt_and_supplier_booking(): void
    {
        $fixture = $this->eligibleAirBlueBooking();
        $actor = $this->platformAdmin();
        $communications = 0;

        $this->mock(BookingCommunicationService::class, function ($mock) use (&$communications): void {
            $mock->shouldReceive('sendSupplierBookingCreated')->once()->andReturnUsing(function () use (&$communications): void {
                $communications++;
            });
            $mock->shouldReceive('notifyManualReviewRequired')->never();
            $mock->shouldReceive('notifySupplierFailure')->never();
            $mock->shouldReceive('logSystemEvent')->zeroOrMoreTimes();
        });

        $this->mock(AirBlueClient::class, function ($mock): void {
            $mock->shouldReceive('callOta')->once()->andReturn([
                'parsed' => [
                    'booking' => ['pnr' => 'ABPNR1', 'instance' => 'INST9'],
                    'pnr' => 'ABPNR1',
                    'instance' => 'INST9',
                ],
                'errors' => [],
                'soap_fault' => null,
            ]);
        });

        $result = app(BookingProviderRouter::class)->createSupplierBooking($fixture['booking']->fresh(), $actor);

        $this->assertTrue($result->success);
        $this->assertSame('ABPNR1', $result->pnr);
        $this->assertSame(1, SupplierBookingAttempt::query()->where('booking_id', $fixture['booking']->id)->count());
        $this->assertSame(1, SupplierBooking::query()->where('booking_id', $fixture['booking']->id)->count());

        $attempt = SupplierBookingAttempt::query()->where('booking_id', $fixture['booking']->id)->firstOrFail();
        $this->assertSame('success', $attempt->status);
        $this->assertSame(SupplierProvider::Airblue->value, $attempt->provider);

        $supplierBooking = SupplierBooking::query()->where('booking_id', $fixture['booking']->id)->firstOrFail();
        $this->assertSame('pending_ticketing', $supplierBooking->status);
        $this->assertSame('ABPNR1', $supplierBooking->supplier_reference);
        $this->assertSame('ABPNR1', $supplierBooking->pnr);
        $this->assertSame($fixture['connection']->id, $supplierBooking->supplier_connection_id);
        $this->assertSame($fixture['booking']->agency_id, $supplierBooking->agency_id);

        $booking = $fixture['booking']->fresh();
        $this->assertSame('ABPNR1', $booking->pnr);
        $this->assertSame('ABPNR1', $booking->supplier_reference);
        $this->assertSame(1, $communications);
        $this->assertTrue(
            AuditLog::query()
                ->where('auditable_id', $booking->id)
                ->where('action', 'booking.supplier_booking_created')
                ->exists(),
        );
        Http::assertNothingSent();
    }

    public function test_uncertain_path_notifies_after_commit_and_blocks_retry(): void
    {
        $fixture = $this->eligibleAirBlueBooking();
        $actor = $this->platformAdmin();
        $manualReview = 0;

        $this->mock(BookingCommunicationService::class, function ($mock) use (&$manualReview): void {
            $mock->shouldReceive('notifyManualReviewRequired')->once()->andReturnUsing(function () use (&$manualReview): void {
                $manualReview++;
            });
            $mock->shouldReceive('sendSupplierBookingCreated')->never();
            $mock->shouldReceive('notifySupplierFailure')->never();
            $mock->shouldReceive('logSystemEvent')->zeroOrMoreTimes();
        });

        $this->mock(AirBlueClient::class, function ($mock): void {
            $mock->shouldReceive('callOta')
                ->once()
                ->andThrow(new AirBlueUnavailableException(
                    'supplier_transport_failed',
                    503,
                    'Provider temporarily unavailable.',
                ));
        });

        $result = app(BookingProviderRouter::class)->createSupplierBooking($fixture['booking']->fresh(), $actor);

        $this->assertFalse($result->success);
        $this->assertSame('manual_review', $result->status);
        $this->assertSame('booking_outcome_uncertain', $result->error_code);
        $this->assertSame(1, SupplierBookingAttempt::query()->where('booking_id', $fixture['booking']->id)->count());
        $this->assertSame(1, SupplierBooking::query()->where('booking_id', $fixture['booking']->id)->count());

        $attempt = SupplierBookingAttempt::query()->where('booking_id', $fixture['booking']->id)->firstOrFail();
        $this->assertSame('uncertain', $attempt->status);

        $supplierBooking = SupplierBooking::query()->where('booking_id', $fixture['booking']->id)->firstOrFail();
        $this->assertSame('pending_reconciliation', $supplierBooking->status);

        $booking = $fixture['booking']->fresh();
        $meta = is_array($booking->meta) ? $booking->meta : [];
        $this->assertSame('uncertain', $meta['airblue_context']['booking_outcome'] ?? null);
        $this->assertTrue((bool) ($meta['airblue_context']['supplier_outcome_unknown'] ?? false));
        $this->assertSame(1, $manualReview, 'manual-review notification must run after commit');
        $this->assertTrue(
            AuditLog::query()
                ->where('auditable_id', $booking->id)
                ->where('action', 'booking.supplier_booking_uncertain')
                ->exists(),
        );

        $retry = app(BookingProviderRouter::class)->createSupplierBooking($booking->fresh(), $actor);
        $this->assertFalse($retry->success);
        $this->assertSame('booking_outcome_uncertain', $retry->error_code);
        $this->assertSame(2, SupplierBookingAttempt::query()->where('booking_id', $booking->id)->count()); // blocked attempt recorded
        $this->assertSame(1, SupplierBooking::query()->where('booking_id', $booking->id)->count());
        Http::assertNothingSent();
    }

    public function test_uncertain_notification_failure_does_not_roll_back_state_or_allow_retry(): void
    {
        $fixture = $this->eligibleAirBlueBooking();
        $actor = $this->platformAdmin();
        $providerCalls = 0;
        $notifyForced = false;

        $this->mock(BookingCommunicationService::class, function ($mock) use (&$notifyForced): void {
            $mock->shouldReceive('notifyManualReviewRequired')
                ->once()
                ->andReturnUsing(function () use (&$notifyForced): void {
                    $notifyForced = true;
                    throw new RuntimeException('forced notification failure');
                });
            $mock->shouldReceive('sendSupplierBookingCreated')->never();
            $mock->shouldReceive('notifySupplierFailure')->never();
            $mock->shouldReceive('logSystemEvent')->zeroOrMoreTimes();
        });

        $this->mock(AirBlueClient::class, function ($mock) use (&$providerCalls): void {
            $mock->shouldReceive('callOta')
                ->once()
                ->andReturnUsing(function () use (&$providerCalls) {
                    $providerCalls++;
                    throw new AirBlueUnavailableException(
                        'supplier_transport_failed',
                        503,
                        'Provider temporarily unavailable.',
                    );
                });
        });

        $result = app(BookingProviderRouter::class)->createSupplierBooking($fixture['booking']->fresh(), $actor);

        $this->assertTrue($notifyForced, 'AIRBLUE_NOTIFICATION_EXCEPTION_FORCED');
        $this->assertSame(1, $providerCalls);
        $this->assertFalse($result->success);
        $this->assertSame('manual_review', $result->status);
        $this->assertSame('booking_outcome_uncertain', $result->error_code);

        $this->assertSame(1, SupplierBookingAttempt::query()->where('booking_id', $fixture['booking']->id)->count());
        $this->assertSame(1, SupplierBooking::query()->where('booking_id', $fixture['booking']->id)->count());
        $this->assertSame('uncertain', SupplierBookingAttempt::query()->where('booking_id', $fixture['booking']->id)->value('status'));
        $this->assertSame('pending_reconciliation', SupplierBooking::query()->where('booking_id', $fixture['booking']->id)->value('status'));

        $booking = $fixture['booking']->fresh();
        $meta = is_array($booking->meta) ? $booking->meta : [];
        $this->assertSame('uncertain', $meta['airblue_context']['booking_outcome'] ?? null);
        $this->assertTrue((bool) ($meta['airblue_context']['supplier_outcome_unknown'] ?? false));
        $this->assertSame('pending_reconciliation', (string) $booking->supplier_booking_status);
        $this->assertTrue(
            AuditLog::query()
                ->where('auditable_id', $booking->id)
                ->where('action', 'booking.supplier_booking_uncertain')
                ->exists(),
        );

        $retry = app(BookingProviderRouter::class)->createSupplierBooking($booking->fresh(), $actor);
        $this->assertFalse($retry->success);
        $this->assertSame('booking_outcome_uncertain', $retry->error_code);
        $this->assertSame(1, $providerCalls, 'SECOND_PROVIDER_CALL must remain 0 after notification failure');
        $this->assertSame(1, SupplierBooking::query()->where('booking_id', $booking->id)->count());
        Http::assertNothingSent();
    }

    public function test_retrieve_reconciles_uncertain_without_new_reservation(): void
    {
        $fixture = $this->eligibleAirBlueBooking();
        $booking = $fixture['booking'];
        $connection = $fixture['connection'];
        $actor = $this->platformAdmin();

        SupplierBookingAttempt::query()->create([
            'agency_id' => $booking->agency_id,
            'booking_id' => $booking->id,
            'supplier_connection_id' => $connection->id,
            'provider' => SupplierProvider::Airblue->value,
            'action' => 'create_pnr',
            'status' => 'uncertain',
            'error_code' => 'booking_outcome_uncertain',
            'attempted_by' => $actor->id,
            'attempted_at' => now(),
            'completed_at' => now(),
            'safe_summary' => ['requires_retrieve_before_retry' => true],
        ]);
        SupplierBooking::query()->create([
            'agency_id' => $booking->agency_id,
            'booking_id' => $booking->id,
            'supplier_connection_id' => $connection->id,
            'provider' => SupplierProvider::Airblue->value,
            'status' => 'pending_reconciliation',
            'created_by' => $actor->id,
            'created_at_supplier' => now(),
        ]);
        $booking->forceFill([
            'meta' => array_merge(is_array($booking->meta) ? $booking->meta : [], [
                'airblue_context' => [
                    'booking_outcome' => 'uncertain',
                    'supplier_outcome_unknown' => true,
                    'pnr' => 'ABPNR1',
                    'instance' => 'INST9',
                    'protocol_version' => '2.0',
                ],
            ]),
        ])->save();

        $this->mock(AirBlueClient::class, function ($mock): void {
            $mock->shouldReceive('callOta')->once()->andReturn([
                'parsed' => [
                    'booking' => ['pnr' => 'ABPNR1', 'instance' => 'INST9'],
                    'pnr' => 'ABPNR1',
                    'instance' => 'INST9',
                    'tickets' => [],
                ],
                'errors' => [],
                'soap_fault' => null,
            ]);
        });

        $synced = app(AirBlueRetrieveService::class)->retrieveAndSync($booking->fresh(), $connection);
        $this->assertTrue($synced['synced'] ?? false);

        $this->assertSame(1, SupplierBookingAttempt::query()->where('booking_id', $booking->id)->count());
        $this->assertSame(1, SupplierBooking::query()->where('booking_id', $booking->id)->count());
        $this->assertSame('reconciled', SupplierBookingAttempt::query()->where('booking_id', $booking->id)->value('status'));
        $this->assertSame('confirmed', SupplierBooking::query()->where('booking_id', $booking->id)->value('status'));
        $this->assertSame('ABPNR1', SupplierBooking::query()->where('booking_id', $booking->id)->value('supplier_reference'));

        $meta = is_array($booking->fresh()->meta) ? $booking->fresh()->meta : [];
        $this->assertSame('reconciled', $meta['airblue_context']['booking_outcome'] ?? null);
        Http::assertNothingSent();
    }

    public function test_concurrent_lock_denies_second_adapter_call(): void
    {
        $fixture = $this->eligibleAirBlueBooking();
        $actor = $this->platformAdmin();
        $providerCalls = 0;

        $lock = app(SupplierBookingAttemptGuard::class)->acquireLock(
            $fixture['booking'],
            SupplierProvider::Airblue->value,
        );
        $this->assertNotNull($lock);

        $this->mock(AirBlueBookingService::class, function ($mock) use (&$providerCalls): void {
            $mock->shouldReceive('createSupplierBooking')->andReturnUsing(function () use (&$providerCalls) {
                $providerCalls++;

                return new SupplierBookingResultData(
                    success: true,
                    status: 'confirmed',
                    provider: SupplierProvider::Airblue->value,
                    supplier_reference: 'SHOULD_NOT',
                    pnr: 'SHOULD_NOT',
                );
            });
        });

        $result = app(BookingProviderRouter::class)->createSupplierBooking($fixture['booking']->fresh(), $actor);

        $this->assertFalse($result->success);
        $this->assertContains($result->error_code, [
            'supplier_booking_lock_active',
            'supplier_booking_in_progress',
            'supplier_booking_already_processing',
        ]);
        $this->assertSame(0, $providerCalls);
        $lock->release();
        Http::assertNothingSent();
    }

    public function test_existing_success_and_uncertain_attempts_block_retry(): void
    {
        $fixture = $this->eligibleAirBlueBooking();
        $actor = $this->platformAdmin();

        $this->mock(AirBlueBookingService::class, function ($mock): void {
            $mock->shouldReceive('createSupplierBooking')->never();
        });

        SupplierBookingAttempt::query()->create([
            'agency_id' => $fixture['booking']->agency_id,
            'booking_id' => $fixture['booking']->id,
            'supplier_connection_id' => $fixture['connection']->id,
            'provider' => SupplierProvider::Airblue->value,
            'action' => 'create_pnr',
            'status' => 'success',
            'supplier_reference' => 'ABPNR1',
            'attempted_by' => $actor->id,
            'attempted_at' => now(),
            'completed_at' => now(),
        ]);

        $successRetry = app(BookingProviderRouter::class)->createSupplierBooking($fixture['booking']->fresh(), $actor);
        $this->assertTrue($successRetry->success);
        $this->assertSame('ABPNR1', $successRetry->supplier_reference);

        $uncertainBooking = $this->eligibleAirBlueBooking()['booking'];
        SupplierBookingAttempt::query()->create([
            'agency_id' => $uncertainBooking->agency_id,
            'booking_id' => $uncertainBooking->id,
            'provider' => SupplierProvider::Airblue->value,
            'action' => 'create_pnr',
            'status' => 'uncertain',
            'error_code' => 'booking_outcome_uncertain',
            'attempted_by' => $actor->id,
            'attempted_at' => now(),
            'completed_at' => now(),
        ]);

        $uncertainRetry = app(BookingProviderRouter::class)->createSupplierBooking($uncertainBooking->fresh(), $actor);
        $this->assertFalse($uncertainRetry->success);
        $this->assertSame('booking_outcome_uncertain', $uncertainRetry->error_code);
        Http::assertNothingSent();
    }

    public function test_financial_fields_unchanged_on_success_and_retrieve(): void
    {
        $fixture = $this->eligibleAirBlueBooking([
            'selected_fare_total' => 14500.00,
            'payable_after_promo' => 14000.00,
            'amount_paid' => 14000.00,
            'balance_due' => 0.00,
            'currency' => 'PKR',
        ]);
        $actor = $this->platformAdmin();
        $before = [
            'selected_fare_total' => (float) $fixture['booking']->selected_fare_total,
            'payable_after_promo' => (float) $fixture['booking']->payable_after_promo,
            'amount_paid' => (float) $fixture['booking']->amount_paid,
            'balance_due' => (float) $fixture['booking']->balance_due,
            'currency' => (string) $fixture['booking']->currency,
        ];

        $this->mock(BookingCommunicationService::class, function ($mock): void {
            $mock->shouldReceive('sendSupplierBookingCreated')->once();
            $mock->shouldReceive('notifyManualReviewRequired')->never();
            $mock->shouldReceive('notifySupplierFailure')->never();
            $mock->shouldReceive('logSystemEvent')->zeroOrMoreTimes();
        });
        $this->mock(AirBlueClient::class, function ($mock): void {
            $mock->shouldReceive('callOta')->once()->andReturn([
                'parsed' => [
                    'booking' => ['pnr' => 'ABPNR1', 'instance' => 'INST9'],
                    'pnr' => 'ABPNR1',
                    'instance' => 'INST9',
                ],
                'errors' => [],
                'soap_fault' => null,
            ]);
        });

        app(BookingProviderRouter::class)->createSupplierBooking($fixture['booking']->fresh(), $actor);
        $afterBook = $fixture['booking']->fresh();
        foreach ($before as $field => $value) {
            $this->assertSame($value, is_float($value) ? (float) $afterBook->{$field} : (string) $afterBook->{$field}, $field);
        }

        $this->mock(AirBlueClient::class, function ($mock): void {
            $mock->shouldReceive('callOta')->once()->andReturn([
                'parsed' => [
                    'booking' => ['pnr' => 'ABPNR1', 'instance' => 'INST9'],
                    'pnr' => 'ABPNR1',
                    'instance' => 'INST9',
                    'tickets' => [],
                ],
                'errors' => [],
                'soap_fault' => null,
            ]);
        });

        $afterBook->forceFill([
            'meta' => array_merge(is_array($afterBook->meta) ? $afterBook->meta : [], [
                'airblue_context' => array_merge(
                    is_array($afterBook->meta['airblue_context'] ?? null) ? $afterBook->meta['airblue_context'] : [],
                    ['pnr' => 'ABPNR1', 'instance' => 'INST9'],
                ),
            ]),
        ])->save();

        app(AirBlueRetrieveService::class)->retrieveAndSync($afterBook->fresh(), $fixture['connection']);
        $afterRetrieve = $fixture['booking']->fresh();
        foreach ($before as $field => $value) {
            $this->assertSame($value, is_float($value) ? (float) $afterRetrieve->{$field} : (string) $afterRetrieve->{$field}, $field);
        }
        Http::assertNothingSent();
    }

    public function test_rejection_fails_without_false_confirmation_or_auto_ticket(): void
    {
        $fixture = $this->eligibleAirBlueBooking();
        $actor = $this->platformAdmin();

        $this->mock(BookingCommunicationService::class, function ($mock): void {
            $mock->shouldReceive('notifySupplierFailure')->once();
            $mock->shouldReceive('sendSupplierBookingCreated')->never();
            $mock->shouldReceive('notifyManualReviewRequired')->never();
            $mock->shouldReceive('logSystemEvent')->zeroOrMoreTimes();
        });
        $this->mock(AirBlueClient::class, function ($mock): void {
            $mock->shouldReceive('callOta')->once()->andThrow(
                new \App\Services\Suppliers\AirBlue\Exceptions\AirBlueProviderException(
                    'provider_error',
                    502,
                    'Provider rejected booking.',
                ),
            );
        });

        $result = app(BookingProviderRouter::class)->createSupplierBooking($fixture['booking']->fresh(), $actor);

        $this->assertFalse($result->success);
        $this->assertSame('failed', $result->status);
        $this->assertSame('provider_error', $result->error_code);
        $this->assertSame(1, SupplierBookingAttempt::query()->where('booking_id', $fixture['booking']->id)->count());
        $this->assertSame(0, SupplierBooking::query()->where('booking_id', $fixture['booking']->id)->count());
        $this->assertSame('failed', SupplierBookingAttempt::query()->where('booking_id', $fixture['booking']->id)->value('status'));
        $this->assertNull($fixture['booking']->fresh()->pnr);
        $this->assertNotSame('ticketed', $fixture['booking']->fresh()->supplier_booking_status);
        Http::assertNothingSent();
    }

    /**
     * @param  array<string, mixed>  $bookingOverrides
     * @return array{booking: Booking, connection: SupplierConnection}
     */
    private function eligibleAirBlueBooking(array $bookingOverrides = []): array
    {
        $agency = Agency::query()->firstOrFail();
        static $seq = 0;
        $seq++;
        $connection = SupplierConnection::query()->create([
            'agency_id' => $agency->id,
            'provider' => SupplierProvider::Airblue,
            'name' => 'AirBlue Pipeline Fixture '.$seq,
            'environment' => SupplierEnvironment::Sandbox,
            'status' => SupplierConnectionStatus::Active,
            'is_active' => true,
            'credentials' => [
                'api_channel' => 'zapways_ota',
                'protocol_version' => '2.0',
                'client_id' => 'fixture-client',
                'client_key' => 'fixture-key',
                'agent_type' => '5',
                'agent_id' => 'fixture-agent',
                'agent_password' => 'fixture-secret',
                'booking_enabled' => true,
                'ticketing_enabled' => false,
                'cancel_enabled' => false,
                'certification_status' => 'pending',
            ],
        ]);

        $booking = Booking::factory()->create(array_merge([
            'agency_id' => $agency->id,
            'status' => BookingStatus::Paid,
            'payment_status' => 'paid',
            'supplier' => SupplierProvider::Airblue->value,
            'pnr' => null,
            'supplier_reference' => null,
            'selected_fare_total' => 14500.00,
            'payable_after_promo' => 14500.00,
            'amount_paid' => 14500.00,
            'balance_due' => 0.00,
            'currency' => 'PKR',
            'meta' => [
                'supplier_provider' => SupplierProvider::Airblue->value,
                'supplier_connection_id' => $connection->id,
                'validated_offer_snapshot' => [
                    'offer_id' => 'airblue-offer-1',
                    'supplier_connection_id' => $connection->id,
                    'currency' => 'PKR',
                    'fare_total' => 14500,
                    'booking_class' => 'Y',
                    'protocol_version' => '2.0',
                    'segments' => [[
                        'origin' => 'KHI',
                        'destination' => 'ISB',
                        'departure_at' => '2026-11-01T08:00:00Z',
                        'arrival_at' => '2026-11-01T10:00:00Z',
                    ]],
                    'passengers' => ['adults' => 1, 'children' => 0, 'infants' => 0],
                ],
                'airblue_context' => [
                    'protocol_version' => '2.0',
                    'priced_itineraries' => [[
                        'segments' => [[
                            'origin' => 'KHI',
                            'destination' => 'ISB',
                            'departure_datetime' => '2026-11-01T08:00:00',
                            'arrival_datetime' => '2026-11-01T10:00:00',
                            'flight_number' => '401',
                            'marketing_airline' => 'PA',
                            'booking_class' => 'Y',
                        ]],
                        'total_fare' => ['total' => 14500, 'currency' => 'PKR'],
                        'fare_breakdowns' => [[
                            'base' => 12000.0,
                            'taxes' => 2500.0,
                            'total' => 14500.0,
                            'currency' => 'PKR',
                            'ptc' => 'ADT',
                        ]],
                    ]],
                ],
            ],
        ], $bookingOverrides));

        return ['booking' => $booking, 'connection' => $connection];
    }
}
