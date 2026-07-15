@extends(client_layout('mobile-app', 'mobile'))

@section('title', $transaction->transaction_ref ?? 'Ledger transaction')

@section('mobile_app_title', 'Ledger Transaction')

@section('mobile_app_back')
    <a href="{{ route('agent.accounting.ledger.index') }}" class="ota-mobile-app__back-btn" aria-label="Back to accounting ledger">
        <svg viewBox="0 0 24 24" width="22" height="22" fill="currentColor" aria-hidden="true"><path d="M20 11H7.83l5.59-5.59L12 4l-8 8 8 8 1.41-1.41L7.83 13H20v-2z"/></svg>
    </a>
@endsection

@section('content')
    @php
        $props = $transaction->properties ?? [];
    @endphp

    <div class="ota-mobile-agent" data-testid="ota-mobile-agent-accounting-ledger-show">
        <section class="ota-mobile-agent__card">
            <h2 class="ota-mobile-agent__card-title">{{ $transaction->transaction_ref ?? 'Transaction' }}</h2>
            <dl class="ota-mobile-agent__meta ota-mobile-agent__meta--finance">
                <div>
                    <dt>Status</dt>
                    <dd>@include('mobile.agent.partials.agent-status-pill', ['status' => $transaction->status->value])</dd>
                </div>
                <div>
                    <dt>Type</dt>
                    <dd>{{ str_replace('_', ' ', $transaction->transaction_type->value) }}</dd>
                </div>
                <div>
                    <dt>Amount</dt>
                    <dd class="ota-mobile-agent__amount">{{ $transaction->currency }} {{ number_format((float) $transaction->amount_total, 2) }}</dd>
                </div>
                <div>
                    <dt>Posted</dt>
                    <dd>{{ $transaction->posted_at?->format('Y-m-d H:i') ?? '—' }}</dd>
                </div>
            </dl>
            @if ($transaction->booking)
                <p class="ota-mobile-agent__text-safe">Booking: {{ $transaction->booking->booking_reference }}</p>
            @endif
            @if ($transaction->source_type)
                <p class="ota-mobile-agent__muted">Source: {{ class_basename($transaction->source_type) }} #{{ $transaction->source_id }}</p>
            @endif
            <p class="ota-mobile-agent__muted">Actor: {{ $transaction->actor_identifier ?? '—' }}</p>
            <p class="ota-mobile-agent__note">{{ $transaction->description ?? '—' }}</p>
            @if ($transaction->reversal_of_id && $transaction->reversalOf)
                <div class="ota-mobile-agent__actions">
                    <a href="{{ route('agent.accounting.ledger.show', $transaction->reversalOf) }}" class="ota-mobile-agent__btn ota-mobile-agent__btn--secondary">View reversal of</a>
                </div>
            @endif
        </section>

        <section class="ota-mobile-agent__list" aria-label="Ledger entries">
            <div class="ota-mobile-agent__card-head">
                <h2 class="ota-mobile-agent__card-title">Entries</h2>
            </div>
            @forelse ($transaction->entries ?? collect() as $entry)
                <article class="ota-mobile-agent__card">
                    <p class="ota-mobile-agent__text-safe">{{ $entry->account?->code ?? '—' }} · {{ $entry->account?->name ?? '—' }}</p>
                    <p class="ota-mobile-agent__muted">Type: {{ $entry->account?->account_type?->value ?? '—' }}</p>
                    <p class="ota-mobile-agent__note">{{ $entry->description ?? '—' }}</p>
                    <dl class="ota-mobile-agent__meta ota-mobile-agent__meta--finance">
                        <div>
                            <dt>Debit</dt>
                            <dd class="ota-mobile-agent__amount">{{ (float) $entry->debit > 0 ? number_format((float) $entry->debit, 2) : '—' }}</dd>
                        </div>
                        <div>
                            <dt>Credit</dt>
                            <dd class="ota-mobile-agent__amount">{{ (float) $entry->credit > 0 ? number_format((float) $entry->credit, 2) : '—' }}</dd>
                        </div>
                        <div>
                            <dt>Currency</dt>
                            <dd class="ota-mobile-agent__amount">{{ $entry->currency }}</dd>
                        </div>
                    </dl>
                </article>
            @empty
                <div class="ota-mobile-agent__card">
                    <p class="ota-mobile-agent__note">No entries.</p>
                </div>
            @endforelse
        </section>

        <section class="ota-mobile-agent__card" data-testid="accounting-ledger-entries-totals">
            <h2 class="ota-mobile-agent__card-title">Totals</h2>
            <dl class="ota-mobile-agent__meta ota-mobile-agent__meta--finance">
                <div>
                    <dt>Debit</dt>
                    <dd class="ota-mobile-agent__amount">{{ number_format((float) ($totals['debit'] ?? 0), 2) }}</dd>
                </div>
                <div>
                    <dt>Credit</dt>
                    <dd class="ota-mobile-agent__amount">{{ number_format((float) ($totals['credit'] ?? 0), 2) }}</dd>
                </div>
                <div>
                    <dt>Balanced</dt>
                    <dd class="ota-mobile-agent__amount">{{ ($totals['balanced'] ?? false) ? 'Yes' : 'No' }}</dd>
                </div>
            </dl>
            @if (! empty($props))
                <p class="ota-mobile-agent__muted">Properties are available in desktop view for detailed JSON.</p>
            @endif
        </section>
    </div>
@endsection
