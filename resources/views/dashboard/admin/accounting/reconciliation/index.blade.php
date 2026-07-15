@extends(client_layout('dashboard', 'admin'))

@section('title', $pageTitle ?? 'Ledger Reconciliation')

@section('page-header')
    <div class="jp-between">
        <div>
            <h1 class="jp-page-title" data-testid="accounting-reconciliation-title">{{ $pageTitle ?? 'Ledger Reconciliation' }}</h1>
            @if (! empty($pageSubtitle))
                <p class="jp-muted">{{ $pageSubtitle }}</p>
            @endif
        </div>
        <div class="jp-toolbar">
            <a href="{{ route('admin.accounting.reconciliation.export') }}" class="jp-btn jp-btn--outline jp-btn--sm" data-testid="accounting-reconciliation-export-csv">Export CSV</a>
            @if (Route::has('admin.accounting.ledger.index'))
                <a href="{{ route('admin.accounting.ledger.index') }}" class="jp-btn jp-btn--ghost jp-btn--sm">Accounting Ledger</a>
            @endif
        </div>
    </div>
@endsection

@section('content')
@endsection
