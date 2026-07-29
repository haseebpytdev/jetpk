<?php

namespace App\Http\Controllers\Agent;

use App\Http\Controllers\Concerns\RespondsWithAgentPortalJson;
use App\Http\Controllers\Controller;
use App\Support\AgentPortal\AgentPortalPaymentsPresenter;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;

class AgentPaymentController extends Controller
{
    use RespondsWithAgentPortalJson;

    public function __construct(
        protected AgentPortalPaymentsPresenter $paymentsPresenter,
    ) {}

    public function index(Request $request): JsonResponse
    {
        if (! $this->wantsAgentPortalJson($request)) {
            abort(404);
        }

        $agent = $request->user()->agent();
        abort_if($agent === null, 403);
        Gate::authorize('viewWallet', $agent);

        $filter = (string) $request->query('filter', 'all');
        $allowed = ['all', 'pending', 'paid', 'failed'];
        if (! in_array($filter, $allowed, true)) {
            $filter = 'all';
        }

        $page = max(1, (int) $request->query('page', 1));
        $perPage = min(50, max(5, (int) $request->query('per_page', 20)));

        $rows = $this->paymentsPresenter->collectPaymentRows($agent, $filter);

        return $this->agentPortalJson(
            $this->paymentsPresenter->presentIndex($agent, $rows, $filter, $page, $perPage),
        );
    }
}
