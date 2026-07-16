{{-- JP-PORTAL-3 TASK 5 · Agent commissions — index (JetPK theme)
     Resolved by client_view('commissions.index', 'agent'); dashboard.agent.commissions.index
     remains the fallback for default/Parwaaz clients and is NOT modified.

     *** AGENT ADMIN ONLY *** — route gate is `agent.admin` (NOT an agent.permission).
     Controller also enforces Gate::authorize('view', $agent). Agent Staff must never reach this
     page, and no commission figure may surface on any Agent Staff surface. This view therefore
     carries NO in-view permission branch: the route middleware is the gate, exactly as legacy.
     Do not add a permission check here that would imply Staff may see a degraded version.
     Mobile branch: mobile.agent.commissions.index — see Task 12.

     PRESERVED EXACTLY:
       • controller vars: $agent, $entries, $statements, $balance, $pending, $approved, $paid
       • KPI set and order: Current balance, Pending, Approved, Paid — all 'Rs ' hardcoded, 2dp
       • NOTE: $balance is passed to number_format() WITHOUT a (float) cast in legacy
         (number_format($balance, 2)), while $pending/$approved/$paid are pre-cast to float in the
         controller. Reproduced verbatim — adding a cast would be a silent behaviour change.
       • Entries columns: Date, Type, Status, Booking, Amount
       • entry date 'j M y, H:i'; type/status ->value, capitalised; booking_reference ?? 'N/A'
       • Statements columns: Statement, Period, Status, Closing balance, (action)
       • statement_number ?? 'N/A'; period 'Y-m-d' - 'Y-m-d' with 'N/A' fallbacks
       • closing_balance number_format((float) ..., 2) with 'Rs '
       • route: agent.commissions.statements.show
       • both empty states, with their exact copy
     Amounts right-aligned, non-wrapping. Mobile cards carry every column.
--}}
@extends(client_layout('agent-portal', 'agent'))

@section('title', 'My commissions')

@section('account_title', 'My commissions')
@section('account_subtitle', 'Track pending, approved, paid commissions and statements.')

@section('account_content')
    <x-dashboard.breadcrumbs :items="[
        ['label' => 'Dashboard', 'href' => client_route('agent.dashboard')],
        ['label' => 'My commissions'],
    ]" />

    <div class="jp-kpi-grid">
        <div class="jp-kpi jp-kpi--emerald">
            <p class="jp-kpi__label">Current balance</p>
            <p class="jp-kpi__value jp-money">Rs {{ number_format($balance, 2) }}</p>
        </div>
        <div class="jp-kpi jp-kpi--amber">
            <p class="jp-kpi__label">Pending</p>
            <p class="jp-kpi__value jp-money">Rs {{ number_format($pending, 2) }}</p>
        </div>
        <div class="jp-kpi">
            <p class="jp-kpi__label">Approved</p>
            <p class="jp-kpi__value jp-money">Rs {{ number_format($approved, 2) }}</p>
        </div>
        <div class="jp-kpi jp-kpi--violet">
            <p class="jp-kpi__label">Paid</p>
            <p class="jp-kpi__value jp-money">Rs {{ number_format($paid, 2) }}</p>
        </div>
    </div>

    <x-jp.card class="jp-portal__panel jp-portal__panel--flush">
        <div class="jp-portal__panel-head">
            <div>
                <h2 class="jp-portal__panel-title">Entries</h2>
                <p class="jp-portal__panel-lead">Commission line items on your account.</p>
            </div>
        </div>

        <div class="jp-table-wrap jp-table-wrap--desktop">
            <table class="jp-table jp-table--finance">
                <thead>
                    <tr>
                        <th scope="col">Date</th>
                        <th scope="col">Type</th>
                        <th scope="col">Status</th>
                        <th scope="col">Booking</th>
                        <th scope="col" class="jp-table__cell--end">Amount</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($entries as $entry)
                        <tr>
                            <td data-label="Date" class="jp-table__cell--nowrap">{{ $entry->created_at?->format('j M y, H:i') }}</td>
                            <td data-label="Type" class="jp-table__cell--capitalize">{{ $entry->type->value }}</td>
                            <td data-label="Status" class="jp-table__cell--capitalize">{{ $entry->status->value }}</td>
                            <td data-label="Booking" class="jp-portal__cell-ref">{{ $entry->booking?->booking_reference ?? 'N/A' }}</td>
                            <td data-label="Amount" class="jp-table__cell--end jp-money">Rs {{ number_format((float) $entry->commission_amount, 2) }}</td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="5">
                                <div class="jp-empty">
                                    <p class="jp-empty__title">No commission entries yet.</p>
                                    <p class="jp-empty__help">Commission line items will appear here.</p>
                                </div>
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        <div class="jp-portal__list jp-portal__list--mobile">
            @forelse ($entries as $entry)
                <article class="jp-portal__list-card">
                    <div class="jp-portal__list-card-head">
                        <span class="jp-portal__list-card-ref jp-table__cell--capitalize">{{ $entry->type->value }}</span>
                        <span class="jp-badge jp-badge--neutral jp-table__cell--capitalize">{{ $entry->status->value }}</span>
                    </div>
                    <div class="jp-portal__list-card-meta">
                        <span>{{ $entry->created_at?->format('j M y, H:i') }}</span>
                        <span>Booking: {{ $entry->booking?->booking_reference ?? 'N/A' }}</span>
                        <span>Amount: <span class="jp-money">Rs {{ number_format((float) $entry->commission_amount, 2) }}</span></span>
                    </div>
                </article>
            @empty
                <div class="jp-empty">
                    <p class="jp-empty__title">No commission entries yet.</p>
                    <p class="jp-empty__help">Commission line items will appear here.</p>
                </div>
            @endforelse
        </div>
    </x-jp.card>

    <x-jp.card class="jp-portal__panel jp-portal__panel--flush">
        <div class="jp-portal__panel-head">
            <div>
                <h2 class="jp-portal__panel-title">Statements</h2>
                <p class="jp-portal__panel-lead">Periodic commission statements.</p>
            </div>
        </div>

        <div class="jp-table-wrap jp-table-wrap--desktop">
            <table class="jp-table jp-table--finance">
                <thead>
                    <tr>
                        <th scope="col">Statement</th>
                        <th scope="col">Period</th>
                        <th scope="col">Status</th>
                        <th scope="col" class="jp-table__cell--end">Closing balance</th>
                        <th scope="col" class="jp-table__cell--end"></th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($statements as $statement)
                        <tr>
                            <td data-label="Statement" class="jp-portal__cell-ref">{{ $statement->statement_number ?? 'N/A' }}</td>
                            <td data-label="Period" class="jp-table__cell--nowrap">{{ $statement->period_start?->format('Y-m-d') ?? 'N/A' }} - {{ $statement->period_end?->format('Y-m-d') ?? 'N/A' }}</td>
                            <td data-label="Status" class="jp-table__cell--capitalize">{{ $statement->status->value }}</td>
                            <td data-label="Closing balance" class="jp-table__cell--end jp-money">Rs {{ number_format((float) $statement->closing_balance, 2) }}</td>
                            <td class="jp-table__cell--end">
                                <a class="jp-btn jp-btn--ghost jp-btn--sm" href="{{ route('agent.commissions.statements.show', $statement) }}">View</a>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="5">
                                <div class="jp-empty">
                                    <p class="jp-empty__title">No statements yet.</p>
                                    <p class="jp-empty__help">Periodic commission statements will appear here.</p>
                                </div>
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        <div class="jp-portal__list jp-portal__list--mobile">
            @forelse ($statements as $statement)
                <article class="jp-portal__list-card">
                    <div class="jp-portal__list-card-head">
                        <span class="jp-portal__list-card-ref">{{ $statement->statement_number ?? 'N/A' }}</span>
                        <span class="jp-badge jp-badge--neutral jp-table__cell--capitalize">{{ $statement->status->value }}</span>
                    </div>
                    <div class="jp-portal__list-card-meta">
                        <span>{{ $statement->period_start?->format('Y-m-d') ?? 'N/A' }} - {{ $statement->period_end?->format('Y-m-d') ?? 'N/A' }}</span>
                        <span>Closing balance: <span class="jp-money">Rs {{ number_format((float) $statement->closing_balance, 2) }}</span></span>
                    </div>
                    <div class="jp-portal__list-card-actions">
                        <a class="jp-btn jp-btn--primary jp-btn--sm" href="{{ route('agent.commissions.statements.show', $statement) }}">View</a>
                    </div>
                </article>
            @empty
                <div class="jp-empty">
                    <p class="jp-empty__title">No statements yet.</p>
                    <p class="jp-empty__help">Periodic commission statements will appear here.</p>
                </div>
            @endforelse
        </div>
    </x-jp.card>
@endsection
