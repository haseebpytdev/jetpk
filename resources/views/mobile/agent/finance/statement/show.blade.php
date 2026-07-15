@extends(client_layout('mobile-app', 'mobile'))

@section('title', $pageTitle ?? 'Agency Statement')

@section('mobile_app_title', 'Finance Statement')

@section('content')
    @php
        $period = $statement['period'] ?? [];
        $movements = $statement['movements'] ?? [];
        $currency = (string) ($statement['currency'] ?? 'PKR');
        $moneyPrefix = $currency === 'PKR' ? 'Rs ' : $currency.' ';
        $exportEnabled = Route::has('agent.finance.statement.export');
    @endphp

    <div class="ota-mobile-agent" data-testid="ota-mobile-agent-finance-statement">
        <form method="get" action="{{ route('agent.finance.statement.show') }}" class="ota-mobile-agent__filters ota-mobile-agent__filters--form" data-testid="finance-statement-filters">
            <div class="ota-mobile-agent__field">
                <label class="ota-mobile-agent__label" for="date_from">From</label>
                <input type="date" name="date_from" id="date_from" class="ota-mobile-agent__input" value="{{ request('date_from', $period['from'] ?? '') }}">
            </div>
            <div class="ota-mobile-agent__field">
                <label class="ota-mobile-agent__label" for="date_to">To</label>
                <input type="date" name="date_to" id="date_to" class="ota-mobile-agent__input" value="{{ request('date_to', $period['to'] ?? '') }}">
            </div>
            <div class="ota-mobile-agent__actions">
                <button type="submit" class="ota-mobile-agent__btn ota-mobile-agent__btn--primary">Apply</button>
                @if ($exportEnabled)
                    <a href="{{ route('agent.finance.statement.export', request()->only(['date_from', 'date_to'])) }}" class="ota-mobile-agent__btn ota-mobile-agent__btn--secondary" data-testid="finance-statement-export">Export CSV</a>
                @endif
            </div>
            @if ($errors->any())
                <p class="ota-mobile-agent__error">{{ $errors->first() }}</p>
            @endif
        </form>

        <section class="ota-mobile-agent__card">
            <h2 class="ota-mobile-agent__card-title">{{ $agency->name }}</h2>
            <p class="ota-mobile-agent__muted">{{ $period['from'] ?? '' }} - {{ $period['to'] ?? '' }}</p>
            <dl class="ota-mobile-agent__meta ota-mobile-agent__meta--finance">
                <div>
                    <dt>Opening balance</dt>
                    <dd class="ota-mobile-agent__amount">{{ $moneyPrefix }}{{ number_format((float) ($statement['opening_balance'] ?? 0), 2) }}</dd>
                </div>
                <div>
                    <dt>Total credits</dt>
                    <dd class="ota-mobile-agent__amount">{{ $moneyPrefix }}{{ number_format((float) ($statement['total_credits'] ?? 0), 2) }}</dd>
                </div>
                <div>
                    <dt>Total debits</dt>
                    <dd class="ota-mobile-agent__amount">{{ $moneyPrefix }}{{ number_format((float) ($statement['total_debits'] ?? 0), 2) }}</dd>
                </div>
                <div>
                    <dt>Closing balance</dt>
                    <dd class="ota-mobile-agent__amount ota-mobile-agent__amount--total">{{ $moneyPrefix }}{{ number_format((float) ($statement['closing_balance'] ?? 0), 2) }}</dd>
                </div>
            </dl>
        </section>

        <section class="ota-mobile-agent__list" aria-label="Statement movements">
            <div class="ota-mobile-agent__card-head">
                <h2 class="ota-mobile-agent__card-title">Transactions</h2>
            </div>
            @forelse ($movements as $row)
                <article class="ota-mobile-agent__card">
                    <div class="ota-mobile-agent__card-head">
                        <span class="ota-mobile-agent__ref">{{ $row['date'] ?? '—' }}</span>
                        <span class="ota-mobile-agent__pill ota-mobile-agent__pill--muted">{{ str_replace('_', ' ', $row['type'] ?? '') }}</span>
                    </div>
                    <p class="ota-mobile-agent__note">{{ $row['description'] ?? '—' }}</p>
                    <p class="ota-mobile-agent__text-safe">Ref: {{ $row['reference'] ?? '—' }}</p>
                    @if (! empty($row['booking_reference']))
                        <p class="ota-mobile-agent__text-safe">Booking: {{ $row['booking_reference'] }}</p>
                    @endif
                    <p class="ota-mobile-agent__muted">Status: {{ $row['status'] ?? '—' }}</p>
                    <dl class="ota-mobile-agent__meta ota-mobile-agent__meta--finance">
                        <div>
                            <dt>Debit</dt>
                            <dd class="ota-mobile-agent__amount">{{ ($row['debit'] ?? 0) > 0 ? $moneyPrefix.number_format((float) $row['debit'], 2) : '—' }}</dd>
                        </div>
                        <div>
                            <dt>Credit</dt>
                            <dd class="ota-mobile-agent__amount">{{ ($row['credit'] ?? 0) > 0 ? $moneyPrefix.number_format((float) $row['credit'], 2) : '—' }}</dd>
                        </div>
                        <div>
                            <dt>Balance</dt>
                            <dd class="ota-mobile-agent__amount">{{ $moneyPrefix }}{{ number_format((float) ($row['running_balance'] ?? 0), 2) }}</dd>
                        </div>
                    </dl>
                </article>
            @empty
                <div class="ota-mobile-agent__empty" data-testid="finance-statement-empty">
                    <p class="ota-mobile-agent__empty-title">No statement movements</p>
                    <p class="ota-mobile-agent__empty-help">No transactions were found for this period.</p>
                </div>
            @endforelse
        </section>
    </div>
@endsection
