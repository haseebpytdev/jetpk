@extends('layouts.mobile-app')

@section('title', 'Wallet')

@section('mobile_app_title', 'Wallet')

@section('mobile_app_top_actions')
    @if ($canUploadPayments ?? false)
        <a href="{{ route('agent.deposits.create') }}" class="ota-mobile-app__top-action" data-testid="ota-mobile-agent-wallet-deposit-link">Deposit</a>
    @endif
@endsection

@section('content')
    @php
        $ws = $summary ?? [];
        $currency = (string) ($ws['currency'] ?? 'PKR');
        $moneyPrefix = $currency === 'PKR' ? 'Rs ' : $currency.' ';
    @endphp

    <div class="ota-mobile-agent" data-testid="ota-mobile-agent-wallet">
        @include('mobile.agent.partials.wallet-summary-card', ['summary' => $summary])

        @if (($pendingDeposits ?? collect())->isNotEmpty())
            <section class="ota-mobile-agent__list" aria-label="Pending deposits">
                <h2 class="ota-mobile-agent__card-title">Pending deposits</h2>
                @foreach ($pendingDeposits as $deposit)
                    @include('mobile.agent.partials.deposit-card', ['deposit' => $deposit])
                @endforeach
            </section>
        @endif

        <section class="ota-mobile-agent__card">
            <div class="ota-mobile-agent__card-head">
                <h2 class="ota-mobile-agent__card-title">Quick links</h2>
            </div>
            <div class="ota-mobile-agent__actions">
                @if ($canUploadPayments ?? false)
                    <a href="{{ route('agent.deposits.create') }}" class="ota-mobile-agent__btn ota-mobile-agent__btn--primary">Request deposit</a>
                @endif
                @if (Route::has('agent.deposits.index'))
                    <a href="{{ route('agent.deposits.index') }}" class="ota-mobile-agent__btn ota-mobile-agent__btn--secondary">Deposit history</a>
                @endif
                @if (($canViewLedger ?? false) && Route::has('agent.ledger.index'))
                    <a href="{{ route('agent.ledger.index') }}" class="ota-mobile-agent__btn ota-mobile-agent__btn--secondary">Full ledger</a>
                @endif
            </div>
        </section>

        <section class="ota-mobile-agent__list" aria-label="Recent transactions">
            <div class="ota-mobile-agent__card-head">
                <h2 class="ota-mobile-agent__card-title">Recent transactions</h2>
                @if (($canViewLedger ?? false) && Route::has('agent.ledger.index'))
                    <a href="{{ route('agent.ledger.index') }}" class="ota-mobile-agent__link">View all</a>
                @endif
            </div>
            @forelse ($recentTransactions as $tx)
                @include('mobile.agent.partials.ledger-row-card', [
                    'transaction' => $tx,
                    'moneyPrefix' => $moneyPrefix,
                ])
            @empty
                <div class="ota-mobile-agent__card">
                    <p class="ota-mobile-agent__note">No transactions yet.</p>
                </div>
            @endforelse
        </section>
    </div>
@endsection
