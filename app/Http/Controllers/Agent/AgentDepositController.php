<?php

namespace App\Http\Controllers\Agent;

use App\Http\Controllers\Concerns\RespondsWithAgentPortalJson;
use App\Http\Controllers\Controller;
use App\Http\Requests\Agent\StoreAgentDepositRequest;
use App\Models\AgentDepositRequest;
use App\Services\Agents\AgentWalletService;
use App\Support\AgentPortal\AgentPortalDepositsPresenter;
use App\Support\Platform\PlatformModuleEnforcer;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\View\View;

class AgentDepositController extends Controller
{
    use RespondsWithAgentPortalJson;

    public function __construct(
        protected AgentWalletService $walletService,
        protected PlatformModuleEnforcer $platformModuleEnforcer,
        protected AgentPortalDepositsPresenter $depositsPresenter,
    ) {}

    public function index(Request $request): View|JsonResponse
    {
        $agent = auth()->user()->agent();
        abort_if($agent === null, 403);
        Gate::authorize('viewWallet', $agent);

        $deposits = AgentDepositRequest::query()
            ->where('agent_id', $agent->id)
            ->latest('id')
            ->paginate(20);

        if ($this->wantsAgentPortalJson($request)) {
            return $this->agentPortalJson($this->depositsPresenter->presentIndex($agent, $deposits));
        }

        return view(client_view('deposits.index', 'agent'), [
            'deposits' => $deposits,
            'summary' => $this->walletService->summary($agent),
        ]);
    }

    public function create(Request $request): View|JsonResponse
    {
        $agent = auth()->user()->agent();
        abort_if($agent === null, 403);
        Gate::authorize('create', AgentDepositRequest::class);

        if ($this->wantsAgentPortalJson($request)) {
            return $this->agentPortalJson($this->depositsPresenter->presentCreateForm($agent));
        }

        return view(client_view('deposits.create', 'agent'), [
            'summary' => $this->walletService->summary($agent),
        ]);
    }

    public function store(StoreAgentDepositRequest $request): RedirectResponse|JsonResponse
    {
        $agent = $request->user()->agent();
        abort_if($agent === null, 403);
        Gate::authorize('create', AgentDepositRequest::class);

        $this->platformModuleEnforcer->ensureAgentDepositsEnabled();

        $validated = $request->validated();
        $proofPath = null;
        if ($request->hasFile('proof')) {
            $proofPath = $request->file('proof')->store('agent-deposits/proofs', 'local');
        }

        $deposit = $this->walletService->submitDepositRequest($agent, $request->user(), [
            'amount' => $validated['amount'],
            'payment_method' => $validated['payment_method'] ?? null,
            'reference' => $validated['reference'] ?? null,
            'agent_note' => $validated['agent_note'] ?? null,
            'proof_path' => $proofPath,
        ]);

        if ($this->wantsAgentPortalJson($request)) {
            return $this->agentPortalJson([
                'ok' => true,
                'deposit' => $this->depositsPresenter->presentListItem($deposit->fresh()),
                'redirect_url' => '/agent/deposits',
            ], 201);
        }

        return redirect()
            ->route('agent.deposits.index')
            ->with('status', 'deposit-submitted');
    }
}
