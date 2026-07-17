{{-- JP-PORTAL-3 TASK 5 · Agent commission statement — show (JetPK theme)
     Resolved by client_view('commissions.statement', 'agent'); dashboard.agent.commissions.statement
     remains the fallback for standalone mode is off\.

     *** AGENT ADMIN ONLY *** — route gate is `agent.admin`; controller enforces
     Gate::authorize('view', $statement). Agent Staff must never reach this page.
     Mobile branch: mobile.agent.commissions.statement — see Task 12.

     PRESERVED EXACTLY:
       • controller var: $statement (loaded with agent.user, entries.booking)
       • account_title: "Statement {{ statement_number ?? '#'.id }}"  (section body, not a string arg)
       • account_subtitle: period_start 'Y-m-d' - period_end 'Y-m-d' with 'N/A' fallbacks
       • facts: Period, Status (capitalised ->value), Closing balance ('Rs ', 2dp, float cast)
       • Entries columns: Date, Type, Booking, Amount
       • entry date 'j M Y, g:i A'  (NOTE: different format from commissions/index's 'j M y, H:i'
         — legacy inconsistency, reproduced deliberately)
       • entry type ->value rendered RAW here (legacy does NOT capitalise on this page, unlike
         index) — reproduced verbatim
       • booking_reference ?? 'N/A'; amount 'Rs ' number_format((float) ..., 2)
       • @foreach with NO empty state — legacy has none on this page; one is NOT invented
       • back link -> agent.commissions.index
--}}
@extends(client_layout('agent-portal', 'agent'))

@section('title', 'Commission statement')

@section('account_title')
    Statement {{ $statement->statement_number ?? '#'.$statement->id }}
@endsection

@section('account_subtitle')
    {{ $statement->period_start?->format('Y-m-d') ?? 'N/A' }} - {{ $statement->period_end?->format('Y-m-d') ?? 'N/A' }}
@endsection

@section('account_actions')
    <a href="{{ route('agent.commissions.index') }}" class="jp-btn jp-btn--ghost">Back to commissions</a>
@endsection

@section('account_content')
    <x-dashboard.breadcrumbs :items="[
        ['label' => 'Dashboard', 'href' => client_route('agent.dashboard')],
        ['label' => 'My commissions', 'href' => route('agent.commissions.index')],
        ['label' => $statement->statement_number ?? '#'.$statement->id],
    ]" />

    <x-jp.card class="jp-portal__panel">
        <dl class="jp-portal__facts">
            <dt>Period</dt>
            <dd>{{ $statement->period_start?->format('Y-m-d') ?? 'N/A' }} - {{ $statement->period_end?->format('Y-m-d') ?? 'N/A' }}</dd>

            <dt>Status</dt>
            <dd class="jp-table__cell--capitalize">{{ $statement->status->value }}</dd>

            <dt>Closing balance</dt>
            <dd class="jp-money">Rs {{ number_format((float) $statement->closing_balance, 2) }}</dd>
        </dl>
    </x-jp.card>

    <x-jp.card class="jp-portal__panel jp-portal__panel--flush">
        <div class="jp-table-wrap jp-table-wrap--desktop">
            <table class="jp-table jp-table--finance">
                <thead>
                    <tr>
                        <th scope="col">Date</th>
                        <th scope="col">Type</th>
                        <th scope="col">Booking</th>
                        <th scope="col" class="jp-table__cell--end">Amount</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach ($statement->entries as $entry)
                        <tr>
                            <td data-label="Date" class="jp-table__cell--nowrap">{{ $entry->created_at?->format('j M Y, g:i A') }}</td>
                            <td data-label="Type">{{ $entry->type->value }}</td>
                            <td data-label="Booking" class="jp-portal__cell-ref">{{ $entry->booking?->booking_reference ?? 'N/A' }}</td>
                            <td data-label="Amount" class="jp-table__cell--end jp-money">Rs {{ number_format((float) $entry->commission_amount, 2) }}</td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>

        <div class="jp-portal__list jp-portal__list--mobile">
            @foreach ($statement->entries as $entry)
                <article class="jp-portal__list-card">
                    <div class="jp-portal__list-card-head">
                        <span class="jp-portal__list-card-ref">{{ $entry->type->value }}</span>
                        <span class="jp-money">Rs {{ number_format((float) $entry->commission_amount, 2) }}</span>
                    </div>
                    <div class="jp-portal__list-card-meta">
                        <span>{{ $entry->created_at?->format('j M Y, g:i A') }}</span>
                        <span>Booking: {{ $entry->booking?->booking_reference ?? 'N/A' }}</span>
                    </div>
                </article>
            @endforeach
        </div>
    </x-jp.card>
@endsection
