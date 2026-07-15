@props([
    'deposit',
])

<article class="ota-mobile-agent__card ota-mobile-agent__deposit-card" data-testid="ota-mobile-agent-deposit-card">
    <div class="ota-mobile-agent__card-head">
        <span class="ota-mobile-agent__amount">Rs {{ number_format((float) $deposit->amount, 2) }}</span>
        @include('mobile.agent.partials.agent-status-pill', ['status' => $deposit->status->value])
    </div>
    <dl class="ota-mobile-agent__meta">
        <div>
            <dt>Method</dt>
            <dd>{{ $deposit->payment_method ?? '—' }}</dd>
        </div>
        @if (filled($deposit->reference))
            <div>
                <dt>Reference</dt>
                <dd class="ota-mobile-agent__text-safe">{{ $deposit->reference }}</dd>
            </div>
        @endif
        <div>
            <dt>Submitted</dt>
            <dd>{{ $deposit->created_at?->format('j M Y, g:i A') ?? '—' }}</dd>
        </div>
    </dl>
</article>
