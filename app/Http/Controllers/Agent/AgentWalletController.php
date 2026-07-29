<?php

namespace App\Http\Controllers\Agent;

use App\Http\Controllers\Concerns\RespondsWithAgentPortalJson;
use App\Http\Controllers\Controller;
use App\Models\AgentDepositRequest;
use App\Models\AgentWalletTransaction;
use App\Services\Agents\AgentWalletService;
use App\Support\AgentPortal\AgentPortalWalletPresenter;
use App\Support\Agents\AgentPermission;
use Illuminate\Contracts\View\View;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;

class AgentWalletController extends Controller
{
    use RespondsWithAgentPortalJson;

    public function __construct(
        protected AgentWalletService $walletService,
        protected AgentPortalWalletPresenter $walletPresenter,
    ) {}

    public function show(Request $request): View|JsonResponse
    {
        $agent = auth()->user()->agent();
        abort_if($agent === null, 403);
        Gate::authorize('viewWallet', $agent);

        if ($this->wantsAgentPortalJson($request)) {
            return $this->agentPortalJson($this->walletPresenter->present($request->user(), $agent));
        }

        $summary = $this->walletService->summary($agent);
        $wallet = $summary['wallet'];

        $pendingDeposits = AgentDepositRequest::query()
            ->where('agent_id', $agent->id)
            ->where('status', 'submitted')
            ->latest('id')
            ->limit(10)
            ->get();

        $recentTransactions = AgentWalletTransaction::query()
            ->where('agent_wallet_id', $wallet->id)
            ->latest('id')
            ->limit(20)
            ->get();

        $user = auth()->user();

        return view(client_view('wallet', 'agent'), [
            'summary' => $summary,
            'pendingDeposits' => $pendingDeposits,
            'recentTransactions' => $recentTransactions,
            'canViewLedger' => $user?->hasAgentPermission(AgentPermission::LedgerView) ?? false,
            'canUploadPayments' => $user?->hasAgentPermission(AgentPermission::PaymentsUpload) ?? false,
        ]);
    }
}
