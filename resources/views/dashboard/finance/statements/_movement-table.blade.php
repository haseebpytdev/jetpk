@php
    $movements = $statement['movements'] ?? [];
    $currency = (string) ($statement['currency'] ?? 'PKR');
    $moneyPrefix = $currency === 'PKR' ? 'Rs ' : $currency.' ';
@endphp
<div class="card mb-4" data-testid="finance-statement-movements">
    <div class="card-header">
        <h3 class="card-title mb-0">Statement movements</h3>
    </div>
    <div class="card-body p-0">
        @if (count($movements) === 0)
            <p class="text-secondary p-4 mb-0" data-testid="finance-statement-empty">No statement movements found for this period.</p>
        @else
            <div class="table-responsive">
                <table class="table table-vcenter table-striped mb-0">
                    <thead>
                        <tr>
                            <th>Date</th>
                            <th>Type</th>
                            <th>Description</th>
                            <th>Reference</th>
                            <th>Booking</th>
                            <th class="text-end">Debit</th>
                            <th class="text-end">Credit</th>
                            <th class="text-end">Balance</th>
                            <th>Status</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($movements as $row)
                            <tr>
                                <td class="text-nowrap">{{ $row['date'] ?? '—' }}</td>
                                <td><span class="badge bg-azure-lt">{{ str_replace('_', ' ', $row['type'] ?? '') }}</span></td>
                                <td class="ota-r-text-safe">{{ $row['description'] ?? '—' }}</td>
                                <td class="ota-r-text-safe">{{ $row['reference'] ?? '—' }}</td>
                                <td>{{ $row['booking_reference'] ?? '—' }}</td>
                                <td class="text-end">{{ ($row['debit'] ?? 0) > 0 ? $moneyPrefix.number_format((float) $row['debit'], 2) : '—' }}</td>
                                <td class="text-end">{{ ($row['credit'] ?? 0) > 0 ? $moneyPrefix.number_format((float) $row['credit'], 2) : '—' }}</td>
                                <td class="text-end">{{ $moneyPrefix }}{{ number_format((float) ($row['running_balance'] ?? 0), 2) }}</td>
                                <td>{{ $row['status'] ?? '—' }}</td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        @endif
    </div>
</div>
