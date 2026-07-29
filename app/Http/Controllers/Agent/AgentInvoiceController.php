<?php

namespace App\Http\Controllers\Agent;

use App\Http\Controllers\Concerns\RespondsWithAgentPortalJson;
use App\Http\Controllers\Controller;
use App\Support\AgentPortal\AgentPortalInvoicesPresenter;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;

class AgentInvoiceController extends Controller
{
    use RespondsWithAgentPortalJson;

    public function __construct(
        protected AgentPortalInvoicesPresenter $invoicesPresenter,
    ) {}

    public function index(Request $request): JsonResponse
    {
        if (! $this->wantsAgentPortalJson($request)) {
            abort(404);
        }

        $agent = $request->user()->agent();
        abort_if($agent === null, 403);
        Gate::authorize('viewWallet', $agent);

        $documents = $this->invoicesPresenter->invoiceQuery($agent)->paginate(20);

        return $this->agentPortalJson($this->invoicesPresenter->presentIndex($documents));
    }
}
