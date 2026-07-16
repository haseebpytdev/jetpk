<?php

namespace App\Http\Controllers\Agent;

use App\Http\Controllers\Controller;
use App\Models\AgentWalletTransaction;
use App\Services\Agents\AgentWalletService;
use App\Support\Ui\MobileViewPreference;
use Illuminate\Contracts\View\View;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;

/**
 * Read-only agent wallet ledger (append-only transaction history).
 */
class AgentLedgerController extends Controller
{
    public function __construct(
        protected AgentWalletService $walletService,
        protected MobileViewPreference $mobileViewPreference,
    ) {}

    public function index(Request $request): View
    {
        $user = $request->user();
        $agent = $user?->agent();
        abort_if($agent === null, 403);
        Gate::authorize('viewLedger', $agent);

        $agent->loadMissing('agency');
        $wallet = $this->walletService->walletFor($agent);
        $timezone = filled($agent->agency?->timezone)
            ? (string) $agent->agency->timezone
            : (string) config('app.timezone');

        $query = AgentWalletTransaction::query()
            ->where('agency_id', $agent->agency_id)
            ->with(['depositRequest', 'creator', 'approver', 'user'])
            ->latest('id');

        if ($request->filled('date_from')) {
            $query->whereDate('created_at', '>=', $request->string('date_from')->toString());
        }

        if ($request->filled('date_to')) {
            $query->whereDate('created_at', '<=', $request->string('date_to')->toString());
        }

        if ($request->filled('type')) {
            $query->where('type', $request->string('type')->toString());
        }

        if ($request->filled('status')) {
            $query->where('status', $request->string('status')->toString());
        }

        if ($request->filled('q')) {
            $term = '%'.$request->string('q')->trim()->toString().'%';
            $query->where(function ($q) use ($term): void {
                $q->where('reference', 'like', $term)
                    ->orWhere('description', 'like', $term);
            });
        }

        $transactions = $query->paginate(25)->withQueryString();

        $viewData = [
            'summary' => $this->walletService->summary($agent),
            'agencyBalance' => $this->walletService->agencyBalanceSummary($agent->agency_id),
            'transactions' => $transactions,
            'timezone' => $timezone,
            'filters' => [
                'date_from' => $request->string('date_from')->toString(),
                'date_to' => $request->string('date_to')->toString(),
                'type' => $request->string('type')->toString(),
                'status' => $request->string('status')->toString(),
                'q' => $request->string('q')->toString(),
            ],
        ];

        if ($this->mobileViewPreference->shouldUseMobileShell($request)) {
            return view('mobile.agent.ledger.index', $viewData);
        }

        return view(client_view('ledger.index', 'agent'), $viewData);
    }
}
