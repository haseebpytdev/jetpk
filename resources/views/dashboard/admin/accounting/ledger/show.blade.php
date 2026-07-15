@extends(client_layout('dashboard', 'admin'))

@section('title', 'Accounting transaction')

@section('content')
    @php
        $indexRoute = ($routePrefix ?? 'admin.accounting.ledger').'.index';
        $showRoutePrefix = $routePrefix ?? 'admin.accounting.ledger';
        $props = $transaction->properties ?? [];
    @endphp

    <div class="page-header d-print-none mb-4">
        <div class="row align-items-center">
            <div class="col">
                <h2 class="jp-page-title" data-testid="accounting-ledger-show-title">{{ $transaction->transaction_ref }}</h2>
                <p class="text-secondary mb-0 text-capitalize">{{ str_replace('_', ' ', $transaction->transaction_type->value) }}</p>
            </div>
            <div class="col-auto">
                <a href="{{ route($indexRoute) }}" class="jp-btn jp-btn--ghost">Back to ledger</a>
            </div>
        </div>
    </div>

    <div class="jp-card">
        <div class="jp-card__body">
            <dl class="row mb-0">
                <dt class="col-sm-3">Status</dt>
                <dd class="col-sm-9"><x-dashboard.status-badge :status="$transaction->status->value" /></dd>
                <dt class="col-sm-3">Agency</dt>
                <dd class="col-sm-9">{{ $transaction->agency?->name ?? '—' }}</dd>
                <dt class="col-sm-3">Amount</dt>
                <dd class="col-sm-9">{{ $transaction->currency }} {{ number_format((float) $transaction->amount_total, 2) }}</dd>
                <dt class="col-sm-3">Posted at</dt>
                <dd class="col-sm-9">{{ $transaction->posted_at?->toDayDateTimeString() ?? '—' }}</dd>
                <dt class="col-sm-3">Occurred at</dt>
                <dd class="col-sm-9">{{ $transaction->occurred_at?->toDayDateTimeString() ?? '—' }}</dd>
                <dt class="col-sm-3">Source</dt>
                <dd class="col-sm-9">
                    @if ($transaction->source_type)
                        {{ $transaction->source_type }} #{{ $transaction->source_id }}
                    @else
                        —
                    @endif
                </dd>
                <dt class="col-sm-3">{{ \App\Support\Identity\IdentityDisplay::labelPostedBy() }}</dt>
                <dd class="col-sm-9" data-testid="accounting-ledger-show-actor-performer">{{ $transaction->actorUser?->name ?? '—' }}</dd>
                <dt class="col-sm-3">{{ \App\Support\Identity\IdentityDisplay::labelUserActorId() }}</dt>
                <dd class="col-sm-9 font-monospace small" data-testid="accounting-ledger-show-actor">{{ $transaction->actor_identifier ?? '—' }}</dd>
                <dt class="col-sm-3">Customer / guest</dt>
                <dd class="col-sm-9">
                    @if ($transaction->customer)
                        {{ $transaction->customer->name ?? $transaction->customer->email }}
                    @elseif ($transaction->guest_key)
                        {{ $transaction->guest_key }}
                    @else
                        —
                    @endif
                </dd>
                <dt class="col-sm-3">Booking</dt>
                <dd class="col-sm-9">
                    @if ($transaction->booking)
                        @php
                            $bookingRoute = match (true) {
                                str_starts_with($showRoutePrefix, 'staff.') => 'staff.bookings.show',
                                str_starts_with($showRoutePrefix, 'agent.') => 'agent.bookings.show',
                                default => 'admin.bookings.show',
                            };
                        @endphp
                        @if (Route::has($bookingRoute))
                            <a href="{{ route($bookingRoute, $transaction->booking) }}">{{ $transaction->booking->booking_reference }}</a>
                        @else
                            {{ $transaction->booking->booking_reference }}
                        @endif
                    @else
                        —
                    @endif
                </dd>
                <dt class="col-sm-3">Description</dt>
                <dd class="col-sm-9">{{ $transaction->description ?? '—' }}</dd>
                @if ($transaction->reversal_of_id && $transaction->reversalOf)
                    <dt class="col-sm-3">Reversal of</dt>
                    <dd class="col-sm-9">
                        <a href="{{ route($showRoutePrefix.'.show', $transaction->reversalOf) }}">{{ $transaction->reversalOf->transaction_ref }}</a>
                    </dd>
                @endif
                @if ($transaction->reversals?->isNotEmpty())
                    <dt class="col-sm-3">Reversals</dt>
                    <dd class="col-sm-9">
                        @foreach ($transaction->reversals as $rev)
                            <a href="{{ route($showRoutePrefix.'.show', $rev) }}">{{ $rev->transaction_ref }}</a>@if (! $loop->last), @endif
                        @endforeach
                    </dd>
                @endif
                @if (! empty($props))
                    <dt class="col-sm-3">Properties</dt>
                    <dd class="col-sm-9">
                        <pre class="small bg-light p-2 rounded mb-0" data-testid="accounting-ledger-properties">{{ json_encode($props, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE) }}</pre>
                    </dd>
                @endif
            </dl>
        </div>
    </div>

    @include('dashboard.accounting.ledger._entries')
@endsection
