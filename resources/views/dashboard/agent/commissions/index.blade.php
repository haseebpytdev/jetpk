@extends(client_layout('agent-portal', 'agent'))

@section('title', 'My commissions')

@section('account_title', 'My commissions')
@section('account_subtitle', 'Track pending, approved, paid commissions and statements.')

@section('account_content')
    <div class="ota-account-grid ota-account-grid--kpis ota-agent-kpi-strip ota-agent-commission-kpis mb-4">
        <div class="ota-account-kpi ota-account-kpi--emerald">
            <div class="ota-account-kpi__label">Current balance</div>
            <div class="ota-account-kpi__value">Rs {{ number_format($balance, 2) }}</div>
        </div>
        <div class="ota-account-kpi ota-account-kpi--amber">
            <div class="ota-account-kpi__label">Pending</div>
            <div class="ota-account-kpi__value">Rs {{ number_format($pending, 2) }}</div>
        </div>
        <div class="ota-account-kpi">
            <div class="ota-account-kpi__label">Approved</div>
            <div class="ota-account-kpi__value">Rs {{ number_format($approved, 2) }}</div>
        </div>
        <div class="ota-account-kpi ota-account-kpi--violet">
            <div class="ota-account-kpi__label">Paid</div>
            <div class="ota-account-kpi__value">Rs {{ number_format($paid, 2) }}</div>
        </div>
    </div>

    <div class="ota-account-card mb-4">
        <div class="ota-account-card__head">
            <div>
                <h2 class="ota-account-card__title">Entries</h2>
                <p class="ota-account-card__lead">Commission line items on your account.</p>
            </div>
        </div>
        <div class="ota-account-card__body ota-account-card__body--flush">
            <div class="ota-account-table-wrap">
                <table class="ota-account-table ota-agent-finance-table mb-0">
                    <thead>
                        <tr><th>Date</th><th>Type</th><th>Status</th><th>Booking</th><th class="text-end">Amount</th></tr>
                    </thead>
                    <tbody>
                    @forelse($entries as $entry)
                        <tr>
                            <td class="text-nowrap">{{ $entry->created_at?->format('j M y, H:i') }}</td>
                            <td class="text-capitalize">{{ $entry->type->value }}</td>
                            <td class="text-capitalize">{{ $entry->status->value }}</td>
                            <td class="ota-agent-cell-ref">{{ $entry->booking?->booking_reference ?? 'N/A' }}</td>
                            <td class="text-end ota-agent-money">Rs {{ number_format((float) $entry->commission_amount, 2) }}</td>
                        </tr>
                    @empty
                        <tr><td colspan="5"><div class="ota-account-empty ota-account-empty--compact"><p class="ota-account-empty-title">No commission entries yet.</p><p class="ota-account-empty-help">Commission line items will appear here.</p></div></td></tr>
                    @endforelse
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    <div class="ota-account-card">
        <div class="ota-account-card__head">
            <div>
                <h2 class="ota-account-card__title">Statements</h2>
                <p class="ota-account-card__lead">Periodic commission statements.</p>
            </div>
        </div>
        <div class="ota-account-card__body ota-account-card__body--flush">
            <div class="ota-account-table-wrap">
                <table class="ota-account-table ota-agent-finance-table mb-0">
                    <thead>
                        <tr><th>Statement</th><th>Period</th><th>Status</th><th class="text-end">Closing balance</th><th class="text-end"></th></tr>
                    </thead>
                    <tbody>
                    @forelse($statements as $statement)
                        <tr>
                            <td class="ota-agent-cell-ref">{{ $statement->statement_number ?? 'N/A' }}</td>
                            <td class="text-nowrap">{{ $statement->period_start?->format('Y-m-d') ?? 'N/A' }} - {{ $statement->period_end?->format('Y-m-d') ?? 'N/A' }}</td>
                            <td class="text-capitalize">{{ $statement->status->value }}</td>
                            <td class="text-end ota-agent-money">Rs {{ number_format((float) $statement->closing_balance, 2) }}</td>
                            <td class="text-end"><a class="ota-account-btn ota-account-btn--secondary ota-account-btn--sm" href="{{ route('agent.commissions.statements.show', $statement) }}">View</a></td>
                        </tr>
                    @empty
                        <tr><td colspan="5"><div class="ota-account-empty ota-account-empty--compact"><p class="ota-account-empty-title">No statements yet.</p><p class="ota-account-empty-help">Periodic commission statements will appear here.</p></div></td></tr>
                    @endforelse
                    </tbody>
                </table>
            </div>
        </div>
    </div>
@endsection