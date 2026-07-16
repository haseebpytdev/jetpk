@extends(client_layout('agent-portal', 'agent'))

@section('title', $pageTitle ?? 'Agency Statement')

@section('account_title', $pageTitle ?? 'Agency Statement')
@section('account_subtitle', ($agency->name ?? 'Agency').' · '.($statement['period']['from'] ?? '').' — '.($statement['period']['to'] ?? ''))

@section('account_content')
    @include('themes.frontend.jetpakistan.components.portal.finance.statement-filters', [
        'agency' => $agency,
        'statement' => $statement,
        'routePrefix' => $routePrefix ?? 'agent.finance.statement',
    ])
    @include('themes.frontend.jetpakistan.components.portal.finance.statement-summary-cards', ['statement' => $statement])
    @include('themes.frontend.jetpakistan.components.portal.finance.statement-movement-table', ['statement' => $statement])
    @include('themes.frontend.jetpakistan.components.portal.finance.statement-reconciliation', ['statement' => $statement])
@endsection
