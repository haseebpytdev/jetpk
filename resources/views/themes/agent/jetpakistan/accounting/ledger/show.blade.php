@extends(client_layout('agent-portal', 'agent'))

@section('title', $transaction->transaction_ref ?? 'Transaction')

@section('account_title', $transaction->transaction_ref ?? 'Transaction')

@section('account_actions')
    <a href="{{ route(($routePrefix ?? 'agent.accounting.ledger').'.index') }}" class="jp-btn jp-btn--ghost jp-btn--sm">Back</a>
@endsection

@section('account_content')
    @php
        $props = $transaction->properties ?? [];
        $showRoutePrefix = $routePrefix ?? 'agent.accounting.ledger';
    @endphp

    <div class="jp-card jp-card--detail">
        <dl class="jp-dl jp-dl--detail">
            <dt>Status</dt><dd>{{ $transaction->status->value }}</dd>
            <dt>Type</dt><dd class="jp-capitalize">{{ str_replace('_', ' ', $transaction->transaction_type->value) }}</dd>
            <dt>Amount</dt><dd class="jp-money">{{ $transaction->currency }} {{ number_format((float) $transaction->amount_total, 2) }}</dd>
            <dt>Posted</dt><dd>{{ $transaction->posted_at?->format('Y-m-d H:i') ?? '—' }}</dd>
            <dt>Source</dt>
            <dd>
                @if ($transaction->source_type)
                    {{ class_basename($transaction->source_type) }} #{{ $transaction->source_id }}
                @else
                    —
                @endif
            </dd>
            <dt>{{ \App\Support\Identity\IdentityDisplay::labelPostedBy() }}</dt><dd data-testid="accounting-ledger-show-actor-performer">{{ $transaction->actorUser?->name ?? '—' }}</dd>
            <dt>{{ \App\Support\Identity\IdentityDisplay::labelUserActorId() }}</dt><dd class="jp-mono" data-testid="accounting-ledger-show-actor">{{ $transaction->actor_identifier ?? '—' }}</dd>
            <dt>Booking</dt>
            <dd>
                @if ($transaction->booking)
                    @if (Route::has('agent.bookings.show'))
                        <a href="{{ route('agent.bookings.show', $transaction->booking) }}">{{ $transaction->booking->booking_reference }}</a>
                    @else
                        {{ $transaction->booking->booking_reference }}
                    @endif
                @else
                    —
                @endif
            </dd>
            <dt>Description</dt><dd>{{ $transaction->description ?? '—' }}</dd>
            @if ($transaction->reversal_of_id && $transaction->reversalOf)
                <dt>Reversal of</dt>
                <dd><a href="{{ route($showRoutePrefix.'.show', $transaction->reversalOf) }}">{{ $transaction->reversalOf->transaction_ref }}</a></dd>
            @endif
            @if (! empty($props))
                <dt>Properties</dt>
                <dd><pre class="jp-code" data-testid="accounting-ledger-properties">{{ json_encode($props, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE) }}</pre></dd>
            @endif
        </dl>
    </div>

    @include('themes.frontend.jetpakistan.components.portal.finance.ledger-entries-table', [
        'transaction' => $transaction,
        'totals' => $totals ?? [],
    ])
@endsection
