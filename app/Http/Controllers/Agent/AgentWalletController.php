<?php

namespace App\Http\Controllers\Agent;

use App\Http\Controllers\Controller;
use App\Models\AgentDepositRequest;
use App\Models\AgentWalletTransaction;
use App\Services\Agents\AgentWalletService;
use App\Support\Agents\AgentPermission;
use App\Support\Ui\MobileViewPreference;
use Illuminate\Contracts\View\View;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;

class AgentWalletController extends Controller
{
    public function __construct(
        protected AgentWalletService $walletService,
        protected MobileViewPreference $mobileViewPreference,
    ) {}

    public function show(Request $request): View
    {
        $agent = auth()->user()->agent();
        abort_if($agent === null, 403);
        Gate::authorize('viewWallet', $agent);

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

        $viewData = [
            'summary' => $summary,
            'pendingDeposits' => $pendingDeposits,
            'recentTransactions' => $recentTransactions,
            'canViewLedger' => $user?->hasAgentPermission(AgentPermission::LedgerView) ?? false,
            'canUploadPayments' => $user?->hasAgentPermission(AgentPermission::PaymentsUpload) ?? false,
        ];

        if ($this->mobileViewPreference->shouldUseMobileShell($request)) {
            return view('mobile.agent.wallet.show', $viewData);
        }

        return view('dashboard.agent.wallet', $viewData);
    }
}
