@php
    use App\Support\Agencies\AgencyScopeResolver;
    use App\Support\Identity\IdentityDisplay;

    $before = (float) $tx->balance_before;
    $after = (float) $tx->balance_after;
    $amount = (float) $tx->amount;
    $debit = $after < $before ? $amount : null;
    $credit = $after > $before ? $amount : null;
    $actorUser = $tx->creator ?? $tx->approver ?? $tx->user;
    $actorCode = IdentityDisplay::userActorId($actorUser);
    $agencyName = $tx->agency ? AgencyScopeResolver::displayName($tx->agency) : '—';
    $bookingRef = $tx->relationLoaded('booking') && $tx->booking
        ? $tx->booking->reference
        : (is_array($tx->meta) && ! empty($tx->meta['booking_id']) ? 'Booking #'.$tx->meta['booking_id'] : null);
    $currency = (string) ($tx->wallet?->currency ?? $summary['currency'] ?? 'PKR');
    $moneyPrefix = $currency === 'PKR' ? 'Rs ' : $currency.' ';
    $showRoute = Route::has(($routePrefix ?? 'admin.ledger').'.show')
        ? route(($routePrefix ?? 'admin.ledger').'.show', $tx)
        : null;
@endphp
<tr data-testid="ledger-row-{{ $tx->id }}">
    <td class="text-nowrap">{{ $tx->created_at?->format('j M Y, g:i A') ?? '—' }}</td>
    <td>{{ $agencyName }}</td>
    <td data-testid="ledger-actor-{{ $tx->id }}">
        <div>{{ $actorUser?->name ?? 'System' }}</div>
        <div class="font-monospace small text-secondary">{{ $actorCode }}</div>
    </td>
    <td>{{ $tx->reference ?? '—' }}</td>
    <td>{{ $bookingRef ?? '—' }}</td>
    <td class="text-capitalize">{{ str_replace('_', ' ', $tx->type->value) }}</td>
    <td class="text-end">{{ $debit !== null ? $moneyPrefix.number_format($debit, 2) : '—' }}</td>
    <td class="text-end">{{ $credit !== null ? $moneyPrefix.number_format($credit, 2) : '—' }}</td>
    <td><x-dashboard.status-badge :status="$tx->status->value" /></td>
    <td class="text-end">
        @if ($showRoute)
            <a href="{{ $showRoute }}" class="jp-btn jp-btn--sm jp-btn--ghost">View</a>
        @endif
    </td>
</tr>
