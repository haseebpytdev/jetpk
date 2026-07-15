@extends(client_layout('dashboard', 'admin'))

@section('title', $pageTitle ?? 'Wallet Audit')

@section('page-header')
    <x-dashboard.section-header :title="$pageTitle ?? 'Wallet Audit'" :subtitle="$pageSubtitle ?? ''">
        <x-slot name="actions">
            @if (! empty($canArchive))
                <a href="{{ route('admin.finance.wallet-audit.archive-preview', ['agency_id' => $filters['agency_id'] ?? null]) }}"
                   class="jp-btn jp-btn--danger btn-sm @if (empty($filters['agency_id'])) disabled @endif"
                   data-testid="wallet-audit-archive-candidates"
                   @if (empty($filters['agency_id'])) aria-disabled="true" tabindex="-1" @endif>
                    Archive candidates
                </a>
            @endif
            <a href="{{ route('admin.finance.wallet-audit.export', request()->only(['agency_id', 'only_duplicates', 'only_candidates'])) }}" class="jp-btn jp-btn--outline btn-sm" data-testid="wallet-audit-export-csv">
                <i class="ti ti-download me-1"></i> Export Wallet Audit CSV
            </a>
            <a href="{{ route('admin.finance.dashboard') }}" class="jp-btn jp-btn--ghost btn-sm">Finance Dashboard</a>
        </x-slot>
    </x-dashboard.section-header>
@endsection

@section('content')
    @php
        $summary = $report['summary'] ?? [];
        $wallets = $report['wallets'] ?? [];
        $agencies = $report['agencies'] ?? [];
        $filters = $filters ?? [];
        $classificationBadge = fn (string $value): string => match ($value) {
            'canonical' => 'bg-primary-lt',
            'cleanup_candidate' => 'bg-success-lt',
            'archived_duplicate' => 'bg-secondary-lt',
            'historical_active' => 'bg-warning-lt',
            'review_required' => 'bg-danger-lt',
            default => 'bg-secondary-lt',
        };
    @endphp

    @if (session('status') === 'wallet-archive-complete')
        <div class="jp-alert jp-alert--success py-2 mb-3" data-testid="wallet-archive-flash">
            Archive complete: {{ (int) session('wallet_archive_archived', 0) }} archived,
            {{ (int) session('wallet_archive_skipped', 0) }} skipped.
        </div>
    @endif

    <div class="jp-alert jp-alert--info py-2 mb-3" data-testid="wallet-audit-readonly-notice">
        <i class="ti ti-info-circle me-1"></i>
        Audit is read-only. Archive zero-balance duplicate cleanup candidates via
        <strong>Archive candidates</strong> (filter by agency first) or
        <code>php artisan agent-wallets:archive-candidates --dry-run</code>.
    </div>

    <div class="card card-sm mb-3">
        <div class="card-body py-2">
            <form method="get" action="{{ route('admin.finance.wallet-audit.index') }}" class="jp-form-grid jp-form-grid--filter">
                <div class="col-auto">
                    <label class="jp-label small mb-0" for="agency_id">Agency ID</label>
                    <input type="number" name="agency_id" id="agency_id" class="jp-control jp-control-sm"
                           value="{{ $filters['agency_id'] ?? '' }}" min="1" placeholder="All">
                </div>
                <div class="col-auto">
                    <div class="form-check mt-4">
                        <input class="form-check-input" type="checkbox" name="only_duplicates" value="1" id="only_duplicates"
                               @checked($filters['only_duplicates'] ?? false)>
                        <label class="form-check-label small" for="only_duplicates">Only duplicates</label>
                    </div>
                </div>
                <div class="col-auto">
                    <div class="form-check mt-4">
                        <input class="form-check-input" type="checkbox" name="only_candidates" value="1" id="only_candidates"
                               @checked($filters['only_candidates'] ?? false)>
                        <label class="form-check-label small" for="only_candidates">Only cleanup candidates</label>
                    </div>
                </div>
                <div class="col-auto">
                    <button type="submit" class="jp-btn jp-btn--sm jp-btn--primary mt-4">Apply filters</button>
                    <a href="{{ route('admin.finance.wallet-audit.index') }}" class="jp-btn jp-btn--sm jp-btn--ghost mt-4">Reset</a>
                </div>
            </form>
        </div>
    </div>

    <div class="row row-cards mb-4 g-3" data-testid="wallet-audit-summary-cards">
        @foreach ([
            ['label' => 'Total agencies', 'value' => $summary['total_agencies'] ?? 0],
            ['label' => 'Agencies with duplicate wallets', 'value' => $summary['agencies_with_multiple_wallets'] ?? 0, 'testid' => 'wallet-audit-dupe-agencies'],
            ['label' => 'Canonical wallets', 'value' => $summary['canonical_wallets'] ?? 0],
            ['label' => 'Cleanup candidates', 'value' => $summary['cleanup_candidates'] ?? 0, 'testid' => 'wallet-audit-cleanup-candidates'],
            ['label' => 'Review required', 'value' => $summary['review_required'] ?? 0, 'testid' => 'wallet-audit-review-required'],
            ['label' => 'Historical active duplicates', 'value' => $summary['historical_active_duplicates'] ?? 0],
        ] as $card)
            <div class="col-6 col-md-4 col-xl-2">
                <div class="card card-sm h-100" @if (! empty($card['testid'])) data-testid="{{ $card['testid'] }}" @endif>
                    <div class="jp-card__body">
                        <div class="text-secondary small">{{ $card['label'] }}</div>
                        <div class="h3 mb-0">{{ number_format((int) $card['value']) }}</div>
                    </div>
                </div>
            </div>
        @endforeach
    </div>

    <h3 class="mb-2">Duplicate wallet detail</h3>
    <div class="jp-card">
        <div class="table-responsive ota-r-table-wrap">
            <table class="jp-table mb-0" data-testid="wallet-audit-wallets-table">
                <thead>
                    <tr>
                        <th>Agency</th>
                        <th>Wallet</th>
                        <th>Canonical</th>
                        <th>Balance</th>
                        <th>Tx</th>
                        <th>Deposits</th>
                        <th>Ledger refs</th>
                        <th>Last movement</th>
                        <th>Classification</th>
                        <th>Recommendation</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($wallets as $row)
                        <tr data-testid="wallet-audit-row-{{ $row['wallet_id'] }}">
                            <td>
                                <div class="fw-medium">{{ $row['agency_name'] }}</div>
                                <div class="text-secondary small">#{{ $row['agency_id'] }}</div>
                            </td>
                            <td>
                                #{{ $row['wallet_id'] }}
                                <div class="text-secondary small">{{ $row['agent_label'] }}</div>
                            </td>
                            <td>
                                @if ($row['is_canonical'])
                                    <span class="badge bg-primary-lt">Canonical</span>
                                @else
                                    <span class="text-secondary">—</span>
                                @endif
                            </td>
                            <td>{{ $row['currency'] }} {{ number_format((float) $row['balance'], 2) }}</td>
                            <td>{{ $row['transaction_count'] }}</td>
                            <td>{{ $row['deposit_request_count'] }}</td>
                            <td>{{ $row['ledger_reference_count'] }}</td>
                            <td class="text-secondary small">{{ $row['last_movement_at'] ?? '—' }}</td>
                            <td>
                                <span class="badge {{ $classificationBadge($row['classification']) }}">
                                    {{ $row['classification_label'] }}
                                </span>
                                @if (($row['status'] ?? '') === 'archived')
                                    <div class="text-secondary small mt-1">Status: archived</div>
                                @endif
                            </td>
                            <td class="small text-secondary">
                                {{ $row['recommendation'] }}
                                @if (! empty($row['archive_metadata']))
                                    <div class="mt-1">
                                        Former cleanup candidate.
                                        Archived {{ $row['archive_metadata']['archived_at'] ?? '—' }}
                                        by {{ $row['archive_metadata']['archived_by'] ?? '—' }}.
                                    </div>
                                @endif
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="10" class="text-secondary text-center py-4">No wallets match the current filters.</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>

    <h3 class="mb-2">Agency grouped view</h3>
    <div class="jp-card">
        <div class="table-responsive ota-r-table-wrap">
            <table class="jp-table mb-0" data-testid="wallet-audit-agencies-table">
                <thead>
                    <tr>
                        <th>Agency</th>
                        <th>Canonical wallet</th>
                        <th>Wallets</th>
                        <th>Duplicates</th>
                        <th>Total balance</th>
                        <th>Candidates</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($agencies as $row)
                        <tr data-testid="wallet-audit-agency-{{ $row['agency_id'] }}">
                            <td>
                                <div class="fw-medium">{{ $row['agency_name'] }}</div>
                                <div class="text-secondary small">#{{ $row['agency_id'] }}</div>
                            </td>
                            <td>#{{ $row['canonical_wallet_id'] ?? '—' }}</td>
                            <td>{{ $row['wallet_count'] }}</td>
                            <td>{{ $row['duplicate_count'] }}</td>
                            <td>{{ $row['currency'] }} {{ number_format((float) $row['total_balance'], 2) }}</td>
                            <td>{{ $row['cleanup_candidate_count'] }}</td>
                            <td class="text-nowrap">
                                @if (! empty($canArchive) && ($row['cleanup_candidate_count'] ?? 0) > 0)
                                    <a href="{{ route('admin.finance.wallet-audit.archive-preview', ['agency_id' => $row['agency_id']]) }}" class="btn btn-sm btn-outline-danger">Archive</a>
                                @endif
                                <a href="{{ route('admin.agencies.show', ['agency' => $row['agency_id'], 'tab' => 'wallet']) }}" class="jp-btn jp-btn--sm jp-btn--ghost">Agency</a>
                                <a href="{{ route('admin.finance.statements.show', $row['agency_id']) }}" class="jp-btn jp-btn--sm jp-btn--ghost">Statement</a>
                                <a href="{{ route('admin.finance.dashboard') }}" class="jp-btn jp-btn--sm jp-btn--ghost">Dashboard</a>
                                <a href="{{ route('admin.finance.adjustments.create', ['agency_id' => $row['agency_id']]) }}" class="jp-btn jp-btn--sm jp-btn--outline">Adjust</a>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="7" class="text-secondary text-center py-4">No agencies match the current filters.</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
@endsection
