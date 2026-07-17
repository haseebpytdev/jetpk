{{-- JP-PORTAL-3 TASK 5 · Agent / Agent Staff wallet ledger — index (JetPK theme)
     Resolved by client_view('ledger.index', 'agent'); dashboard.agent.ledger.index remains the
     fallback for standalone mode is off\.
     Route gate: agent.permission:LedgerView + platform.module:agent_ledger.
     Controller also enforces Gate::authorize('viewLedger', $agent).
     Mobile branch: mobile.agent.ledger.index — see Task 12.

     *** DEBIT/CREDIT SEMANTICS — DO NOT REFACTOR ***
     Debit and credit are DERIVED from the balance delta, not stored:
         $debit  = $after < $before ? $amount : null;
         $credit = $after > $before ? $amount : null;
     Both expressions are reproduced character-for-character. A transaction where
     $after === $before yields NEITHER a debit nor a credit (both render '—'). That is legacy
     behaviour and is preserved; "simplifying" this inverts or erases money movement.

     PRESERVED EXACTLY:
       • controller vars: $summary, $agencyBalance, $transactions (paginator, withQueryString),
         $timezone, $filters
       • $moneyPrefix derived from $ws['currency'] ?? 'PKR' (currency-aware, like wallet)
       • $tz = $timezone ?? config('app.timezone'); timestamps rendered via
         $tx->created_at?->timezone($tz) — agency timezone, NOT app default
       • $compactLedgerReference closure reproduced verbatim, including both regexes
         (/^[A-Z0-9]{2,4}-(AG|AD|CU)-(.+)$/i and /^(AG|AD|CU)-(.+)$/i) and Str::limit(...,18).
         The FULL reference is preserved in the title attribute and the details panel — the
         compaction is display-only and never loses data.
       • agency balance panel gated by ! empty($agencyBal): Agency balance (all wallets),
         Pending deposits
       • filter form: method="get", fields date_from, date_to, type, status, q — names unchanged
         so query parameters and ->withQueryString() pagination keep working
       • type/status options from AgentWalletTransactionType::cases() /
         AgentWalletTransactionStatus::cases() with str_replace('_',' ',...) labels
       • "Clear" link gated by array_filter($filters ?? [])
       • columns: Date/Time, User, Reference, Debit, Credit, Balance
       • $performer = $tx->creator ?? $tx->approver ?? $tx->user  (exact fallback chain);
         name ?? 'System'; IdentityDisplay::userActorId($performer)
       • $related: 'Deposit #id' from depositRequest, else 'Booking #id' from meta['booking_id']
       • per-row <details> panel: Type, Full reference, Status, Description, Related
       • pagination: $transactions->links() gated by hasPages()
       • data-testids: agent-agency-balance, agent-ledger-filters, agent-ledger-table,
         agent-ledger-row-{id}, ledger-actor-{id}
     Money cells are right-aligned, non-wrapping, and never truncated. The mobile card list
     carries Debit, Credit AND Balance explicitly — no financial column is dropped at any width.
--}}
@extends(client_layout('agent-portal', 'agent'))

@section('title', 'My Ledger')

@section('account_title', 'My Ledger')
@section('account_subtitle', 'Transaction history for your agency wallet (read-only).')

@section('account_actions')
    @if (Route::has('agent.wallet.show'))
        <a href="{{ route('agent.wallet.show') }}" class="jp-btn jp-btn--ghost">Back to wallet</a>
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

    <x-dashboard.breadcrumbs :items="[
        ['label' => 'Dashboard', 'href' => client_route('agent.dashboard')],
        ['label' => 'My Ledger'],
    ]" />

    @if (! empty($agencyBal))
        <x-jp.card class="jp-portal__panel" data-testid="agent-agency-balance">
            <div class="jp-portal__summary-row">
                <div class="jp-portal__summary-item">
                    <p class="jp-portal__summary-label">Agency balance (all wallets)</p>
                    <p class="jp-money jp-money--strong">{{ $moneyPrefix }}{{ number_format((float) ($agencyBal['balance'] ?? 0), 2) }}</p>
                </div>
                <div class="jp-portal__summary-item">
                    <p class="jp-portal__summary-label">Pending deposits</p>
                    <p class="jp-money jp-money--strong">{{ $moneyPrefix }}{{ number_format((float) ($agencyBal['pending_deposits'] ?? 0), 2) }}</p>
                </div>
            </div>
        </x-jp.card>
    @endif

    <form method="get" action="{{ route('agent.ledger.index') }}" class="jp-filters" data-testid="agent-ledger-filters">
        <div class="jp-filters__field">
            <label class="jp-label" for="ledger_date_from">From</label>
            <input type="date" id="ledger_date_from" name="date_from" value="{{ $filters['date_from'] ?? '' }}" class="jp-input jp-input--sm" aria-label="From date">
        </div>
        <div class="jp-filters__field">
            <label class="jp-label" for="ledger_date_to">To</label>
            <input type="date" id="ledger_date_to" name="date_to" value="{{ $filters['date_to'] ?? '' }}" class="jp-input jp-input--sm" aria-label="To date">
        </div>
        <div class="jp-filters__field">
            <label class="jp-label" for="ledger_type">Type</label>
            <select id="ledger_type" name="type" class="jp-select jp-select--sm" aria-label="Type">
                <option value="">All types</option>
                @foreach (AgentWalletTransactionType::cases() as $case)
                    <option value="{{ $case->value }}" @selected(($filters['type'] ?? '') === $case->value)>{{ str_replace('_', ' ', $case->value) }}</option>
                @endforeach
            </select>
        </div>
        <div class="jp-filters__field">
            <label class="jp-label" for="ledger_status">Status</label>
            <select id="ledger_status" name="status" class="jp-select jp-select--sm" aria-label="Status">
                <option value="">All statuses</option>
                @foreach (AgentWalletTransactionStatus::cases() as $case)
                    <option value="{{ $case->value }}" @selected(($filters['status'] ?? '') === $case->value)>{{ str_replace('_', ' ', $case->value) }}</option>
                @endforeach
            </select>
        </div>
        <div class="jp-filters__field jp-filters__field--search">
            <label class="jp-label" for="ledger_q">Search</label>
            <input type="search" id="ledger_q" name="q" value="{{ $filters['q'] ?? '' }}" class="jp-input jp-input--sm" placeholder="Reference or description" aria-label="Search">
        </div>
        <div class="jp-filters__actions">
            <button type="submit" class="jp-btn jp-btn--ghost jp-btn--sm">Filter</button>
            @if (array_filter($filters ?? []))
                <a href="{{ route('agent.ledger.index') }}" class="jp-btn jp-btn--ghost jp-btn--sm">Clear</a>
            @endif
        </div>
    </form>

    <x-jp.card class="jp-portal__panel jp-portal__panel--flush">
        {{-- Desktop: full six-column ledger. --}}
        <div class="jp-table-wrap jp-table-wrap--desktop">
            <table class="jp-table jp-table--ledger" data-testid="agent-ledger-table">
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
                        <th scope="col">Date / Time</th>
                        <th scope="col">User</th>
                        <th scope="col">Reference</th>
                        <th scope="col" class="jp-table__cell--end">Debit</th>
                        <th scope="col" class="jp-table__cell--end">Credit</th>
                        <th scope="col" class="jp-table__cell--end">Balance</th>
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
                        <tr class="jp-ledger__row" data-testid="agent-ledger-row-{{ $tx->id }}">
                            <td class="jp-ledger__date">
                                <span class="jp-ledger__date-day">{{ $localAt?->format('d M y') ?? '—' }}</span>
                                <span class="jp-ledger__date-time">{{ $localAt?->format('H:i') ?? '—' }}</span>
                            </td>
                            <td class="jp-ledger__user" data-testid="ledger-actor-{{ $tx->id }}">
                                <span class="jp-ledger__user-name" title="{{ $performerName }}">{{ $performerName }}</span>
                                @if ($performerCode)
                                    <span class="jp-ledger__user-code" title="{{ $performerCode }}">{{ $performerCode }}</span>
                                @endif
                            </td>
                            <td class="jp-ledger__reference">
                                <span title="{{ $tx->reference ?? '—' }}">{{ $compactReference }}</span>
                            </td>
                            <td class="jp-table__cell--end jp-money">
                                @if ($debit !== null)
                                    {{ $moneyPrefix.number_format($debit, 2) }}
                                @else
                                    <span class="jp-money--empty">—</span>
                                @endif
                            </td>
                            <td class="jp-table__cell--end jp-money">
                                @if ($credit !== null)
                                    {{ $moneyPrefix.number_format($credit, 2) }}
                                @else
                                    <span class="jp-money--empty">—</span>
                                @endif
                            </td>
                            <td class="jp-table__cell--end jp-money">{{ $moneyPrefix.number_format($after, 2) }}</td>
                        </tr>
                        <tr class="jp-ledger__detail-row">
                            <td colspan="6">
                                <details class="jp-ledger__details">
                                    <summary aria-label="Show transaction details for {{ $tx->reference ?? 'ledger transaction '.$tx->id }}">View transaction details</summary>
                                    <div class="jp-ledger__detail-panel">
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
                                <div class="jp-empty">
                                    <span class="jp-empty__icon" aria-hidden="true"><x-jp.icon name="list-details" /></span>
                                    <p class="jp-empty__title">No ledger entries</p>
                                    <p class="jp-empty__help">Transactions appear here when deposits are submitted or approved.</p>
                                </div>
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        {{-- Mobile: Debit, Credit and Balance are ALL carried explicitly. --}}
        <div class="jp-portal__list jp-portal__list--mobile">
            @forelse ($transactions as $tx)
                @php
                    $before = (float) $tx->balance_before;
                    $after = (float) $tx->balance_after;
                    $amount = (float) $tx->amount;
                    $debit = $after < $before ? $amount : null;
                    $credit = $after > $before ? $amount : null;
                    $localAt = $tx->created_at?->timezone($tz);
                    $performer = $tx->creator ?? $tx->approver ?? $tx->user;
                    $performerName = $performer?->name ?? 'System';
                @endphp
                <article class="jp-portal__list-card" data-testid="agent-ledger-mobile-{{ $tx->id }}">
                    <div class="jp-portal__list-card-head">
                        <span class="jp-portal__list-card-ref" title="{{ $tx->reference ?? '—' }}">{{ $compactLedgerReference($tx->reference) }}</span>
                        <span class="jp-badge jp-badge--neutral">{{ str_replace('_', ' ', $tx->status->value) }}</span>
                    </div>
                    <div class="jp-portal__list-card-meta">
                        <span>{{ $localAt?->format('d M y, H:i') ?? '—' }}</span>
                        <span>User: {{ $performerName }}</span>
                        <span>Type: {{ str_replace('_', ' ', $tx->type->value) }}</span>
                        <span>Debit: <span class="jp-money">{{ $debit !== null ? $moneyPrefix.number_format($debit, 2) : '—' }}</span></span>
                        <span>Credit: <span class="jp-money">{{ $credit !== null ? $moneyPrefix.number_format($credit, 2) : '—' }}</span></span>
                        <span>Balance: <span class="jp-money">{{ $moneyPrefix.number_format($after, 2) }}</span></span>
                        <span>Description: {{ $tx->description ?? '—' }}</span>
                    </div>
                </article>
            @empty
                <div class="jp-empty">
                    <p class="jp-empty__title">No ledger entries</p>
                    <p class="jp-empty__help">Transactions appear here when deposits are submitted or approved.</p>
                </div>
            @endforelse
        </div>

        @if ($transactions->hasPages())
            <div class="jp-portal__pagination">{{ $transactions->links() }}</div>
        @endif
    </x-jp.card>
@endsection
