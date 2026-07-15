@extends(client_layout('mobile-app', 'mobile'))

@section('title', 'My commissions')

@section('mobile_app_title', 'Commissions')

@section('content')
    <div class="ota-mobile-agent" data-testid="ota-mobile-agent-commissions-index">
        <section class="ota-mobile-agent__card">
            <h2 class="ota-mobile-agent__card-title">Summary</h2>
            <dl class="ota-mobile-agent__meta ota-mobile-agent__meta--finance">
                <div>
                    <dt>Current balance</dt>
                    <dd class="ota-mobile-agent__amount">Rs {{ number_format((float) $balance, 2) }}</dd>
                </div>
                <div>
                    <dt>Pending</dt>
                    <dd class="ota-mobile-agent__amount">Rs {{ number_format((float) $pending, 2) }}</dd>
                </div>
                <div>
                    <dt>Approved</dt>
                    <dd class="ota-mobile-agent__amount">Rs {{ number_format((float) $approved, 2) }}</dd>
                </div>
                <div>
                    <dt>Paid</dt>
                    <dd class="ota-mobile-agent__amount">Rs {{ number_format((float) $paid, 2) }}</dd>
                </div>
            </dl>
        </section>

        <section class="ota-mobile-agent__list" aria-label="Commission entries">
            <div class="ota-mobile-agent__card-head">
                <h2 class="ota-mobile-agent__card-title">Entries</h2>
            </div>
            @forelse($entries as $entry)
                <article class="ota-mobile-agent__card">
                    <div class="ota-mobile-agent__card-head">
                        <span class="ota-mobile-agent__ref">{{ $entry->created_at?->format('j M Y, g:i A') ?? '—' }}</span>
                        @include('mobile.agent.partials.agent-status-pill', ['status' => $entry->status->value])
                    </div>
                    <p class="ota-mobile-agent__muted">Type: {{ str_replace('_', ' ', $entry->type->value) }}</p>
                    <p class="ota-mobile-agent__text-safe">Booking: {{ $entry->booking?->booking_reference ?? 'N/A' }}</p>
                    <p class="ota-mobile-agent__amount ota-mobile-agent__amount--total">Rs {{ number_format((float) $entry->commission_amount, 2) }}</p>
                </article>
            @empty
                <div class="ota-mobile-agent__card">
                    <p class="ota-mobile-agent__note">No commission entries yet.</p>
                </div>
            @endforelse
        </section>

        <section class="ota-mobile-agent__list" aria-label="Commission statements">
            <div class="ota-mobile-agent__card-head">
                <h2 class="ota-mobile-agent__card-title">Statements</h2>
            </div>
            @forelse($statements as $statement)
                <article class="ota-mobile-agent__card">
                    <div class="ota-mobile-agent__card-head">
                        <span class="ota-mobile-agent__ref">{{ $statement->statement_number ?? 'N/A' }}</span>
                        @include('mobile.agent.partials.agent-status-pill', ['status' => $statement->status->value])
                    </div>
                    <p class="ota-mobile-agent__muted">{{ $statement->period_start?->format('Y-m-d') ?? 'N/A' }} - {{ $statement->period_end?->format('Y-m-d') ?? 'N/A' }}</p>
                    <p class="ota-mobile-agent__amount ota-mobile-agent__amount--total">Rs {{ number_format((float) $statement->closing_balance, 2) }}</p>
                    <div class="ota-mobile-agent__actions">
                        <a class="ota-mobile-agent__btn ota-mobile-agent__btn--primary" href="{{ route('agent.commissions.statements.show', $statement) }}">View statement</a>
                    </div>
                </article>
            @empty
                <div class="ota-mobile-agent__card">
                    <p class="ota-mobile-agent__note">No statements yet.</p>
                </div>
            @endforelse
        </section>
    </div>
@endsection
