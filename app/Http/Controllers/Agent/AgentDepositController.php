<?php

namespace App\Http\Controllers\Agent;

use App\Http\Controllers\Controller;
use App\Http\Requests\Agent\StoreAgentDepositRequest;
use App\Models\AgentDepositRequest;
use App\Services\Agents\AgentWalletService;
use App\Support\Platform\PlatformModuleEnforcer;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\View\View;

class AgentDepositController extends Controller
{
    public function __construct(
        protected AgentWalletService $walletService,
        protected PlatformModuleEnforcer $platformModuleEnforcer,
    ) {}

    public function index(Request $request): View
    {
        $agent = auth()->user()->agent();
        abort_if($agent === null, 403);
        Gate::authorize('viewWallet', $agent);

        $deposits = AgentDepositRequest::query()
            ->where('agent_id', $agent->id)
            ->latest('id')
            ->paginate(20);

        $summary = $this->walletService->summary($agent);

        $viewData = [
            'deposits' => $deposits,
            'summary' => $summary,
        ];

        return view(client_view('deposits.index', 'agent'), $viewData);
    }

    public function create(Request $request): View
    {
        $agent = auth()->user()->agent();
        abort_if($agent === null, 403);
        Gate::authorize('create', AgentDepositRequest::class);

        $viewData = [
            'summary' => $this->walletService->summary($agent),
        ];

        return view(client_view('deposits.create', 'agent'), $viewData);
    }

    public function store(StoreAgentDepositRequest $request): RedirectResponse
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

        $this->walletService->submitDepositRequest($agent, $request->user(), [
            'amount' => $validated['amount'],
            'payment_method' => $validated['payment_method'] ?? null,
            'reference' => $validated['reference'] ?? null,
            'agent_note' => $validated['agent_note'] ?? null,
            'proof_path' => $proofPath,
        ]);

        return redirect()
            ->route('agent.deposits.index')
            ->with('status', 'deposit-submitted');
    }
}
