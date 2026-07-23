<?php

namespace Tests\Feature\Suppliers;

use App\Contracts\Suppliers\OneApi\OneApiSoapTransportContract;
use App\Enums\BookingCommunicationEvent;
use App\Enums\SupplierProvider;
use App\Models\Agency;
use App\Models\Booking;
use App\Models\CommunicationLog;
use App\Models\SupplierConnection;
use App\Models\User;
use App\Services\Suppliers\OneApi\Booking\OneApiBookingService;
use App\Services\Suppliers\OneApi\Checkout\OneApiCheckoutSelectionValidator;
use App\Services\Suppliers\OneApi\Exceptions\OneApiValidationException;
use App\Services\Suppliers\OneApi\Workflow\OneApiWorkflowContext;
use App\Services\Suppliers\OneApi\Workflow\OneApiWorkflowContextStore;
use App\Support\OneApi\OneApiWorkflowContextGuard;
use App\Support\OneApi\OneApiWorkflowFingerprint;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Mockery;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\Support\OneApi\OneApiEnablesFixtureTransport;
use Tests\TestCase;

class OneApiWorkflowCorruptionMatrixTest extends TestCase
{
    use OneApiEnablesFixtureTransport;
    use RefreshDatabase;

    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }

    public static function corruptionCaseProvider(): array
    {
        $implemented = [
            'COR-003', 'COR-004', 'COR-005', 'COR-006', 'COR-007', 'COR-008', 'COR-009', 'COR-010', 'COR-011',
            'COR-012', 'COR-013', 'COR-014', 'COR-015', 'COR-016',
            'COR-017', 'COR-018', 'COR-019', 'COR-020', 'COR-021', 'COR-022', 'COR-023', 'COR-024',
            'COR-025', 'COR-026', 'COR-027',
        ];
        $cases = [];
        foreach ($implemented as $id) {
            $cases[$id] = [$id];
        }

        return $cases;
    }

    #[DataProvider('corruptionCaseProvider')]
    public function test_corruption_case_rejects_without_transport_or_communication(string $caseId): void
    {
        $transport = Mockery::mock(OneApiSoapTransportContract::class);
        $transport->shouldNotReceive('call');
        $this->instance(OneApiSoapTransportContract::class, $transport);

        $agency = Agency::factory()->create();
        $owner = User::factory()->create(['current_agency_id' => $agency->id]);
        $intruder = User::factory()->create(['current_agency_id' => $agency->id]);
        $connection = $this->connection($agency->id);
        $otherConnection = SupplierConnection::factory()->create([
            'agency_id' => $agency->id,
            'name' => 'One API Other '.uniqid('', true),
            'provider' => SupplierProvider::OneApi,
            'credentials' => $this->credentials(),
        ]);
        $store = app(OneApiWorkflowContextStore::class);
        $payload = ['segments' => [['origin' => 'SHJ', 'destination' => 'KHI']], 'trip_type' => 'one_way'];
        $context = $store->create($connection->id, 'corr-'.$caseId, $payload, $agency->id, $owner->id);
        $context->moneySnapshot = [
            'final_price_confirmed' => true,
            'price_confirmed' => true,
            'catalog_registry' => [
                'valid-bundle' => ['type' => 'bundle', 'id' => 'b1'],
            ],
        ];
        $context->signedOfferPayload = $payload;
        $context->signedOfferFingerprint = OneApiWorkflowFingerprint::signedOffer($payload);
        $context->terminalsBySegmentKey = ['SHJ-KHI' => 'SEARCH_TERMINAL_A'];
        $booking = Booking::factory()->create([
            'agency_id' => $agency->id,
            'supplier' => SupplierProvider::OneApi->value,
            'meta' => [
                'one_api_context' => ['workflow_context_id' => $context->contextId],
                'supplier_connection_id' => $connection->id,
            ],
        ]);
        $context->bookingId = (int) $booking->id;
        $store->put($context);
        $context = $store->get($context->contextId);
        $this->assertNotNull($context);

        $attemptsBefore = $booking->supplierBookingAttempts()->count();
        $commsBefore = CommunicationLog::query()->where('booking_id', $booking->id)->count();

        $denied = false;
        if ($caseId === 'COR-003') {
            $context->transactionIdentifier = 'TID_CONTEXT_CURRENT';
            $context->cookieJar = ['JSESSIONID=fixture'];
            app(OneApiWorkflowContextStore::class)->put($context);
            $result = app(OneApiBookingService::class)->createSupplierBooking($booking, $connection, $owner, [
                'transaction_identifier' => 'TID_STALE_SUBMITTED',
            ]);
            $denied = ! $result->success;
        } else {
            try {
                $this->runCase($caseId, $context, $connection, $otherConnection, $booking, $owner, $intruder, $agency);
            } catch (OneApiValidationException) {
                $denied = true;
            }
        }

        if ($caseId === 'COR-027') {
            $context->moneySnapshot['final_price_confirmed'] = false;
            app(OneApiWorkflowContextStore::class)->put($context);
            $result = app(OneApiBookingService::class)->createSupplierBooking($booking, $connection, $owner, []);
            $denied = ! $result->success;
        }

        $this->assertTrue($denied, 'Expected rejection for '.$caseId);

        $this->assertSame($attemptsBefore, $booking->fresh()->supplierBookingAttempts()->count());
        $this->assertSame($commsBefore, CommunicationLog::query()->where('booking_id', $booking->id)->count());
        $this->assertSame(
            0,
            CommunicationLog::query()
                ->where('booking_id', $booking->id)
                ->whereIn('event', [
                    BookingCommunicationEvent::SupplierBookingCreated->value,
                    BookingCommunicationEvent::TicketIssued->value,
                ])
                ->count()
        );
    }

    private function runCase(
        string $caseId,
        OneApiWorkflowContext $context,
        SupplierConnection $connection,
        SupplierConnection $otherConnection,
        Booking $booking,
        User $owner,
        User $intruder,
        Agency $agency,
    ): void {
        $guard = app(OneApiWorkflowContextGuard::class);
        $validator = app(OneApiCheckoutSelectionValidator::class);

        match ($caseId) {
            'COR-003' => null,
            'COR-004' => $guard->authorizeBookingMutation($booking, $owner, $connection, tap($context, function ($c): void {
                $c->transactionIdentifier = 'TID_WITHOUT_COOKIE';
                $c->cookieJar = [];
            })),
            'COR-005' => $guard->authorizeHttp($owner, $connection, tap($context, function ($c): void {
                $c->sessionFingerprint = OneApiWorkflowFingerprint::session('bound');
            }), 'other-session'),
            'COR-006', 'COR-007' => $validator->validate($context, ['bundle_selection_ids' => ['missing']], false),
            'COR-008' => $validator->validate($context, [
                'terminals_by_segment_key' => ['SHJ-KHI' => 'SEARCH_TERMINAL_B'],
            ], false),
            'COR-009' => $guard->authorizeHttp($owner, $connection, tap($context, function ($c): void {
                $c->signedOfferPayload = ['segments' => [['origin' => 'DXB', 'destination' => 'KHI']]];
            }), 's1'),
            'COR-010' => $guard->authorizeHttp($owner, $connection, tap($context, function ($c): void {
                $c->signedOfferPayload = ['segments' => [['origin' => 'KHI', 'destination' => 'SHJ']]];
            }), 's1'),
            'COR-011' => $guard->authorizeHttp($owner, $connection, tap($context, function ($c): void {
                $c->signedOfferPayload = ['segments' => [['origin' => 'SHJ', 'destination' => 'KHI']], 'adt' => 2];
            }), 's1'),
            'COR-012' => $guard->authorizeHttp($intruder, $connection, $context, 's1'),
            'COR-013' => $guard->authorizeHttp(tap($owner, function ($u) use ($agency): void {
                $u->forceFill(['current_agency_id' => $agency->id + 999]);
            }), $connection, $context, 's1'),
            'COR-014' => $guard->authorizeHttp($owner, $connection, tap($context, function ($c) use ($booking): void {
                $c->bookingId = $booking->id + 50;
            }), 's1', $booking->id),
            'COR-015' => $guard->authorizeHttp($owner, $connection, tap($context, function ($c): void {
                $c->sessionFingerprint = OneApiWorkflowFingerprint::session('bound');
            }), 'wrong'),
            'COR-016' => $guard->authorizeHttp($owner, $otherConnection, $context, 's1'),
            'COR-017' => $guard->authorizeHttp($owner, $connection, tap($context, function ($c): void {
                $c->expiresAtIso = now()->subMinute()->toIso8601String();
            }), 's1'),
            'COR-018' => $guard->authorizeHttp($owner, $connection, tap($context, function ($c): void {
                $c->lifecycleStatus = 'completed';
            }), 's1'),
            'COR-019', 'COR-020', 'COR-021', 'COR-022' => $validator->validate($context, ['bundle_selection_ids' => ['missing']], false),
            'COR-023', 'COR-024' => $validator->validate($context, ['seat_selection_ids' => ['missing']], false),
            'COR-025' => $validator->validate($context, ['client_total' => 99], false),
            'COR-026' => $validator->validate($context, ['posted_supplier_amount' => 1], false),
            'COR-027' => null,
            default => throw new OneApiValidationException('test_gap', 422, 'Unhandled case '.$caseId),
        };
    }

    /**
     * @return array<string, string>
     */
    private function credentials(): array
    {
        return [
            'username' => 'ONE_API_TEST_USERNAME',
            'password' => 'ONE_API_TEST_PASSWORD',
            'agent_code' => 'AGENT',
            'rest_auth_url' => 'https://example.test/auth',
            'rest_search_url' => 'https://example.test/search',
            'soap_url' => 'https://example.test/soap',
        ];
    }

    private function connection(int $agencyId): SupplierConnection
    {
        return SupplierConnection::factory()->create([
            'agency_id' => $agencyId,
            'name' => 'One API '.uniqid('', true),
            'provider' => SupplierProvider::OneApi,
            'credentials' => $this->credentials(),
        ]);
    }
}
