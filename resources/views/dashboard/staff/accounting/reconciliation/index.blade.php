@extends(client_layout('dashboard', 'staff'))

@section('title', $pageTitle ?? 'Ledger Reconciliation')

@section('content')
    <div class="page-header d-print-none mb-4">
        <div class="row align-items-center">
            <div class="col">
                <h2 class="page-title" data-testid="accounting-reconciliation-title">{{ $pageTitle ?? 'Ledger Reconciliation' }}</h2>
                <p class="text-secondary mb-0">{{ $pageSubtitle ?? '' }}</p>
            </div>
            <div class="col-auto">
                @if (Route::has('staff.accounting.ledger.index'))
                    <a href="{{ route('staff.accounting.ledger.index') }}" class="btn btn-outline-secondary">Accounting Ledger</a>
                @endif
            </div>
        </div>
    </div>

    @include('dashboard.accounting._reconciliation-cards')
@endsection
