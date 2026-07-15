@php
    $showRoutePrefix = $routePrefix ?? 'admin.accounting.ledger';
    $showAgency = ($scope ?? 'platform') === 'platform';
@endphp

<div class="jp-card">
    <div class="table-responsive">
        <table class="jp-table mb-0" data-testid="accounting-ledger-table">
            <thead>
                <tr>
                    <th>Posted</th>
                    @if ($showAgency)
                        <th>Agency</th>
                    @endif
                    <th>Ref</th>
                    <th>Type</th>
                    <th>Status</th>
                    <th>Booking</th>
                    <th>Source</th>
                    <th>{{ \App\Support\Identity\IdentityDisplay::labelPostedBy() }}</th>
                    <th class="text-end">Amount</th>
                    <th class="text-end">Debit</th>
                    <th class="text-end">Credit</th>
                    <th>Balanced</th>
                    <th></th>
                </tr>
            </thead>
            <tbody>
                @forelse ($transactions as $tx)
                    @php
                        $debit = round((float) ($tx->debit_total ?? 0), 2);
                        $credit = round((float) ($tx->credit_total ?? 0), 2);
                        $balanced = abs($debit - $credit) < 0.01;
                        $showUrl = Route::has($showRoutePrefix.'.show')
                            ? route($showRoutePrefix.'.show', $tx)
                            : null;
                    @endphp
                    <tr data-testid="accounting-ledger-row-{{ $tx->id }}">
                        <td>{{ $tx->posted_at?->format('Y-m-d H:i') ?? $tx->occurred_at?->format('Y-m-d H:i') ?? '—' }}</td>
                        @if ($showAgency)
                            <td>{{ $tx->agency?->name ?? '—' }}</td>
                        @endif
                        <td class="font-monospace small">{{ $tx->transaction_ref }}</td>
                        <td class="text-capitalize small">{{ str_replace('_', ' ', $tx->transaction_type->value) }}</td>
                        <td><x-dashboard.status-badge :status="$tx->status->value" /></td>
                        <td>
                            @if ($tx->booking)
                                <span class="font-monospace small">{{ $tx->booking->booking_reference }}</span>
                            @else
                                —
                            @endif
                        </td>
                        <td class="small">
                            @if ($tx->source_type)
                                {{ class_basename($tx->source_type) }}#{{ $tx->source_id }}
                            @else
                                —
                            @endif
                        </td>
                        <td class="font-monospace small" data-testid="accounting-ledger-actor-{{ $tx->id }}">{{ $tx->actor_identifier ?? '—' }}</td>
                        <td class="text-end">{{ $tx->currency }} {{ number_format((float) $tx->amount_total, 2) }}</td>
                        <td class="text-end">{{ number_format($debit, 2) }}</td>
                        <td class="text-end">{{ number_format($credit, 2) }}</td>
                        <td>
                            @if ($balanced)
                                <span class="badge bg-success-lt">Yes</span>
                            @else
                                <span class="badge bg-danger-lt">No</span>
                            @endif
                            @if ($tx->reversal_of_id)
                                <span class="badge bg-warning-lt">Reversal</span>
                            @elseif ($tx->reversals?->isNotEmpty())
                                <span class="badge bg-secondary-lt">Reversed</span>
                            @endif
                        </td>
                        <td>
                            @if ($showUrl)
                                <a href="{{ $showUrl }}" class="btn btn-sm btn-outline-primary">View</a>
                            @endif
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="{{ $showAgency ? 13 : 12 }}" class="text-center text-secondary py-5" data-testid="accounting-ledger-empty">
                            No double-entry ledger transactions yet. New verified finance events will appear here.
                        </td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>
    @if ($transactions->hasPages())
        <div class="card-footer">{{ $transactions->links() }}</div>
    @endif
</div>
