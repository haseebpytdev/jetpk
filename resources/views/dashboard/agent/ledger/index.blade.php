@extends(client_layout('agent-portal', 'agent'))

@section('title', 'My Ledger')

@section('account_title', 'My Ledger')
@section('account_subtitle', 'Transaction history for your agency wallet (read-only).')

@section('account_actions')
    @if (Route::has('agent.wallet.show'))
        <a href="{{ route('agent.wallet.show') }}" class="ota-account-btn ota-account-btn--secondary">Back to wallet</a>
    @endif
@endsection

@section('account_content')
    @php
        use App\Enums\AgentWalletTransactionStatus;
        use App\Enums\AgentWalletTransactionType;

        use App\Support\Identity\IdentityDisplay;

        $ws = $summary ?? [];
        $currency = (string) ($ws['currency'] ?? 'PKR');
        $moneyPrefix = $currency === 'PKR' ? 'Rs ' : $currency.' ';
        $tz = $timezone ?? config('app.timezone');
        $agencyBal = $agencyBalance ?? [];
        $compactLedgerReference = static function (?string $value): string {
            $raw = trim((string) $value);
            if ($raw === '') {
                return '—';
            }

            if (preg_match('/^[A-Z0-9]{2,4}-(AG|AD|CU)-(.+)$/i', $raw, $matches) === 1) {
                return \Illuminate\Support\Str::limit($matches[2], 18);
            }

            if (preg_match('/^(AG|AD|CU)-(.+)$/i', $raw, $matches) === 1) {
                return \Illuminate\Support\Str::limit($matches[2], 18);
            }

            return \Illuminate\Support\Str::limit($raw, 18);
        };
    @endphp

    @if (! empty($agencyBal))
        <div class="ota-account-card ota-agent-ledger-summary mb-3" data-testid="agent-agency-balance">
            <div class="ota-account-card__body">
                <div class="ota-agent-ledger-summary__item">
                    <div class="text-secondary small">Agency balance (all wallets)</div>
                    <div class="fw-semibold">{{ $moneyPrefix }}{{ number_format((float) ($agencyBal['balance'] ?? 0), 2) }}</div>
                </div>
                <div class="ota-agent-ledger-summary__item">
                    <div class="text-secondary small">Pending deposits</div>
                    <div class="fw-semibold">{{ $moneyPrefix }}{{ number_format((float) ($agencyBal['pending_deposits'] ?? 0), 2) }}</div>
                </div>
            </div>
        </div>
    @endif

    <form method="get" action="{{ route('agent.ledger.index') }}" class="ota-account-toolbar ota-agent-ledger-filters mb-3" data-testid="agent-ledger-filters">
        <div class="ota-agent-ledger-filter-field">
            <label for="ledger_date_from">From</label>
            <input type="date" id="ledger_date_from" name="date_from" value="{{ $filters['date_from'] ?? '' }}" class="form-control form-control-sm" aria-label="From date">
        </div>
        <div class="ota-agent-ledger-filter-field">
            <label for="ledger_date_to">To</label>
            <input type="date" id="ledger_date_to" name="date_to" value="{{ $filters['date_to'] ?? '' }}" class="form-control form-control-sm" aria-label="To date">
        </div>
        <div class="ota-agent-ledger-filter-field">
            <label for="ledger_type">Type</label>
            <select id="ledger_type" name="type" class="form-select form-select-sm" aria-label="Type">
                <option value="">All types</option>
                @foreach (AgentWalletTransactionType::cases() as $case)
                    <option value="{{ $case->value }}" @selected(($filters['type'] ?? '') === $case->value)>{{ str_replace('_', ' ', $case->value) }}</option>
                @endforeach
            </select>
        </div>
        <div class="ota-agent-ledger-filter-field">
            <label for="ledger_status">Status</label>
            <select id="ledger_status" name="status" class="form-select form-select-sm" aria-label="Status">
                <option value="">All statuses</option>
                @foreach (AgentWalletTransactionStatus::cases() as $case)
                    <option value="{{ $case->value }}" @selected(($filters['status'] ?? '') === $case->value)>{{ str_replace('_', ' ', $case->value) }}</option>
                @endforeach
            </select>
        </div>
        <div class="ota-agent-ledger-filter-field ota-agent-ledger-filter-field--search">
            <label for="ledger_q">Search</label>
            <input type="search" id="ledger_q" name="q" value="{{ $filters['q'] ?? '' }}" class="form-control form-control-sm" placeholder="Reference or description" aria-label="Search">
        </div>
        <div class="ota-agent-ledger-filter-actions">
            <button type="submit" class="ota-account-btn ota-account-btn--secondary ota-account-btn--sm">Filter</button>
            @if (array_filter($filters ?? []))
                <a href="{{ route('agent.ledger.index') }}" class="ota-account-btn ota-account-btn--secondary ota-account-btn--sm">Clear</a>
            @endif
        </div>
    </form>

    <div class="ota-account-card">
        <div class="ota-account-card__body ota-account-card__body--flush">
            <div class="ota-ledger-table-wrap">
                <table class="ota-ledger-table mb-0" data-testid="agent-ledger-table">
                    <colgroup>
                        <col style="width: 14%">
                        <col style="width: 24%">
                        <col style="width: 24%">
                        <col style="width: 12%">
                        <col style="width: 12%">
                        <col style="width: 14%">
                    </colgroup>
                    <thead>
                        <tr>
                            <th>Date / Time</th>
                            <th>User</th>
                            <th>Reference</th>
                            <th class="text-end">Debit</th>
                            <th class="text-end">Credit</th>
                            <th class="text-end">Balance</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse ($transactions as $tx)
                            @php
                                $before = (float) $tx->balance_before;
                                $after = (float) $tx->balance_after;
                                $amount = (float) $tx->amount;
                                $debit = $after < $before ? $amount : null;
                                $credit = $after > $before ? $amount : null;
                                $localAt = $tx->created_at?->timezone($tz);
                                $related = null;
                                if ($tx->depositRequest) {
                                    $related = 'Deposit #'.$tx->depositRequest->id;
                                } elseif (is_array($tx->meta) && ! empty($tx->meta['booking_id'])) {
                                    $related = 'Booking #'.$tx->meta['booking_id'];
                                }
                                $performer = $tx->creator ?? $tx->approver ?? $tx->user;
                                $performerName = $performer?->name ?? 'System';
                                $performerCode = IdentityDisplay::userActorId($performer);
                                $compactReference = $compactLedgerReference($tx->reference);
                                $typeLabel = str_replace('_', ' ', $tx->type->value);
                                $statusLabel = str_replace('_', ' ', $tx->status->value);
                            @endphp
                            <tr class="ota-ledger-row" data-testid="agent-ledger-row-{{ $tx->id }}">
                                <td class="ota-ledger-date">
                                    <span class="ota-ledger-date__day">{{ $localAt?->format('d M y') ?? '—' }}</span>
                                    <span class="ota-ledger-date__time">{{ $localAt?->format('H:i') ?? '—' }}</span>
                                </td>
                                <td class="ota-ledger-user" data-testid="ledger-actor-{{ $tx->id }}">
                                    <span class="ota-ledger-user__name" title="{{ $performerName }}">{{ $performerName }}</span>
                                    @if ($performerCode)
                                        <span class="ota-ledger-user__code" title="{{ $performerCode }}">{{ $performerCode }}</span>
                                    @endif
                                </td>
                                <td class="ota-ledger-reference">
                                    <span title="{{ $tx->reference ?? '—' }}">{{ $compactReference }}</span>
                                </td>
                                <td class="text-end ota-ledger-money">
                                    @if ($debit !== null)
                                        {{ $moneyPrefix.number_format($debit, 2) }}
                                    @else
                                        <span class="ota-ledger-money-empty">—</span>
                                    @endif
                                </td>
                                <td class="text-end ota-ledger-money">
                                    @if ($credit !== null)
                                        {{ $moneyPrefix.number_format($credit, 2) }}
                                    @else
                                        <span class="ota-ledger-money-empty">—</span>
                                    @endif
                                </td>
                                <td class="text-end ota-ledger-money">{{ $moneyPrefix.number_format($after, 2) }}</td>
                            </tr>
                            <tr class="ota-ledger-detail-row">
                                <td colspan="6">
                                    <details class="ota-ledger-row-details">
                                        <summary aria-label="Show transaction details for {{ $tx->reference ?? 'ledger transaction '.$tx->id }}">View transaction details</summary>
                                        <div class="ota-ledger-detail-panel">
                                            <div><strong>Type:</strong> {{ $typeLabel }}</div>
                                            <div><strong>Full reference:</strong> <span title="{{ $tx->reference ?? '—' }}">{{ $tx->reference ?? '—' }}</span></div>
                                            <div><strong>Status:</strong> {{ $statusLabel }}</div>
                                            <div><strong>Description:</strong> <span>{{ $tx->description ?? '—' }}</span></div>
                                            @if ($related)
                                                <div><strong>Related:</strong> {{ $related }}</div>
                                            @endif
                                        </div>
                                    </details>
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="6">
                                    <div class="ota-account-empty ota-account-empty--compact py-4">
                                        <div class="ota-account-empty-icon" aria-hidden="true"><i class="ti ti-list-details"></i></div>
                                        <p class="ota-account-empty-title">No ledger entries</p>
                                        <p class="ota-account-empty-help">Transactions appear here when deposits are submitted or approved.</p>
                                    </div>
                                </td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
        @if ($transactions->hasPages())
            <div class="ota-account-card__footer">{{ $transactions->links() }}</div>
        @endif
    </div>
@endsection
