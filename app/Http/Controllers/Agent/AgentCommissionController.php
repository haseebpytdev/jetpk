<?php

namespace App\Http\Controllers\Agent;

use App\Http\Controllers\Controller;
use App\Models\AgentCommissionStatement;
use App\Services\Agents\AgentCommissionService;
use App\Support\Ui\MobileViewPreference;
use Illuminate\Contracts\View\View;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;

class AgentCommissionController extends Controller
{
    public function __construct(
        protected AgentCommissionService $commissionService,
        protected MobileViewPreference $mobileViewPreference,
    ) {}

    public function index(Request $request): View
    {
        $agent = auth()->user()->agent();
        abort_if($agent === null, 403);
        Gate::authorize('view', $agent);

        $agent->load(['commissionEntries.booking', 'commissionStatements']);
        $entries = $agent->commissionEntries->sortByDesc('created_at');

        $viewData = [
            'agent' => $agent,
            'entries' => $entries,
            'statements' => $agent->commissionStatements->sortByDesc('created_at'),
            'balance' => $this->commissionService->calculateBalance($agent),
            'pending' => (float) $entries->where('status', 'pending')->sum('commission_amount'),
            'approved' => (float) $entries->where('status', 'approved')->sum('commission_amount'),
            'paid' => (float) $entries->where('status', 'paid')->sum('commission_amount'),
        ];

        if ($this->mobileViewPreference->shouldUseMobileShell($request)) {
            return view('mobile.agent.commissions.index', $viewData);
        }

        return view(client_view('commissions.index', 'agent'), $viewData);
    }

    public function showStatement(Request $request, AgentCommissionStatement $statement): View
    {
        Gate::authorize('view', $statement);
        $statement->load(['agent.user', 'entries.booking']);

        $viewData = [
            'statement' => $statement,
        ];

        if ($this->mobileViewPreference->shouldUseMobileShell($request)) {
            return view('mobile.agent.commissions.statement', $viewData);
        }

        return view(client_view('commissions.statement', 'agent'), $viewData);
    }
}
