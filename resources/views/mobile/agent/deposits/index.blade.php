@extends('layouts.mobile-app')

@section('title', 'Deposits')

@section('mobile_app_title', 'Deposits')

@section('mobile_app_top_actions')
    @if (auth()->user()?->hasAgentPermission(\App\Support\Agents\AgentPermission::PaymentsUpload))
        <a href="{{ route('agent.deposits.create') }}" class="ota-mobile-app__top-action" data-testid="ota-mobile-agent-deposits-create-link">New</a>
    @endif
@endsection

@section('content')
    <div class="ota-mobile-agent" data-testid="ota-mobile-agent-deposits">
        @if (session('status') === 'deposit-submitted')
            @include('mobile.components.alert', ['type' => 'success', 'message' => 'Deposit request submitted. Finance will review your proof.'])
        @endif

        @include('mobile.agent.partials.wallet-summary-card', [
            'summary' => $summary,
            'title' => 'Wallet snapshot',
        ])

        @if ($deposits->isEmpty())
            <div class="ota-mobile-agent__empty" data-testid="ota-mobile-agent-deposits-empty">
                <p class="ota-mobile-agent__empty-title">No deposits yet</p>
                <p class="ota-mobile-agent__empty-help">Request a deposit after transferring funds to the agency account.</p>
                @if (auth()->user()?->hasAgentPermission(\App\Support\Agents\AgentPermission::PaymentsUpload))
                    <a href="{{ route('agent.deposits.create') }}" class="ota-mobile-agent__btn ota-mobile-agent__btn--primary">New deposit request</a>
                @endif
            </div>
        @else
            <div class="ota-mobile-agent__list">
                @foreach ($deposits as $deposit)
                    @include('mobile.agent.partials.deposit-card', ['deposit' => $deposit])
                @endforeach
            </div>
            @if ($deposits->hasPages())
                <div class="ota-mobile-agent__pagination">{{ $deposits->links() }}</div>
            @endif
        @endif
    </div>
@endsection
