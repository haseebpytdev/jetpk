@extends(client_layout('agent-portal', 'agent'))

@section('account_title', $transaction->transaction_ref ?? 'Transaction')

@section('account_actions')
    <a href="{{ route(($routePrefix ?? 'agent.accounting.ledger').'.index') }}" class="ota-account-btn ota-account-btn--secondary ota-account-btn--sm">Back</a>
@endsection

@section('account_content')
    @php
        $props = $transaction->properties ?? [];
        $showRoutePrefix = $routePrefix ?? 'agent.accounting.ledger';
    @endphp

    <div class="ota-account-card mb-3">
        <dl class="ota-account-dl">
            <dt>Status</dt><dd>{{ $transaction->status->value }}</dd>
            <dt>Type</dt><dd class="text-capitalize">{{ str_replace('_', ' ', $transaction->transaction_type->value) }}</dd>
            <dt>Amount</dt><dd>{{ $transaction->currency }} {{ number_format((float) $transaction->amount_total, 2) }}</dd>
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
            <dt>{{ \App\Support\Identity\IdentityDisplay::labelUserActorId() }}</dt><dd class="font-monospace small" data-testid="accounting-ledger-show-actor">{{ $transaction->actor_identifier ?? '—' }}</dd>
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
                <dd><pre class="small mb-0" data-testid="accounting-ledger-properties">{{ json_encode($props, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE) }}</pre></dd>
            @endif
        </dl>
    </div>

    <div class="ota-account-card">
        @include('dashboard.accounting.ledger._entries')
    </div>
@endsection
