<?php

namespace App\Http\Controllers\Admin;

use App\Enums\AgentDepositRequestStatus;
use App\Http\Controllers\Controller;
use App\Models\AgentDepositRequest;
use App\Models\User;
use App\Services\Agents\AgentWalletService;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Storage;
use Illuminate\View\View;
use InvalidArgumentException;
use Symfony\Component\HttpFoundation\StreamedResponse;

class AgentDepositController extends Controller
{
    public function __construct(
        protected AgentWalletService $walletService,
    ) {}

    public function index(Request $request): View
    {
        Gate::authorize('viewAny', AgentDepositRequest::class);

        $status = $request->string('status')->toString();

        $query = $this->scopedDepositQuery($request->user())
            ->latest('id');

        if ($status !== '') {
            $query->where('status', $status);
        }

        $deposits = $query->paginate(25)->withQueryString();

        $pendingCount = $this->scopedDepositQuery($request->user())
            ->where('status', AgentDepositRequestStatus::Submitted)
            ->count();

        return view('dashboard.admin.agent-deposits.index', [
            'deposits' => $deposits,
            'filters' => ['status' => $status],
            'pendingCount' => $pendingCount,
        ]);
    }

    public function show(AgentDepositRequest $deposit): View
    {
        Gate::authorize('view', $deposit);
        $deposit->load(['agency', 'user', 'agent.user', 'wallet', 'reviewer', 'transactions']);

        return view('dashboard.admin.agent-deposits.show', [
            'deposit' => $deposit,
        ]);
    }

    public function approve(Request $request, AgentDepositRequest $deposit): RedirectResponse
    {
        Gate::authorize('approve', $deposit);

        try {
            $this->walletService->approveDeposit($deposit, $request->user());
        } catch (InvalidArgumentException $exception) {
            return back()->withErrors(['deposit' => $exception->getMessage()]);
        }

        return redirect()
            ->route('admin.agent-deposits.show', $deposit)
            ->with('status', 'deposit-approved');
    }

    public function reject(Request $request, AgentDepositRequest $deposit): RedirectResponse
    {
        Gate::authorize('reject', $deposit);
        $validated = $request->validate([
            'admin_note' => ['required', 'string', 'max:2000'],
        ]);

        try {
            $this->walletService->rejectDeposit($deposit, $request->user(), $validated['admin_note']);
        } catch (InvalidArgumentException $exception) {
            return back()->withErrors(['deposit' => $exception->getMessage()]);
        }

        return redirect()
            ->route('admin.agent-deposits.show', $deposit)
            ->with('status', 'deposit-rejected');
    }

    public function proof(AgentDepositRequest $deposit): StreamedResponse
    {
        Gate::authorize('downloadProof', $deposit);

        if (! filled($deposit->proof_path) || ! Storage::disk('local')->exists($deposit->proof_path)) {
            abort(404);
        }

        return Storage::disk('local')->download($deposit->proof_path, 'deposit-proof-'.$deposit->id);
    }

    /** @return Builder<AgentDepositRequest> */
    protected function scopedDepositQuery(User $user): Builder
    {
        $query = AgentDepositRequest::query()
            ->with(['agency', 'user', 'agent.user']);

        if (! $user->isPlatformAdmin()) {
            $query->where('agency_id', $user->current_agency_id);
        }

        return $query;
    }
}
