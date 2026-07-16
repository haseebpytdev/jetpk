@php
    $movements = $statement['movements'] ?? [];
    $currency = (string) ($statement['currency'] ?? 'PKR');
    $moneyPrefix = $currency === 'PKR' ? 'Rs ' : $currency.' ';
@endphp
<div class="jp-card" data-testid="finance-statement-movements">
    <div class="jp-panel__header">
        <h2 class="jp-panel__title">Statement movements</h2>
    </div>
    @if (count($movements) === 0)
        <p class="jp-empty" data-testid="finance-statement-empty">No statement movements found for this period.</p>
    @else
        <div class="jp-table-wrap">
            <table class="jp-table">
                <thead>
                    <tr>
                        <th>Date</th>
                        <th>Type</th>
                        <th>Description</th>
                        <th>Reference</th>
                        <th>Booking</th>
                        <th class="jp-table__num">Debit</th>
                        <th class="jp-table__num">Credit</th>
                        <th class="jp-table__num">Balance</th>
                        <th>Status</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach ($movements as $row)
                        <tr>
                            <td class="jp-nowrap">{{ $row['date'] ?? '—' }}</td>
                            <td><span class="jp-badge jp-badge--info">{{ str_replace('_', ' ', $row['type'] ?? '') }}</span></td>
                            <td>{{ $row['description'] ?? '—' }}</td>
                            <td>{{ $row['reference'] ?? '—' }}</td>
                            <td>{{ $row['booking_reference'] ?? '—' }}</td>
                            <td class="jp-table__num jp-money">{{ ($row['debit'] ?? 0) > 0 ? $moneyPrefix.number_format((float) $row['debit'], 2) : '—' }}</td>
                            <td class="jp-table__num jp-money">{{ ($row['credit'] ?? 0) > 0 ? $moneyPrefix.number_format((float) $row['credit'], 2) : '—' }}</td>
                            <td class="jp-table__num jp-money">{{ $moneyPrefix }}{{ number_format((float) ($row['running_balance'] ?? 0), 2) }}</td>
                            <td>{{ $row['status'] ?? '—' }}</td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    @endif
</div>
