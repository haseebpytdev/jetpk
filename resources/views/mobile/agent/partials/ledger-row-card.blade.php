@props([
    'transaction',
    'moneyPrefix' => 'Rs ',
    'timezone' => null,
])

@php
    $before = (float) $transaction->balance_before;
    $after = (float) $transaction->balance_after;
    $amount = (float) $transaction->amount;
    $debit = $after < $before ? $amount : null;
    $credit = $after > $before ? $amount : null;
    $tz = $timezone ?? config('app.timezone');
    $localAt = $transaction->created_at?->timezone($tz);
    $related = null;
    if ($transaction->depositRequest) {
        $related = 'Deposit #'.$transaction->depositRequest->id;
    } elseif (is_array($transaction->meta) && ! empty($transaction->meta['booking_id'])) {
        $related = 'Booking #'.$transaction->meta['booking_id'];
    }
@endphp

<article class="ota-mobile-agent__card ota-mobile-agent__ledger-card" data-testid="ota-mobile-agent-ledger-row-{{ $transaction->id }}">
    <div class="ota-mobile-agent__card-head">
        <span class="ota-mobile-agent__ref">{{ $localAt?->format('j M Y, g:i A') ?? '—' }}</span>
        @include('mobile.agent.partials.agent-status-pill', ['status' => $transaction->status->value])
    </div>
    <p class="ota-mobile-agent__ledger-type">{{ ucwords(str_replace('_', ' ', $transaction->type->value)) }}</p>
    @if (filled($transaction->reference))
        <p class="ota-mobile-agent__muted ota-mobile-agent__text-safe">Ref: {{ $transaction->reference }}</p>
    @endif
    @if (filled($transaction->description))
        <p class="ota-mobile-agent__note">{{ $transaction->description }}</p>
    @endif
    <dl class="ota-mobile-agent__meta ota-mobile-agent__meta--finance">
        <div>
            <dt>Debit</dt>
            <dd class="ota-mobile-agent__amount">{{ $debit !== null ? $moneyPrefix.number_format($debit, 2) : '—' }}</dd>
        </div>
        <div>
            <dt>Credit</dt>
            <dd class="ota-mobile-agent__amount">{{ $credit !== null ? $moneyPrefix.number_format($credit, 2) : '—' }}</dd>
        </div>
        <div>
            <dt>Balance after</dt>
            <dd class="ota-mobile-agent__amount">{{ $moneyPrefix }}{{ number_format($after, 2) }}</dd>
        </div>
    </dl>
    @if ($related)
        <p class="ota-mobile-agent__muted">{{ $related }}</p>
    @endif
</article>
