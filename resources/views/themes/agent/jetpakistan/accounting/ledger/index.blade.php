@extends(client_layout('agent-portal', 'agent'))

@section('title', $pageTitle ?? 'Accounting Ledger')

@section('account_title', $pageTitle ?? 'Accounting Ledger')
@section('account_subtitle', 'Double-entry ledger transactions for your agency.')

@section('account_content')
    @include('themes.frontend.jetpakistan.components.portal.finance.ledger-summary-cards', ['summary' => $summary ?? []])

    <div class="jp-panel">
        @include('themes.frontend.jetpakistan.components.portal.finance.ledger-filters', [
            'filters' => $filters ?? [],
            'scope' => $scope ?? 'agency',
            'agencies' => $agencies ?? collect(),
            'perPageOptions' => $perPageOptions ?? [25, 50],
            'perPage' => $perPage ?? 25,
            'routePrefix' => $routePrefix ?? 'agent.accounting.ledger',
        ])
        @include('themes.frontend.jetpakistan.components.portal.finance.ledger-transaction-table', [
            'transactions' => $transactions,
            'scope' => $scope ?? 'agency',
            'routePrefix' => $routePrefix ?? 'agent.accounting.ledger',
        ])
    </div>
@endsection
