@extends(client_layout('agent-portal', 'agent'))

@section('title', $pageTitle ?? 'Agency Statement')

@section('content')
    <div class="ota-account-page-header mb-4">
        <h1 class="ota-account-page-title" data-testid="agent-finance-statement-title">{{ $pageTitle ?? 'Agency Statement' }}</h1>
        <p class="text-secondary mb-0">{{ $agency->name }} · {{ $statement['period']['from'] ?? '' }} — {{ $statement['period']['to'] ?? '' }}</p>
    </div>

    @include('dashboard.finance.statements._filters', [
        'agency' => $agency,
        'statement' => $statement,
        'routePrefix' => $routePrefix ?? 'agent.finance.statement',
    ])
    @include('dashboard.finance.statements._summary-cards', ['statement' => $statement])
    @include('dashboard.finance.statements._movement-table', ['statement' => $statement])
    @include('dashboard.finance.statements._ledger-reconciliation', ['statement' => $statement])
@endsection
