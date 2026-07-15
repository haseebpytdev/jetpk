@extends(client_layout('agent-portal', 'agent'))

@section('title', 'Commission statement')

@section('account_title')
    Statement {{ $statement->statement_number ?? '#'.$statement->id }}
@endsection

@section('account_subtitle')
    {{ $statement->period_start?->format('Y-m-d') ?? 'N/A' }} - {{ $statement->period_end?->format('Y-m-d') ?? 'N/A' }}
@endsection

@section('account_actions')
    <a href="{{ route('agent.commissions.index') }}" class="ota-account-btn ota-account-btn--secondary">Back to commissions</a>
@endsection

@section('account_content')
    <div class="ota-account-card mb-4">
        <div class="ota-account-card__body">
            <div class="ota-account-dl">
                <div class="ota-account-dl__row"><dt>Period</dt><dd>{{ $statement->period_start?->format('Y-m-d') ?? 'N/A' }} - {{ $statement->period_end?->format('Y-m-d') ?? 'N/A' }}</dd></div>
                <div class="ota-account-dl__row"><dt>Status</dt><dd class="text-capitalize">{{ $statement->status->value }}</dd></div>
                <div class="ota-account-dl__row"><dt>Closing balance</dt><dd>Rs {{ number_format((float) $statement->closing_balance, 2) }}</dd></div>
            </div>
        </div>
    </div>
    <div class="ota-account-card">
        <div class="ota-account-card__body ota-account-card__body--flush">
            <div class="ota-account-table-wrap">
                <table class="ota-account-table mb-0">
                    <thead><tr><th>Date</th><th>Type</th><th>Booking</th><th class="text-end">Amount</th></tr></thead>
                    <tbody>
                    @foreach($statement->entries as $entry)
                        <tr>
                            <td>{{ $entry->created_at?->format('j M Y, g:i A') }}</td>
                            <td>{{ $entry->type->value }}</td>
                            <td>{{ $entry->booking?->booking_reference ?? 'N/A' }}</td>
                            <td class="text-end">Rs {{ number_format((float) $entry->commission_amount, 2) }}</td>
                        </tr>
                    @endforeach
                    </tbody>
                </table>
            </div>
        </div>
    </div>
@endsection