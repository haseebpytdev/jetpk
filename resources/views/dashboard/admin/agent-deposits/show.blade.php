@extends(client_layout('dashboard', 'admin'))

@section('title', 'Review deposit')

@section('page-header')
    <x-dashboard.section-header title="Deposit request #{{ $deposit->id }}" subtitle="Review proof and approve or reject.">
        <x-slot name="actions">
            <a href="{{ route('admin.agent-deposits.index') }}" class="jp-btn jp-btn--ghost btn-sm">Back to list</a>
        </x-slot>
    </x-dashboard.section-header>
@endsection

@section('content')
    @php
        use App\Support\Identity\IdentityDisplay;

        $agency = $deposit->agency;
        $requester = $deposit->user ?? $deposit->agent?->user;
        $agencyCode = IdentityDisplay::agencyCodeDisplay($agency);
    @endphp

    @if (session('status') === 'deposit-approved')
        <div class="jp-alert jp-alert--success" data-testid="admin-deposit-approved-flash">Deposit approved and wallet balance updated.</div>
    @elseif (session('status') === 'deposit-rejected')
        <div class="jp-alert jp-alert--warn" data-testid="admin-deposit-rejected-flash">Deposit rejected. Wallet balance unchanged.</div>
    @endif

    @if ($errors->has('deposit'))
        <div class="jp-alert jp-alert--danger">{{ $errors->first('deposit') }}</div>
    @endif

    <div class="row g-3 mb-4" data-testid="admin-agent-deposit-detail">
        <div class="col-md-6">
            <div class="card border-0 shadow-sm h-100">
                <div class="card-header border-0"><h3 class="jp-card__title mb-0">Agency</h3></div>
                <div class="jp-card__body">
                    <p class="mb-1"><span class="text-secondary">Agency:</span> <strong>{{ $agency?->name ?? ('Agency #'.$deposit->agency_id) }}</strong></p>
                    @if ($agencyCode !== null)
                        <p class="mb-1"><span class="text-secondary">{{ IdentityDisplay::labelAgencyCode() }}:</span> <strong>{{ $agencyCode }}</strong></p>
                    @endif
                    @if ($deposit->agent)
                        <p class="mb-0"><span class="text-secondary">{{ IdentityDisplay::labelLegacyAgentProfileCode() }}:</span> {{ IdentityDisplay::legacyAgentProfileCode($deposit->agent) }}</p>
                    @endif
                </div>
            </div>
        </div>
        <div class="col-md-6">
            <div class="card border-0 shadow-sm h-100">
                <div class="card-header border-0"><h3 class="jp-card__title mb-0">{{ IdentityDisplay::labelRequestedBy() }}</h3></div>
                <div class="jp-card__body">
                    <p class="mb-1"><span class="text-secondary">Name:</span> <strong>{{ $requester?->name ?? '—' }}</strong></p>
                    <p class="mb-1"><span class="text-secondary">Email:</span> {{ $requester?->email ?? '—' }}</p>
                    @if ($requester)
                        <p class="mb-1"><span class="text-secondary">{{ IdentityDisplay::labelUserActorId() }}:</span> <code>{{ IdentityDisplay::userActorId($requester) }}</code></p>
                        <p class="mb-0"><span class="text-secondary">{{ IdentityDisplay::labelAccessType() }}:</span> {{ IdentityDisplay::accessTypeLabel($requester) }}</p>
                    @endif
                </div>
            </div>
        </div>
    </div>

    <div class="row g-3 mb-4">
        <div class="col-md-6">
            <div class="card border-0 shadow-sm h-100">
                <div class="card-header border-0"><h3 class="jp-card__title mb-0">Request details</h3></div>
                <div class="jp-card__body">
                    <p class="mb-1"><span class="text-secondary">Amount:</span> <strong>Rs {{ number_format((float) $deposit->amount, 2) }}</strong></p>
                    <p class="mb-1"><span class="text-secondary">Method:</span> {{ $deposit->payment_method ?? '—' }}</p>
                    <p class="mb-1"><span class="text-secondary">Reference:</span> {{ $deposit->reference ?? '—' }}</p>
                    <p class="mb-1"><span class="text-secondary">Submitted:</span> {{ $deposit->created_at?->format('Y-m-d H:i') }}</p>
                    <p class="mb-1"><span class="text-secondary">Status:</span> <x-dashboard.status-badge :status="$deposit->status->value" /></p>
                    @if ($deposit->agent_note)
                        <p class="mb-0"><span class="text-secondary">Request note:</span> {{ $deposit->agent_note }}</p>
                    @endif
                    @if (filled($deposit->proof_path))
                        <p class="mb-0 mt-2">
                            <a href="{{ route('admin.agent-deposits.proof', $deposit) }}" class="jp-btn jp-btn--sm jp-btn--ghost" data-testid="admin-deposit-proof-download">Download proof</a>
                        </p>
                    @endif
                </div>
            </div>
        </div>
        <div class="col-md-6">
            <div class="card border-0 shadow-sm h-100">
                <div class="card-header border-0"><h3 class="jp-card__title mb-0">Wallet</h3></div>
                <div class="jp-card__body">
                    <p class="mb-1"><span class="text-secondary">Current balance:</span> <strong data-testid="admin-deposit-wallet-balance">Rs {{ number_format((float) ($deposit->wallet?->balance ?? 0), 2) }}</strong></p>
                    <p class="mb-0"><span class="text-secondary">Credit limit:</span>
                        @if ($deposit->wallet?->credit_limit !== null)
                            Rs {{ number_format((float) $deposit->wallet->credit_limit, 2) }}
                        @else
                            Not enabled
                        @endif
                    </p>
                </div>
            </div>
        </div>
    </div>

    @if ($deposit->reviewer && $deposit->status->value !== 'submitted')
        <div class="card border-0 shadow-sm mb-4">
            <div class="card-header border-0"><h3 class="jp-card__title mb-0">{{ IdentityDisplay::labelPerformedBy() }} (review)</h3></div>
            <div class="jp-card__body">
                <p class="mb-1"><span class="text-secondary">Name:</span> <strong>{{ $deposit->reviewer->name }}</strong></p>
                <p class="mb-1"><span class="text-secondary">Email:</span> {{ $deposit->reviewer->email }}</p>
                <p class="mb-0"><span class="text-secondary">{{ IdentityDisplay::labelUserActorId() }}:</span> <code>{{ IdentityDisplay::userActorId($deposit->reviewer) }}</code></p>
            </div>
        </div>
    @endif

    @if ($deposit->status->value === 'submitted')
        <div class="card border-0 shadow-sm" data-testid="admin-deposit-actions">
            <div class="jp-card__body">
                <div class="row g-3">
                    <div class="col-md-6">
                        <form method="post" action="{{ route('admin.agent-deposits.approve', $deposit) }}">
                            @csrf
                            @method('patch')
                            <button type="submit" class="btn btn-success" data-testid="admin-deposit-approve">Approve deposit</button>
                        </form>
                    </div>
                    <div class="col-md-6">
                        <form method="post" action="{{ route('admin.agent-deposits.reject', $deposit) }}">
                            @csrf
                            @method('patch')
                            <div class="mb-2">
                                <label class="jp-label" for="admin_note">Rejection reason (required)</label>
                                <textarea name="admin_note" id="admin_note" class="jp-control" rows="2" required>{{ old('admin_note') }}</textarea>
                            </div>
                            <button type="submit" class="jp-btn jp-btn--danger" data-testid="admin-deposit-reject">Reject deposit</button>
                        </form>
                    </div>
                </div>
            </div>
        </div>
    @elseif ($deposit->admin_note && $deposit->status->value === 'rejected')
        <div class="alert alert-light border small">
            <strong>Admin note:</strong> {{ $deposit->admin_note }}
        </div>
    @endif
@endsection
