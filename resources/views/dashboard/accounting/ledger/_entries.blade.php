@php
    $entries = $transaction->entries ?? collect();
@endphp

<div class="card mt-3">
    <div class="card-header">
        <h3 class="card-title">Ledger entries</h3>
    </div>
    <div class="table-responsive">
        <table class="table table-vcenter card-table mb-0" data-testid="accounting-ledger-entries">
            <thead>
                <tr>
                    <th>Account code</th>
                    <th>Account name</th>
                    <th>Type</th>
                    <th>Agency</th>
                    <th>Booking</th>
                    <th class="text-end">Debit</th>
                    <th class="text-end">Credit</th>
                    <th>Currency</th>
                    <th>Description</th>
                </tr>
            </thead>
            <tbody>
                @forelse ($entries as $entry)
                    <tr>
                        <td class="font-monospace small">{{ $entry->account?->code ?? '—' }}</td>
                        <td>{{ $entry->account?->name ?? '—' }}</td>
                        <td class="text-capitalize">{{ $entry->account?->account_type?->value ?? '—' }}</td>
                        <td>{{ $entry->agency_id ?? '—' }}</td>
                        <td>{{ $entry->booking_id ?? '—' }}</td>
                        <td class="text-end">{{ (float) $entry->debit > 0 ? number_format((float) $entry->debit, 2) : '—' }}</td>
                        <td class="text-end">{{ (float) $entry->credit > 0 ? number_format((float) $entry->credit, 2) : '—' }}</td>
                        <td>{{ $entry->currency }}</td>
                        <td class="small">{{ $entry->description ?? '—' }}</td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="9" class="text-center text-secondary py-4">No entries.</td>
                    </tr>
                @endforelse
            </tbody>
            @if ($entries->isNotEmpty())
                <tfoot>
                    <tr class="fw-bold">
                        <td colspan="5">Totals</td>
                        <td class="text-end">{{ number_format($totals['debit'] ?? 0, 2) }}</td>
                        <td class="text-end">{{ number_format($totals['credit'] ?? 0, 2) }}</td>
                        <td colspan="2">
                            @if ($totals['balanced'] ?? false)
                                <span class="badge bg-success-lt">Balanced</span>
                            @else
                                <span class="badge bg-danger-lt">Unbalanced</span>
                            @endif
                        </td>
                    </tr>
                </tfoot>
            @endif
        </table>
    </div>
</div>
