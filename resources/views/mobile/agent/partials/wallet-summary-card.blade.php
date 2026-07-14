@props([
    'summary',
    'title' => 'Wallet summary',
])

@php
    $ws = $summary ?? [];
    $currency = (string) ($ws['currency'] ?? 'PKR');
    $moneyPrefix = $currency === 'PKR' ? 'Rs ' : $currency.' ';
@endphp

<section class="ota-mobile-agent__card ota-mobile-agent__wallet-card" data-testid="ota-mobile-agent-wallet-summary">
    @if (filled($title))
        <h2 class="ota-mobile-agent__card-title">{{ $title }}</h2>
    @endif
    <dl class="ota-mobile-agent__meta ota-mobile-agent__meta--finance">
        <div>
            <dt>Available balance</dt>
            <dd class="ota-mobile-agent__amount">{{ $moneyPrefix }}{{ number_format((float) ($ws['available_balance'] ?? 0), 2) }}</dd>
        </div>
        <div>
            <dt>Wallet balance</dt>
            <dd class="ota-mobile-agent__amount">{{ $moneyPrefix }}{{ number_format((float) ($ws['balance'] ?? 0), 2) }}</dd>
        </div>
        <div>
            <dt>Pending deposits</dt>
            <dd class="ota-mobile-agent__amount">{{ $moneyPrefix }}{{ number_format((float) ($ws['pending_deposits'] ?? 0), 2) }}</dd>
        </div>
        <div>
            <dt>Credit limit</dt>
            <dd class="ota-mobile-agent__amount">
                @if ($ws['credit_enabled'] ?? false)
                    {{ $moneyPrefix }}{{ number_format((float) $ws['credit_limit'], 2) }}
                @else
                    Not enabled
                @endif
            </dd>
        </div>
    </dl>
    <p class="ota-mobile-agent__note">Credit limit is display-only. Booking credit enforcement is not enabled yet.</p>
</section>
