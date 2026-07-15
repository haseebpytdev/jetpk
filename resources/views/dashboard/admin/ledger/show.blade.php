@extends(client_layout('dashboard', 'admin'))

@section('title', 'Ledger entry')

@section('content')
    @php
        use App\Support\Identity\IdentityDisplay;

        $actorUser = $transaction->creator ?? $transaction->approver ?? $transaction->user;
        $actorCode = IdentityDisplay::userActorId($actorUser);
        $before = (float) $transaction->balance_before;
        $after = (float) $transaction->balance_after;
        $amount = (float) $transaction->amount;
        $debit = $after < $before ? $amount : null;
        $credit = $after > $before ? $amount : null;
        $indexRoute = ($routePrefix ?? 'admin.ledger').'.index';
    @endphp

    <div class="page-header d-print-none mb-4">
        <div class="row align-items-center">
            <div class="col">
                <h2 class="jp-page-title">Ledger entry #{{ $transaction->id }}</h2>
                <p class="text-secondary mb-0">{{ $agencyName }}@if ($actorUser) · {{ IdentityDisplay::labelPerformedBy() }}: {{ $actorUser->name }}@endif</p>
            </div>
            <div class="col-auto">
                <a href="{{ route($indexRoute) }}" class="jp-btn jp-btn--ghost">Back to ledger</a>
            </div>
        </div>
    </div>

    <div class="jp-card">
        <div class="jp-card__body">
            <dl class="row mb-0">
                <dt class="col-sm-3">Agency</dt><dd class="col-sm-9">{{ $agencyName }}</dd>
                <dt class="col-sm-3">{{ IdentityDisplay::labelPerformedBy() }}</dt>
                <dd class="col-sm-9" data-testid="ledger-show-actor">
                    @if ($actorUser)
                        <div>{{ $actorUser->name }}</div>
                        @if ($actorUser->email)
                            <div class="small text-secondary">{{ $actorUser->email }}</div>
                        @endif
                    @else
                        <div>System</div>
                    @endif
                </dd>
                <dt class="col-sm-3">{{ IdentityDisplay::labelUserActorId() }}</dt><dd class="col-sm-9 font-monospace small">{{ $actorCode }}</dd>
                <dt class="col-sm-3">Type</dt><dd class="col-sm-9 text-capitalize">{{ str_replace('_', ' ', $transaction->type->value) }}</dd>
                <dt class="col-sm-3">Status</dt><dd class="col-sm-9"><x-dashboard.status-badge :status="$transaction->status->value" /></dd>
                <dt class="col-sm-3">Debit</dt><dd class="col-sm-9">{{ $debit !== null ? number_format($debit, 2) : '—' }}</dd>
                <dt class="col-sm-3">Credit</dt><dd class="col-sm-9">{{ $credit !== null ? number_format($credit, 2) : '—' }}</dd>
                <dt class="col-sm-3">Reference</dt><dd class="col-sm-9">{{ $transaction->reference ?? '—' }}</dd>
                <dt class="col-sm-3">Description</dt><dd class="col-sm-9">{{ $transaction->description ?? '—' }}</dd>
                <dt class="col-sm-3">Created</dt><dd class="col-sm-9">{{ $transaction->created_at?->toDayDateTimeString() ?? '—' }}</dd>
            </dl>
        </div>
    </div>
@endsection
