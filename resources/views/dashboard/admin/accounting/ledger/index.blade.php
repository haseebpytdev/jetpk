@extends(client_layout('dashboard', 'admin'))

@section('title', $pageTitle ?? 'Accounting Ledger')

@section('page-header')
    <div class="jp-between">
        <div>
            <p class="jp-backlink"><a href="{{ route('admin.accounting.ledger.index') }}">← Accounting ledger</a></p>
            <h1 class="jp-page-title" data-testid="accounting-ledger-title">{{ $pageTitle ?? 'Accounting Ledger' }}</h1>
            @if (! empty($pageSubtitle))
                <p class="jp-muted">{{ $pageSubtitle }}</p>
            @endif
        </div>
        <div class="jp-toolbar">
            @if (Route::has('admin.accounting.reconciliation.index'))
                <a href="{{ route('admin.accounting.reconciliation.index') }}" class="jp-btn jp-btn--ghost">Reconciliation</a>
            @endif
        </div>
    </div>
@endsection

@section('content')
    @include('dashboard.accounting.ledger._table')
@endsection
