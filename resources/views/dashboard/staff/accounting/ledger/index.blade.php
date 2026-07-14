@extends(client_layout('dashboard', 'staff'))

@section('title', $pageTitle ?? 'Accounting Ledger')

@section('content')
    <div class="page-header d-print-none mb-4">
        <div class="row align-items-center">
            <div class="col">
                <h2 class="page-title" data-testid="accounting-ledger-title">{{ $pageTitle ?? 'Accounting Ledger' }}</h2>
                <p class="text-secondary mb-0">{{ $pageSubtitle ?? '' }}</p>
            </div>
            <div class="col-auto">
                @if (Route::has('staff.accounting.reconciliation.index'))
                    <a href="{{ route('staff.accounting.reconciliation.index') }}" class="btn btn-outline-secondary">Reconciliation</a>
                @endif
            </div>
        </div>
    </div>

    @include('dashboard.accounting.ledger._filters')
    @include('dashboard.accounting.ledger._table')
@endsection
