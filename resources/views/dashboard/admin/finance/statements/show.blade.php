@extends(client_layout('dashboard', 'admin'))

@section('title', $pageTitle ?? 'Agent Statement')

@section('page-header')
    <div class="jp-between">
        <div class="col">
            <h1 class="jp-page-title" data-testid="finance-statement-show-title">{{ $pageTitle ?? 'Agent Statement' }}</h1>
            <p class="text-secondary mb-0">{{ $statement['period']['from'] ?? '' }} — {{ $statement['period']['to'] ?? '' }}</p>
        </div>
        <div class="col-auto">
            <a href="{{ route($indexRoute ?? 'admin.finance.statements.index') }}" class="jp-btn jp-btn--ghost">Back to list</a>
        </div>
    </div>
@endsection

@section('content')
    @include('dashboard.finance.statements._filters', ['agency' => $agency, 'statement' => $statement, 'routePrefix' => $routePrefix ?? 'admin.finance.statements'])
    @include('dashboard.finance.statements._summary-cards', ['statement' => $statement])
    @include('dashboard.finance.statements._movement-table', ['statement' => $statement])
    @include('dashboard.finance.statements._ledger-reconciliation', ['statement' => $statement])
@endsection
