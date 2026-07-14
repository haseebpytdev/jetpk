<?php

namespace Tests\Feature\Finance;

use App\Models\AgentWalletTransaction;
use App\Models\Booking;
use App\Services\Finance\MasterLedgerService;
use App\Services\Reports\BookingReportService;
use App\Support\Finance\OtaFinanceDemoScenario;
use App\Support\Identity\ActorIdentifier;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Request;
use Tests\Feature\Finance\Concerns\BuildsOtaFinanceScenario;
use Tests\TestCase;

/**
 * Realistic OTA finance scenario: 3 agencies, platform staff, wallet ledger totals,
 * reports scoping, actor codes, and RBAC.
 */
class OtaFinanceScenarioTest extends TestCase
{
    use BuildsOtaFinanceScenario;
    use RefreshDatabase;

    /** @var array<string, mixed> */
    protected array $scenario;

    protected function setUp(): void
    {
        parent::setUp();
        $this->scenario = $this->buildOtaFinanceScenario();
    }

    public function test_platform_master_ledger_summary_matches_expected_totals(): void
    {
        $admin = $this->scenario['platform']['admin'];
        $service = app(MasterLedgerService::class);
        $payload = $service->buildIndex($admin, Request::create('/admin/ledger', 'GET'));
        $summary = $payload['summary'];
        $expected = OtaFinanceDemoScenario::PLATFORM_LEDGER;

        $this->assertSame($expected['total_credits'], (float) $summary['total_credits']);
        $this->assertSame($expected['total_debits'], (float) $summary['total_debits']);
        $this->assertSame($expected['net_balance'], (float) $summary['net_balance']);
        $this->assertSame($expected['pending_deposits'], (float) $summary['pending_deposits']);
        $this->assertSame($expected['approved_deposits'], (float) $summary['approved_deposits']);
        $this->assertSame($expected['refund_liabilities'], (float) $summary['refund_liabilities']);
        $this->assertSame($expected['agency_wallet_exposure'], (float) $summary['agency_wallet_exposure']);
    }

    public function test_platform_master_ledger_agency_filter_isolates_easy_ticket(): void
    {
        $admin = $this->scenario['platform']['admin'];
        $et = $this->scenario['agencies']['et'];
        $jp = $this->scenario['agencies']['jp'];

        $service = app(MasterLedgerService::class);
        $payload = $service->buildIndex($admin, Request::create('/admin/ledger', 'GET', [
            'agency_id' => (string) $et['agency']->id,
        ]));

        $expected = OtaFinanceDemoScenario::AGENCY_ET_LEDGER;
        $summary = $payload['summary'];

        $this->assertSame($expected['total_credits'], (float) $summary['total_credits']);
        $this->assertSame($expected['total_debits'], (float) $summary['total_debits']);
        $this->assertSame($expected['net_balance'], (float) $summary['net_balance']);
        $this->assertSame($expected['pending_deposits'], (float) $summary['pending_deposits']);
        $this->assertSame($expected['wallet_balance'], (float) $summary['agency_wallet_exposure']);

        $ids = $payload['transactions']->getCollection()->pluck('agency_id')->unique()->values()->all();
        $this->assertSame([(int) $et['agency']->id], $ids);

        $jpTxId = $jp['ledger']['transactions']['deposit1']->id;
        $this->actingAs($admin)
            ->get(route('admin.ledger.index', ['agency_id' => $et['agency']->id]))
            ->assertOk()
            ->assertDontSee('data-testid="ledger-row-'.$jpTxId.'"', false);
    }

    public function test_master_ledger_filters_by_status_type_direction_and_booking_ref(): void
    {
        $admin = $this->scenario['platform']['admin'];
        $et = $this->scenario['agencies']['et'];
        $holdTx = $et['ledger']['transactions']['bookingHold'];

        $service = app(MasterLedgerService::class);

        $byStatus = $service->buildIndex($admin, Request::create('/', 'GET', ['status' => 'pending']));
        $this->assertTrue(
            $byStatus['transactions']->getCollection()->every(
                fn (AgentWalletTransaction $tx): bool => $tx->status->value === 'pending',
            ),
        );

        $byType = $service->buildIndex($admin, Request::create('/', 'GET', [
            'type' => 'booking_hold',
            'agency_id' => (string) $et['agency']->id,
        ]));
        $this->assertCount(1, $byType['transactions']->getCollection());
        $this->assertSame($holdTx->id, $byType['transactions']->first()->id);

        $byDebit = $service->buildIndex($admin, Request::create('/', 'GET', [
            'direction' => 'debit',
            'agency_id' => (string) $et['agency']->id,
        ]));
        $debitTotal = $byDebit['transactions']->getCollection()
            ->filter(fn (AgentWalletTransaction $tx): bool => (float) $tx->balance_after < (float) $tx->balance_before)
            ->sum('amount');
        $this->assertSame(OtaFinanceDemoScenario::AGENCY_ET_LEDGER['total_debits'], (float) $debitTotal);

        $byBookingRef = $service->buildIndex($admin, Request::create('/', 'GET', [
            'booking_ref' => 'ET-BKG-WALLET-001',
        ]));
        $this->assertTrue($byBookingRef['transactions']->getCollection()->contains('id', $holdTx->id));
    }

    public function test_agency_ledger_balance_matches_wallet_and_blocks_cross_agency_rows(): void
    {
        $et = $this->scenario['agencies']['et'];
        $jp = $this->scenario['agencies']['jp'];
        $owner = $et['owner'];
        $jpTxId = $jp['ledger']['transactions']['deposit1']->id;

        $response = $this->actingAs($owner)->get(route('agent.ledger.index'));
        $response->assertOk()
            ->assertSee('Rs '.number_format(OtaFinanceDemoScenario::AGENCY_ET_LEDGER['wallet_balance'], 2), false)
            ->assertSee('Rs '.number_format(OtaFinanceDemoScenario::AGENCY_ET_LEDGER['pending_deposits'], 2), false)
            ->assertDontSee('data-testid="ledger-row-'.$jpTxId.'"', false)
            ->assertDontSee('JP-DEP-1', false);

        $this->assertSame(
            OtaFinanceDemoScenario::AGENCY_ET_LEDGER['wallet_balance'],
            (float) $et['wallet']->fresh()->balance,
        );
    }

    public function test_agency_staff_ledger_permission_matrix(): void
    {
        $et = $this->scenario['agencies']['et'];

        $this->actingAs($et['staffFinance'])->get(route('agent.ledger.index'))->assertOk();
        $this->actingAs($et['staffOps'])->get(route('agent.ledger.index'))->assertForbidden();
    }

    public function test_platform_reports_include_all_agencies_agency_reports_are_scoped(): void
    {
        $admin = $this->scenario['platform']['admin'];
        $etOwner = $this->scenario['agencies']['et']['owner'];
        $reportService = app(BookingReportService::class);

        $platform = $reportService->build($admin, Request::create('/admin/reports', 'GET'));
        $platformGross = (float) $platform['summary']['gross_sales'];
        $this->assertGreaterThan(OtaFinanceDemoScenario::AGENCY_ET_REPORTS['gross_sales'], $platformGross);

        $agency = $reportService->build($etOwner, Request::create('/agent/reports', 'GET'));
        $this->assertSame(
            OtaFinanceDemoScenario::AGENCY_ET_REPORTS['gross_sales'],
            (float) $agency['summary']['gross_sales'],
        );
        $this->assertSame(
            OtaFinanceDemoScenario::AGENCY_ET_REPORTS['markup_revenue'],
            (float) $agency['summary']['markup_revenue'],
        );
        $this->assertSame(
            OtaFinanceDemoScenario::AGENCY_ET_REPORTS['agent_sales'],
            (float) $agency['summary']['agent_sales'],
        );
        $this->assertSame(
            OtaFinanceDemoScenario::AGENCY_ET_REPORTS['direct_customer_sales'],
            (float) $agency['summary']['direct_customer_sales'],
        );

        $jpOwner = $this->scenario['agencies']['jp']['owner'];
        $jpReport = $reportService->build($jpOwner, Request::create('/agent/reports', 'GET'));
        $this->assertLessThan(
            (float) $agency['summary']['gross_sales'],
            (float) $jpReport['summary']['gross_sales'],
        );
    }

    public function test_reports_invalid_status_filter_does_not_crash(): void
    {
        $admin = $this->scenario['platform']['admin'];

        $this->actingAs($admin)
            ->get(route('admin.reports', ['status' => 'not-a-real-status']))
            ->assertOk();

        $this->actingAs($admin)
            ->get(route('admin.ledger.index', ['status' => 'invalid-status']))
            ->assertOk();
    }

    public function test_actor_identifiers_match_required_formats(): void
    {
        $platformAdmin = $this->scenario['platform']['admin'];
        $staffFinance = $this->scenario['platform']['staffFinance'];
        $et = $this->scenario['agencies']['et'];

        $this->assertSame(
            'ADM-'.$platformAdmin->id.'-Platform',
            ActorIdentifier::forUser($platformAdmin),
        );
        $this->assertSame(
            'STF-'.$staffFinance->id.'-Finance',
            ActorIdentifier::forUser($staffFinance),
        );
        $this->assertSame(
            'ET-AGM-'.$et['owner']->id.'-Tariq',
            ActorIdentifier::forUser($et['owner']),
        );
        $this->assertSame(
            'ET-AGST-'.$et['staffFinance']->id.'-Ayesha',
            ActorIdentifier::forUser($et['staffFinance']),
        );
        $this->assertSame(
            'CU-'.$this->scenario['customers']['et']->id.'-Customer',
            ActorIdentifier::forUser($this->scenario['customers']['et']),
        );
        $this->assertSame(
            'GU-9001-Guest',
            ActorIdentifier::forGuest(['guest_id' => 9001, 'first_name' => 'Guest']),
        );

        $holdTx = $et['ledger']['transactions']['bookingHold'];
        $this->actingAs($platformAdmin)
            ->get(route('admin.ledger.index', ['agency_id' => $et['agency']->id]))
            ->assertOk()
            ->assertSee('ET-AGM-'.$et['owner']->id.'-Tariq', false)
            ->assertSee('data-testid="ledger-actor-'.$holdTx->id.'"', false);
    }

    public function test_finance_rbac_matrix(): void
    {
        $platform = $this->scenario['platform'];
        $et = $this->scenario['agencies']['et'];
        $customer = $this->scenario['customers']['et'];

        $this->actingAs($platform['admin'])->get(route('admin.ledger.index'))->assertOk();
        $this->actingAs($platform['admin'])->get(route('admin.reports'))->assertOk();

        $this->actingAs($platform['staffFinance'])->get(route('staff.ledger.index'))->assertOk();
        $this->actingAs($platform['staffFinance'])->get(route('staff.reports.index'))->assertOk();

        $this->actingAs($platform['staffOps'])->get(route('staff.ledger.index'))->assertForbidden();
        $this->actingAs($platform['staffOps'])->get(route('staff.reports.index'))->assertForbidden();

        $this->actingAs($et['owner'])->get(route('agent.ledger.index'))->assertOk();
        $this->actingAs($et['owner'])->get(route('agent.reports.index'))->assertOk();
        $this->actingAs($et['owner'])->get(route('admin.ledger.index'))->assertForbidden();

        $this->actingAs($et['staffFinance'])->get(route('agent.reports.index'))->assertOk();
        $this->actingAs($et['staffOps'])->get(route('agent.reports.index'))->assertForbidden();

        $this->actingAs($customer)->get(route('admin.ledger.index'))->assertForbidden();
        $this->actingAs($customer)->get(route('agent.ledger.index'))->assertForbidden();
        $this->actingAs($customer)->get(route('staff.ledger.index'))->assertForbidden();
    }

    public function test_scenario_includes_required_finance_record_types(): void
    {
        $et = $this->scenario['agencies']['et'];

        $this->assertInstanceOf(AgentWalletTransaction::class, $et['ledger']['transactions']['depositRequestPending']);
        $this->assertInstanceOf(AgentWalletTransaction::class, $et['ledger']['transactions']['depositApproved1']);
        $this->assertInstanceOf(AgentWalletTransaction::class, $et['ledger']['transactions']['depositRejected']);
        $this->assertInstanceOf(AgentWalletTransaction::class, $et['ledger']['transactions']['adminCredit']);
        $this->assertInstanceOf(AgentWalletTransaction::class, $et['ledger']['transactions']['bookingHold']);
        $this->assertInstanceOf(AgentWalletTransaction::class, $et['ledger']['transactions']['bookingRelease']);

        $this->assertDatabaseHas('booking_payments', ['status' => 'verified']);
        $this->assertDatabaseHas('booking_refunds', ['status' => 'paid', 'reference' => 'ET-REF-PAID-001']);
        $this->assertDatabaseHas('agent_commission_entries', ['booking_id' => Booking::query()->where('booking_reference', 'ET-BKG-WALLET-001')->value('id')]);
        $this->assertDatabaseHas('bookings', ['booking_reference' => 'ET-BKG-GUEST-001', 'source_channel' => 'public_guest']);
        $this->assertDatabaseHas('bookings', ['booking_reference' => 'ET-BKG-CUST-001', 'source_channel' => 'public_web']);
    }
}
