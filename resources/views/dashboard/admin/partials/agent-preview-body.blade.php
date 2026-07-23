@php
    /**
     * Agent preview body partial — used by both the full Agents page render
     * and the AJAX preview endpoint (admin.agents.preview). Keeping the markup
     * here avoids duplicating the seven-section mini-profile inside JS.
     *
     * Inputs:
     *   $a (?array) — selected agent row payload (built by AdminSectionController::buildAgentRow)
     */
    $statusBadgeFor = static function (string $status): string {
        return match ($status) {
            'active' => 'badge-soft-success',
            'inactive' => 'badge-soft-neutral',
            default => 'badge-soft-warning',
        };
    };

    $bookingStatusBadge = static function (string $status): string {
        return match ($status) {
            'ticketed' => 'badge-soft-success',
            'confirmed' => 'badge-soft-info',
            'cancelled', 'refunded' => 'badge-soft-neutral',
            'draft' => 'badge-soft-neutral',
            default => 'badge-soft-warning',
        };
    };
@endphp

@if ($a)
    @php
        $st = $a['status'] ?? 'inactive';
        $stClass = $statusBadgeFor($st);
        $outstanding = (float) ($a['outstanding_balance'] ?? 0);
        $commissionPaid = (float) ($a['commission_paid'] ?? 0);
        $commissionPayable = (float) ($a['commission_payable'] ?? 0);
        $commissionPending = (float) ($a['commission_pending'] ?? 0);
        $userId = (int) ($a['user_id'] ?? 0);
        $hasUser = $userId > 0;
        $isActive = ($st === 'active');

        if ($outstanding > 0) {
            $commissionStatusLabel = 'Outstanding';
            $commissionStatusClass = 'is-warning';
        } elseif ($commissionPaid > 0) {
            $commissionStatusLabel = 'Up to date';
            $commissionStatusClass = 'is-success';
        } else {
            $commissionStatusLabel = 'No entries yet';
            $commissionStatusClass = '';
        }
    @endphp

    <h4 class="mb-1">{{ $a['agency_name'] }}</h4>
    <div class="text-secondary small mb-3">
        {{ $a['agent_code'] }}
        @if (($a['city'] ?? '—') !== '—') · {{ $a['city'] }} @endif
        <span class="badge {{ $stClass }} ms-1">{{ ucfirst($st) }}</span>
    </div>

    {{-- 1. Agent profile --}}
    <section class="preview-section" data-testid="ota-agents-preview-section-profile">
        <h6 class="preview-section-title"><i class="ti ti-id-badge"></i> Agent profile</h6>
        <div class="preview-block">
            <div class="preview-kv"><span><i class="ti ti-building me-1"></i> Agency</span><strong>{{ $a['agency_name'] }}</strong></div>
            <div class="preview-kv"><span><i class="ti ti-hash me-1"></i> {{ \App\Support\Identity\IdentityDisplay::labelLegacyAgentProfileCode() }}</span><strong><code>{{ $a['agent_code'] }}</code></strong></div>
            <div class="preview-kv">
                <span><i class="ti ti-circle-check me-1"></i> Status</span>
                <strong><span class="badge {{ $stClass }}" data-testid="ota-agents-preview-status">{{ ucfirst($st) }}</span></strong>
            </div>
            <div class="preview-kv"><span><i class="ti ti-map-pin me-1"></i> City</span><strong>{{ $a['city'] ?? '—' }}</strong></div>
            <div class="preview-kv" data-testid="ota-agents-preview-onboarded">
                <span><i class="ti ti-calendar-event me-1"></i> Onboarded</span>
                <strong>
                    {{ $a['onboarded_at'] ?? '—' }}
                    @if (($a['onboarded_human'] ?? '—') !== '—')
                        <span class="text-muted small">({{ $a['onboarded_human'] }})</span>
                    @endif
                </strong>
            </div>
        </div>
    </section>

    {{-- 2. Contact --}}
    <section class="preview-section" data-testid="ota-agents-preview-section-contact">
        <h6 class="preview-section-title"><i class="ti ti-address-book"></i> Contact</h6>
        <div class="preview-block">
            <div class="preview-kv"><span><i class="ti ti-user me-1"></i> Contact person</span><strong>{{ $a['contact_person'] }}</strong></div>
            <div class="preview-kv"><span><i class="ti ti-mail me-1"></i> Email</span><strong>{{ $a['email'] }}</strong></div>
            <div class="preview-kv"><span><i class="ti ti-phone me-1"></i> Phone</span><strong>{{ $a['phone'] }}</strong></div>
        </div>
    </section>

    {{-- 3. Commission setup --}}
    <section class="preview-section" data-testid="ota-agents-preview-section-commission-setup">
        <h6 class="preview-section-title"><i class="ti ti-percentage"></i> Commission setup</h6>
        <div class="preview-block" data-testid="ota-agents-preview-commission">
            <div class="preview-kv"><span>Commission rate</span><strong>{{ number_format((float) ($a['commission_percent'] ?? 0), 2) }}%</strong></div>
            <div class="preview-kv {{ $commissionStatusClass }}">
                <span>Commission status</span>
                <strong data-testid="ota-agents-preview-commission-status">{{ $commissionStatusLabel }}</strong>
            </div>
            <div class="preview-kv">
                <span>Next payout</span>
                <strong class="text-muted" data-testid="ota-agents-preview-next-payout">Not scheduled</strong>
            </div>
        </div>
    </section>

    {{-- 4. Performance --}}
    <section class="preview-section" data-testid="ota-agents-preview-section-performance">
        <h6 class="preview-section-title"><i class="ti ti-trending-up"></i> Performance</h6>
        <div class="preview-block" data-testid="ota-agents-preview-performance">
            <div class="preview-kv"><span>Total bookings</span><strong>{{ number_format((int) ($a['bookings_count'] ?? 0)) }}</strong></div>
            <div class="preview-kv"><span>Monthly sales</span><strong>Rs {{ number_format((int) round((float) ($a['monthly_sales'] ?? 0))) }}</strong></div>
            <div class="preview-kv {{ ($commissionPending + $commissionPayable) > 0 ? 'is-warning' : '' }}">
                <span>Pending commission</span>
                <strong>Rs {{ number_format((int) round($commissionPending + $commissionPayable)) }}</strong>
            </div>
            <div class="preview-kv {{ $commissionPaid > 0 ? 'is-success' : '' }}">
                <span>Paid commission</span>
                <strong>Rs {{ number_format((int) round($commissionPaid)) }}</strong>
            </div>
            <div class="preview-kv {{ $outstanding > 0 ? 'is-warning' : '' }}">
                <span>Balance</span>
                <strong>Rs {{ number_format((int) round($outstanding)) }}</strong>
            </div>
        </div>
    </section>

    {{-- 5. Recent bookings --}}
    <section class="preview-section" data-testid="ota-agents-preview-section-recent">
        <h6 class="preview-section-title"><i class="ti ti-list"></i> Recent bookings</h6>
        <div class="preview-block">
            <ul class="preview-recent" data-testid="ota-agents-preview-recent">
                @forelse(array_slice(($a['recent_bookings'] ?? []), 0, 3) as $booking)
                    <li>
                        <span>
                            <strong>{{ $booking['reference'] }}</strong>
                            <span class="recent-meta d-block">{{ $booking['route'] }}</span>
                        </span>
                        <span class="text-end">
                            <span class="badge {{ $bookingStatusBadge($booking['status'] ?? 'pending') }}">{{ ucfirst(str_replace('_', ' ', $booking['status'])) }}</span>
                            <span class="recent-meta d-block">Rs {{ number_format((int) round((float) ($booking['amount'] ?? 0))) }}</span>
                        </span>
                    </li>
                @empty
                    <li class="text-secondary"><span>No bookings yet.</span></li>
                @endforelse
            </ul>
        </div>
    </section>

    {{-- 6. Notes --}}
    <section class="preview-section" data-testid="ota-agents-preview-section-notes">
        <h6 class="preview-section-title"><i class="ti ti-notes"></i> Notes</h6>
        <div class="alert alert-secondary small mb-0 mt-0">
            {{ $a['notes'] ?? '—' }}
        </div>
    </section>

    {{-- 7. Actions --}}
    <section class="preview-section" data-testid="ota-agents-preview-section-actions">
        <h6 class="preview-section-title"><i class="ti ti-bolt"></i> Actions</h6>
        <div class="preview-actions">
            @if ($hasUser)
                <a href="{{ route('admin.users.show', ['user' => $userId]) }}" class="jp-btn jp-btn--primary" data-testid="ota-agents-action-open-profile">
                    <i class="ti ti-external-link"></i> Open full profile
                </a>
            @else
                <button type="button" class="jp-btn jp-btn--ghost btn-disabled" aria-disabled="true" title="No linked user account" data-testid="ota-agents-action-open-profile">
                    <i class="ti ti-external-link"></i> Open full profile
                    <span class="action-helper">No user</span>
                </button>
            @endif

            <a href="{{ route('admin.bookings', ['agent_customer' => 'agent', 'search' => $a['agent_code']]) }}" class="jp-btn jp-btn--ghost" data-testid="ota-agents-action-view-bookings">
                <i class="ti ti-clipboard-list"></i> View bookings
            </a>

            <a href="{{ route('admin.commissions.show', ['agent' => $a['id']]) }}#statement" class="jp-btn jp-btn--ghost" data-testid="ota-agents-action-generate-statement">
                <i class="ti ti-file-invoice"></i> Generate statement
            </a>

            <a href="{{ route('admin.commissions.show', ['agent' => $a['id']]) }}#payouts" class="jp-btn jp-btn--ghost" data-testid="ota-agents-action-record-payment">
                <i class="ti ti-coin"></i> Record commission payment
            </a>
        </div>

        <details class="mt-3" data-testid="ota-agents-planned-features">
            <summary class="small text-secondary">Planned features (not available yet)</summary>
            <div class="preview-actions mt-2">
                <button type="button" class="jp-btn jp-btn--sm jp-btn--ghost" disabled aria-disabled="true" title="Commission rate editing is coming soon — for now, edit on the agent record." data-testid="ota-agents-action-edit-commission">
                    <i class="ti ti-edit"></i> Edit commission
                </button>
                <button type="button" class="jp-btn jp-btn--sm jp-btn--ghost" disabled aria-disabled="true" title="Deactivate from preview is not wired — use the user profile status section." data-testid="ota-agents-action-deactivate">
                    <i class="ti ti-user-off"></i> Deactivate agent
                </button>
            </div>
        </details>
    </section>
@else
    <div class="agents-empty-state mb-0" data-testid="ota-agents-preview-empty">
        <i class="ti ti-user-search d-block mb-2 fs-2 text-muted"></i>
        <strong class="d-block mb-1">No agent selected</strong>
        Select an agent to view profile, commission, and performance.
    </div>
@endif

