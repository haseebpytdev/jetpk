<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Concerns\RespondsWithBackOfficeJson;
use App\Http\Controllers\Controller;
use App\Models\Agent;
use App\Models\AgentCommissionEntry;
use App\Services\Agents\AgentCommissionService;
use Illuminate\Contracts\View\View;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;

class AgentCommissionController extends Controller
{
    use RespondsWithBackOfficeJson;

    public function __construct(
        protected AgentCommissionService $commissionService,
    ) {}

    public function index(Request $request): View
    {
        Gate::authorize('viewAny', AgentCommissionEntry::class);

        $agencyId = $request->user()->current_agency_id;
        $agents = Agent::query()
            ->where('agency_id', $agencyId)
            ->with(['user'])
            ->get();

        $pending = AgentCommissionEntry::query()->where('agency_id', $agencyId)->where('status', 'pending')->sum('commission_amount');
        $approvedUnpaid = AgentCommissionEntry::query()->where('agency_id', $agencyId)->where('status', 'approved')->sum('commission_amount');
        $paidThisMonth = AgentCommissionEntry::query()->where('agency_id', $agencyId)->where('status', 'paid')->whereBetween('paid_at', [now()->startOfMonth(), now()->endOfMonth()])->sum('commission_amount');

        return view('dashboard.admin.commissions.index', [
            'agents' => $agents,
            'kpis' => [
                'pending' => (float) $pending,
                'approved_unpaid' => (float) $approvedUnpaid,
                'paid_this_month' => (float) $paidThisMonth,
                'active_agents' => $agents->count(),
            ],
            'balances' => $agents->mapWithKeys(fn (Agent $agent): array => [$agent->id => $this->commissionService->calculateBalance($agent)]),
        ]);
    }

    public function show(Agent $agent): View
    {
        Gate::authorize('view', $agent);

        $agent->load([
            'user',
            'commissionEntries.booking',
            'commissionEntries.bookingTicket',
            'commissionEntries.approvedBy',
            'commissionEntries.paidBy',
            'commissionStatements.entries',
        ]);

        return view('dashboard.admin.commissions.show', [
            'agent' => $agent,
            'balance' => $this->commissionService->calculateBalance($agent),
        ]);
    }

    public function approve(Request $request, AgentCommissionEntry $entry): RedirectResponse|JsonResponse
    {
        Gate::authorize('approve', $entry);
        $this->commissionService->approveEntry($entry, $request->user());
        $this->commissionService->writeAudit($entry->agent, $request->user(), 'agent.commission_entry_approved', ['entry_id' => $entry->id]);
        $entry->refresh();

        if ($this->wantsBackOfficeJson($request)) {
            return $this->backOfficeJson([
                'ok' => true,
                'entry' => $this->presentEntry($entry),
            ]);
        }

        return back()->with('status', 'commission-entry-approved');
    }

    public function reject(Request $request, AgentCommissionEntry $entry): RedirectResponse|JsonResponse
    {
        Gate::authorize('reject', $entry);
        $validated = $this->validateBackOffice($request, ['reason' => ['required', 'string', 'max:255']]);
        $this->commissionService->rejectEntry($entry, $request->user(), $validated['reason']);
        $this->commissionService->writeAudit($entry->agent, $request->user(), 'agent.commission_entry_rejected', ['entry_id' => $entry->id]);
        $entry->refresh();

        if ($this->wantsBackOfficeJson($request)) {
            return $this->backOfficeJson([
                'ok' => true,
                'entry' => $this->presentEntry($entry),
            ]);
        }

        return back()->with('status', 'commission-entry-rejected');
    }

    public function adjustment(Request $request, Agent $agent): RedirectResponse|JsonResponse
    {
        Gate::authorize('commission.adjust', $agent);
        $validated = $this->validateBackOffice($request, [
            'amount' => ['required', 'numeric'],
            'description' => ['nullable', 'string', 'max:255'],
        ]);
        $entry = $this->commissionService->recordAdjustment($agent, $request->user(), $validated);
        $this->commissionService->writeAudit($agent, $request->user(), 'agent.commission_adjustment_recorded', ['entry_id' => $entry->id]);
        $entry->refresh();

        if ($this->wantsBackOfficeJson($request)) {
            return $this->backOfficeJson([
                'ok' => true,
                'entry' => $this->presentEntry($entry),
            ]);
        }

        return back()->with('status', 'commission-adjustment-recorded');
    }

    public function payout(Request $request, Agent $agent): RedirectResponse|JsonResponse
    {
        Gate::authorize('commission.payout', $agent);
        $validated = $this->validateBackOffice($request, [
            'amount' => ['required', 'numeric', 'min:0.01'],
            'description' => ['nullable', 'string', 'max:255'],
        ]);
        $entry = $this->commissionService->recordPayout($agent, $request->user(), $validated);
        $this->commissionService->writeAudit($agent, $request->user(), 'agent.commission_payout_recorded', ['entry_id' => $entry->id]);
        $entry->refresh();

        if ($this->wantsBackOfficeJson($request)) {
            return $this->backOfficeJson([
                'ok' => true,
                'entry' => $this->presentEntry($entry),
            ]);
        }

        return back()->with('status', 'commission-payout-recorded');
    }

    public function statement(Request $request, Agent $agent): RedirectResponse|JsonResponse
    {
        Gate::authorize('commission.statement', $agent);
        $validated = $this->validateBackOffice($request, [
            'period_start' => ['nullable', 'date'],
            'period_end' => ['nullable', 'date'],
        ]);
        $statement = $this->commissionService->buildStatement($agent, $request->user(), $validated['period_start'] ?? null, $validated['period_end'] ?? null);
        $this->commissionService->writeAudit($agent, $request->user(), 'agent.commission_statement_generated', ['statement_id' => $statement->id]);

        if ($this->wantsBackOfficeJson($request)) {
            return $this->backOfficeJson([
                'ok' => true,
                'statement' => [
                    'id' => (string) $statement->id,
                    'agent_id' => (string) $agent->id,
                    'period_start' => $statement->period_start?->toDateString(),
                    'period_end' => $statement->period_end?->toDateString(),
                ],
            ]);
        }

        return back()->with('status', 'commission-statement-generated');
    }

    /**
     * @return array<string, mixed>
     */
    private function presentEntry(AgentCommissionEntry $entry): array
    {
        return [
            'id' => (string) $entry->id,
            'agent_id' => (string) $entry->agent_id,
            'status' => $entry->status->value,
            'commission_amount' => (float) $entry->commission_amount,
        ];
    }
}
