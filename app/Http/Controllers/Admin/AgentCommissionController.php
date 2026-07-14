<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Agent;
use App\Models\AgentCommissionEntry;
use App\Services\Agents\AgentCommissionService;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;

class AgentCommissionController extends Controller
{
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

    public function approve(Request $request, AgentCommissionEntry $entry): RedirectResponse
    {
        Gate::authorize('approve', $entry);
        $this->commissionService->approveEntry($entry, $request->user());
        $this->commissionService->writeAudit($entry->agent, $request->user(), 'agent.commission_entry_approved', ['entry_id' => $entry->id]);

        return back()->with('status', 'commission-entry-approved');
    }

    public function reject(Request $request, AgentCommissionEntry $entry): RedirectResponse
    {
        Gate::authorize('reject', $entry);
        $validated = $request->validate(['reason' => ['required', 'string', 'max:255']]);
        $this->commissionService->rejectEntry($entry, $request->user(), $validated['reason']);
        $this->commissionService->writeAudit($entry->agent, $request->user(), 'agent.commission_entry_rejected', ['entry_id' => $entry->id]);

        return back()->with('status', 'commission-entry-rejected');
    }

    public function adjustment(Request $request, Agent $agent): RedirectResponse
    {
        Gate::authorize('commission.adjust', $agent);
        $validated = $request->validate([
            'amount' => ['required', 'numeric'],
            'description' => ['nullable', 'string', 'max:255'],
        ]);
        $entry = $this->commissionService->recordAdjustment($agent, $request->user(), $validated);
        $this->commissionService->writeAudit($agent, $request->user(), 'agent.commission_adjustment_recorded', ['entry_id' => $entry->id]);

        return back()->with('status', 'commission-adjustment-recorded');
    }

    public function payout(Request $request, Agent $agent): RedirectResponse
    {
        Gate::authorize('commission.payout', $agent);
        $validated = $request->validate([
            'amount' => ['required', 'numeric', 'min:0.01'],
            'description' => ['nullable', 'string', 'max:255'],
        ]);
        $entry = $this->commissionService->recordPayout($agent, $request->user(), $validated);
        $this->commissionService->writeAudit($agent, $request->user(), 'agent.commission_payout_recorded', ['entry_id' => $entry->id]);

        return back()->with('status', 'commission-payout-recorded');
    }

    public function statement(Request $request, Agent $agent): RedirectResponse
    {
        Gate::authorize('commission.statement', $agent);
        $validated = $request->validate([
            'period_start' => ['nullable', 'date'],
            'period_end' => ['nullable', 'date'],
        ]);
        $statement = $this->commissionService->buildStatement($agent, $request->user(), $validated['period_start'] ?? null, $validated['period_end'] ?? null);
        $this->commissionService->writeAudit($agent, $request->user(), 'agent.commission_statement_generated', ['statement_id' => $statement->id]);

        return back()->with('status', 'commission-statement-generated');
    }
}
