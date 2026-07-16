@php
    $entries = $transaction->entries ?? collect();
@endphp

<div class="jp-card">
    <div class="jp-panel__header">
        <h2 class="jp-panel__title">Ledger entries</h2>
    </div>
    <div class="jp-table-wrap">
        <table class="jp-table" data-testid="accounting-ledger-entries">
            <thead>
                <tr>
                    <th>Account code</th>
                    <th>Account name</th>
                    <th>Type</th>
                    <th>Agency</th>
                    <th>Booking</th>
                    <th class="jp-table__num">Debit</th>
                    <th class="jp-table__num">Credit</th>
                    <th>Currency</th>
                    <th>Description</th>
                </tr>
            </thead>
            <tbody>
                @forelse ($entries as $entry)
                    <tr>
                        <td class="jp-mono">{{ $entry->account?->code ?? '—' }}</td>
                        <td>{{ $entry->account?->name ?? '—' }}</td>
                        <td class="jp-capitalize">{{ $entry->account?->account_type?->value ?? '—' }}</td>
                        <td>{{ $entry->agency_id ?? '—' }}</td>
                        <td>{{ $entry->booking_id ?? '—' }}</td>
                        <td class="jp-table__num jp-money">{{ (float) $entry->debit > 0 ? number_format((float) $entry->debit, 2) : '—' }}</td>
                        <td class="jp-table__num jp-money">{{ (float) $entry->credit > 0 ? number_format((float) $entry->credit, 2) : '—' }}</td>
                        <td>{{ $entry->currency }}</td>
                        <td>{{ $entry->description ?? '—' }}</td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="9" class="jp-table__empty">No entries.</td>
                    </tr>
                @endforelse
            </tbody>
            @if ($entries->isNotEmpty())
                <tfoot>
                    <tr class="jp-table__totals">
                        <td colspan="5">Totals</td>
                        <td class="jp-table__num jp-money">{{ number_format($totals['debit'] ?? 0, 2) }}</td>
                        <td class="jp-table__num jp-money">{{ number_format($totals['credit'] ?? 0, 2) }}</td>
                        <td colspan="2">
                            @if ($totals['balanced'] ?? false)
                                <span class="jp-badge jp-badge--success">Balanced</span>
                            @else
                                <span class="jp-badge jp-badge--danger">Unbalanced</span>
                            @endif
                        </td>
                    </tr>
                </tfoot>
            @endif
        </table>
    </div>
</div>
