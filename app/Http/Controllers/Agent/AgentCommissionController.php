<?php

namespace App\Http\Controllers\Agent;

use App\Http\Controllers\Concerns\RespondsWithAgentPortalJson;
use App\Http\Controllers\Controller;
use App\Models\AgentCommissionStatement;
use App\Services\Agents\AgentCommissionService;
use App\Support\AgentPortal\AgentPortalCommissionPresenter;
use Illuminate\Contracts\View\View;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;

class AgentCommissionController extends Controller
{
    use RespondsWithAgentPortalJson;

    public function __construct(
        protected AgentCommissionService $commissionService,
        protected AgentPortalCommissionPresenter $commissionPresenter,
    ) {}

    public function index(Request $request): View|JsonResponse
    {
        $agent = auth()->user()->agent();
        abort_if($agent === null, 403);
        Gate::authorize('view', $agent);

        $agent->load(['commissionEntries.booking', 'commissionStatements']);
        $entries = $agent->commissionEntries;
        $statements = $agent->commissionStatements;

        if ($this->wantsAgentPortalJson($request)) {
            return $this->agentPortalJson(
                $this->commissionPresenter->presentIndex($agent, $entries, $statements),
            );
        }

        $viewData = [
            'agent' => $agent,
            'entries' => $entries->sortByDesc('created_at'),
            'statements' => $statements->sortByDesc('created_at'),
            'balance' => $this->commissionService->calculateBalance($agent),
            'pending' => (float) $entries->where('status', 'pending')->sum('commission_amount'),
            'approved' => (float) $entries->where('status', 'approved')->sum('commission_amount'),
            'paid' => (float) $entries->where('status', 'paid')->sum('commission_amount'),
        ];

        return view(client_view('commissions.index', 'agent'), $viewData);
    }

    public function showStatement(Request $request, AgentCommissionStatement $statement): View|JsonResponse
    {
        Gate::authorize('view', $statement);

        if ($this->wantsAgentPortalJson($request)) {
            return $this->agentPortalJson($this->commissionPresenter->presentStatement($statement));
        }

        $statement->load(['agent.user', 'entries.booking']);

        $viewData = [
            'statement' => $statement,
        ];

        return view(client_view('commissions.statement', 'agent'), $viewData);
    }
}
