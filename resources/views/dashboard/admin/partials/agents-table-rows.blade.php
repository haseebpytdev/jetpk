@php
    /**
     * Agents table body partial — used by both the full Agents page render
     * and the AJAX filter endpoint (admin.agents.data). Keeps row markup in
     * one place so the JS swap can never drift from the server-rendered form.
     *
     * Inputs:
     *   $agents (iterable<array>)  — filtered agent row payloads.
     *   $a      (?array)           — currently selected agent (for is-active
     *                                highlighting).
     *   $totalAgents (int)         — count for the current filter scope (matches
     *                                "of N agents" subtitle on the page).
     *   $hasFilters (bool)         — true when any user-controlled filter or
     *                                queue tab is narrowing the view; drives the
     *                                empty-state copy ("no agents match" vs
     *                                "no agents yet").
     */
    $statusBadgeFor = static fn (string $status): string => match ($status) {
        'active' => 'ota-bstat ota-bstat--ticketed',
        'inactive' => 'ota-bstat ota-bstat--muted',
        default => 'ota-bstat ota-bstat--pending',
    };
    $hasFilters = $hasFilters ?? false;
@endphp

@if (! $agents || (is_countable($agents) && count($agents) === 0))
    <div class="agents-empty-state m-3" data-testid="ota-agents-empty">
        @if ($hasFilters || ($totalAgents ?? 0) > 0)
            <i class="ti ti-filter-off d-block mb-2 fs-2 text-muted"></i>
            <strong class="d-block mb-1">No agents match your filters</strong>
            Try a different queue or clear your filters.
        @else
            <i class="ti ti-users d-block mb-2 fs-2 text-muted"></i>
            <strong class="d-block mb-1">No agents yet</strong>
            <p class="mb-3">Agents and partner agencies will appear here after approval or manual creation.</p>
            <a href="{{ route('admin.agent-applications.index') }}"
               class="jp-btn jp-btn--sm jp-btn--primary"
               data-testid="ota-agents-empty-review-applications">
                <i class="ti ti-clipboard-check me-1"></i> Review applications
            </a>
        @endif
    </div>
@else
    <table class="agents-table ota-admin-table" data-testid="ota-agents-table">
        <thead>
            <tr>
                <th class="col-agent">Agent</th>
                <th class="col-contact">Contact</th>
                <th class="col-status">Status</th>
                <th class="col-commission">Commission</th>
                <th class="col-bookings">Bookings</th>
                <th class="col-sales">Monthly sales</th>
            </tr>
        </thead>
        <tbody>
            @foreach ($agents as $row)
                @php
                    $userId = $row['user_id'] ?? null;
                    $openUrl = $userId
                        ? route('admin.users.show', ['user' => $userId])
                        : route('admin.commissions.show', ['agent' => $row['id']]);
                    $st = $row['status'] ?? 'inactive';
                    $stClass = $statusBadgeFor($st);
                    $bookingsCount = (int) ($row['bookings_count'] ?? 0);
                    $phone = trim((string) ($row['phone'] ?? ''));
                    $hasPhone = $phone !== '' && $phone !== '—';
                @endphp
                <tr class="ota-admin-click-row"
                    data-agent-row
                    data-agent-id="{{ $row['id'] }}"
                    data-href="{{ $openUrl }}"
                    tabindex="0"
                    role="link"
                    aria-label="Open agent {{ $row['agent_code'] }}">
                    <td class="col-agent" data-label="Agent">
                        <span class="agent-cell-code">{{ $row['agent_code'] }}</span>
                        <span class="agent-cell-agency">{{ $row['agency_name'] }}</span>
                    </td>
                    <td class="col-contact" data-label="Contact">
                        <div class="agent-cell-name">{{ $row['contact_person'] }}</div>
                        <div class="agent-cell-contactline">
                            <span class="agent-cell-email">{{ $row['email'] }}</span>
                            @if ($hasPhone)
                                <span class="agent-cell-sep" aria-hidden="true">·</span>
                                <span class="agent-cell-phone">{{ $row['phone'] }}</span>
                            @endif
                        </div>
                    </td>
                    <td class="col-status" data-label="Status">
                        <span class="{{ $stClass }}" data-testid="ota-agent-status-{{ $st }}">{{ ucfirst($st) }}</span>
                    </td>
                    <td class="col-commission col-numeric" data-label="Commission">{{ number_format((float) ($row['commission_percent'] ?? 0), 1) }}%</td>
                    <td class="col-bookings col-numeric" data-label="Bookings">{{ number_format($bookingsCount) }} {{ $bookingsCount === 1 ? 'booking' : 'bookings' }}</td>
                    <td class="col-sales col-numeric" data-label="Monthly sales">Rs {{ number_format((int) round((float) ($row['monthly_sales'] ?? 0))) }}</td>
                </tr>
            @endforeach
        </tbody>
    </table>
@endif

