@extends(client_layout('dashboard', 'admin'))

@section('title', 'Adjustment #'.$transaction->id)

@section('page-header')
    <x-dashboard.section-header title="Manual adjustment #{{ $transaction->id }}" subtitle="{{ $transaction->agency?->name }}">
        <x-slot name="actions">
            <a href="{{ route('admin.finance.adjustments.index') }}" class="jp-btn jp-btn--ghost btn-sm">Back to list</a>
            @if ($transaction->agency_id)
                <a href="{{ route('admin.finance.statements.show', $transaction->agency_id) }}" class="jp-btn jp-btn--ghost btn-sm">Agency statement</a>
            @endif
            @if ($canReverse)
                <a href="{{ route('admin.finance.adjustments.reverse.confirm', $transaction) }}" class="jp-btn jp-btn--danger btn-sm" data-testid="finance-adjustment-reverse-link">Reverse</a>
            @endif
        </x-slot>
    </x-dashboard.section-header>
@endsection

@section('content')
    @if (session('status') === 'adjustment-created')
        <div class="jp-alert jp-alert--success" data-testid="finance-adjustment-created-flash">Adjustment posted successfully.</div>
    @endif
    @if (session('status') === 'adjustment-existing')
        <div class="jp-alert jp-alert--info" data-testid="finance-adjustment-existing-flash">This adjustment was already posted (duplicate submission ignored).</div>
    @endif
    @if (session('status') === 'adjustment-reversed')
        <div class="jp-alert jp-alert--success" data-testid="finance-adjustment-reversed-flash">Reversal posted successfully.</div>
    @endif

    <div class="row g-3" data-testid="finance-adjustment-show-detail">
        <div class="col-lg-6">
            <div class="card border-0 shadow-sm h-100">
                <div class="card-header border-0"><h3 class="jp-card__title mb-0">Wallet transaction</h3></div>
                <div class="jp-card__body">
                    <p class="mb-1"><span class="text-secondary">Type:</span> <strong>{{ str_replace('_', ' ', $transaction->type->value) }}</strong></p>
                    <p class="mb-1"><span class="text-secondary">Amount:</span> <strong>Rs {{ number_format((float) $transaction->amount, 2) }}</strong></p>
                    <p class="mb-1"><span class="text-secondary">Balance before:</span> Rs {{ number_format((float) $transaction->balance_before, 2) }}</p>
                    <p class="mb-1"><span class="text-secondary">Balance after:</span> <strong data-testid="finance-adjustment-balance-after">Rs {{ number_format((float) $transaction->balance_after, 2) }}</strong></p>
                    <p class="mb-1"><span class="text-secondary">Reference:</span> {{ $transaction->reference ?? '—' }}</p>
                    <p class="mb-1"><span class="text-secondary">Status:</span> {{ $transaction->status->value }}</p>
                    <p class="mb-1"><span class="text-secondary">Posted:</span> {{ $transaction->created_at?->format('Y-m-d H:i:s') }}</p>
                    <p class="mb-1"><span class="text-secondary">Performed by:</span> {{ $transaction->creator?->name ?? '—' }}</p>
                    @php
                        $meta = is_array($transaction->meta) ? $transaction->meta : [];
                    @endphp
                    @if (! empty($meta['reversal_of_wallet_transaction_id']))
                        <p class="mb-1"><span class="text-secondary">Reversal of:</span>
                            <a href="{{ route('admin.finance.adjustments.show', $meta['reversal_of_wallet_transaction_id']) }}">#{{ $meta['reversal_of_wallet_transaction_id'] }}</a>
                        </p>
                        <p class="mb-1"><span class="text-secondary">Reversal reason:</span> {{ $meta['reversal_reason'] ?? '—' }}</p>
                    @else
                        <p class="mb-1"><span class="text-secondary">Reason:</span> {{ str_replace('_', ' ', $meta['adjustment_reason'] ?? '—') }}</p>
                        @if (! empty($meta['adjustment_note']))
                            <p class="mb-0"><span class="text-secondary">Note:</span> {{ $meta['adjustment_note'] }}</p>
                        @endif
                    @endif
                    @if (! empty($meta['performed_by_identifier']))
                        <p class="mb-0 mt-2"><span class="text-secondary">{{ \App\Support\Identity\IdentityDisplay::labelUserActorId() }}:</span> <code>{{ $meta['performed_by_identifier'] }}</code></p>
                    @endif
                    @if (! empty($meta['reversed_by_identifier']))
                        <p class="mb-0 mt-2"><span class="text-secondary">Reversed by:</span> <code>{{ $meta['reversed_by_identifier'] }}</code></p>
                    @endif
                </div>
            </div>
        </div>
        <div class="col-lg-6">
            <div class="card border-0 shadow-sm h-100">
                <div class="card-header border-0"><h3 class="jp-card__title mb-0">Accounting ledger</h3></div>
                <div class="jp-card__body">
                    @if ($ledgerTransaction)
                        <p class="mb-1"><span class="text-secondary">Ledger ref:</span> <strong>{{ $ledgerTransaction->transaction_ref }}</strong></p>
                        <p class="mb-1"><span class="text-secondary">Type:</span> {{ $ledgerTransaction->transaction_type->value ?? $ledgerTransaction->transaction_type }}</p>
                        <p class="mb-1"><span class="text-secondary">Amount:</span> Rs {{ number_format((float) $ledgerTransaction->amount_total, 2) }}</p>
                        <p class="mb-1"><span class="text-secondary">Status:</span> {{ $ledgerTransaction->status->value ?? $ledgerTransaction->status }}</p>
                        <p class="mb-0">
                            <a href="{{ route('admin.accounting.ledger.show', $ledgerTransaction) }}" class="jp-btn jp-btn--sm jp-btn--outline" data-testid="finance-adjustment-ledger-link">View ledger transaction</a>
                        </p>
                    @else
                        <p class="text-secondary mb-0">No linked ledger transaction found.</p>
                    @endif
                </div>
            </div>
        </div>
    </div>

    @if ($reversalTransaction)
        <div class="card border-0 shadow-sm mt-3" data-testid="finance-adjustment-reversal-detail">
            <div class="card-header border-0"><h3 class="jp-card__title mb-0">Compensating reversal</h3></div>
            <div class="jp-card__body">
                <p class="mb-1"><span class="text-secondary">Reversal transaction:</span>
                    <a href="{{ route('admin.finance.adjustments.show', $reversalTransaction) }}">#{{ $reversalTransaction->id }}</a>
                    ({{ str_replace('_', ' ', $reversalTransaction->type->value) }}, Rs {{ number_format((float) $reversalTransaction->amount, 2) }})
                </p>
                @php $revMeta = is_array($reversalTransaction->meta) ? $reversalTransaction->meta : []; @endphp
                <p class="mb-1"><span class="text-secondary">Reason:</span> {{ $revMeta['reversal_reason'] ?? '—' }}</p>
                @if ($reversalLedgerTransaction)
                    <p class="mb-0">
                        <a href="{{ route('admin.accounting.ledger.show', $reversalLedgerTransaction) }}" class="jp-btn jp-btn--sm jp-btn--outline">View reversal ledger</a>
                    </p>
                @endif
            </div>
        </div>
    @endif

    @if ($originalTransaction)
        <div class="card border-0 shadow-sm mt-3" data-testid="finance-adjustment-original-detail">
            <div class="card-header border-0"><h3 class="jp-card__title mb-0">Original adjustment</h3></div>
            <div class="jp-card__body">
                <p class="mb-1">
                    <a href="{{ route('admin.finance.adjustments.show', $originalTransaction) }}">#{{ $originalTransaction->id }}</a>
                    — {{ str_replace('_', ' ', $originalTransaction->type->value) }}, Rs {{ number_format((float) $originalTransaction->amount, 2) }}
                </p>
                @if ($originalLedgerTransaction)
                    <p class="mb-0">
                        <a href="{{ route('admin.accounting.ledger.show', $originalLedgerTransaction) }}" class="jp-btn jp-btn--sm jp-btn--ghost">View original ledger</a>
                    </p>
                @endif
            </div>
        </div>
    @endif

    @if ($canReverse)
        <div class="card border-0 shadow-sm mt-3" data-testid="finance-adjustment-reverse-panel">
            <div class="card-header border-0"><h3 class="jp-card__title mb-0">Reverse this adjustment</h3></div>
            <div class="jp-card__body">
                <div class="jp-alert jp-alert--warn border-0 mb-3">
                    Reversal creates a new compensating wallet and ledger transaction. It does not delete the original.
                </div>
                <a href="{{ route('admin.finance.adjustments.reverse.confirm', $transaction) }}" class="jp-btn jp-btn--danger">Continue to reversal confirmation</a>
            </div>
        </div>
    @endif
@endsection
