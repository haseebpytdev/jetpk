@extends(client_layout('dashboard', 'admin'))

@section('title', $pageTitle ?? 'Archive duplicate wallets')

@section('page-header')
    <x-dashboard.section-header :title="$pageTitle ?? 'Archive duplicate wallets'" :subtitle="$pageSubtitle ?? ''">
        <x-slot name="actions">
            <a href="{{ route('admin.finance.wallet-audit.index', ['agency_id' => $agencyId]) }}" class="jp-btn jp-btn--ghost btn-sm">Back to audit</a>
        </x-slot>
    </x-dashboard.section-header>
@endsection

@section('content')
    @php
        $eligible = $preview['eligible'] ?? [];
        $blocked = $preview['blocked'] ?? [];
        $summary = $preview['summary'] ?? [];
    @endphp

    <div class="jp-alert jp-alert--warn py-2 mb-3" data-testid="wallet-archive-warning">
        <i class="ti ti-alert-triangle me-1"></i>
        This archives zero-balance duplicate wallets only. It does not delete wallets, transactions, deposits, or ledger entries.
    </div>

    <div class="row row-cards mb-4 g-3" data-testid="wallet-archive-preview-summary">
        <div class="col-6 col-md-4">
            <div class="card card-sm h-100">
                <div class="jp-card__body">
                    <div class="text-secondary small">Eligible to archive</div>
                    <div class="h3 mb-0">{{ $summary['eligible_count'] ?? 0 }}</div>
                </div>
            </div>
        </div>
        <div class="col-6 col-md-4">
            <div class="card card-sm h-100">
                <div class="jp-card__body">
                    <div class="text-secondary small">Blocked</div>
                    <div class="h3 mb-0">{{ $summary['blocked_count'] ?? 0 }}</div>
                </div>
            </div>
        </div>
    </div>

    <h3 class="mb-2">Eligible wallets</h3>
    <div class="jp-card">
        <div class="table-responsive ota-r-table-wrap">
            <table class="jp-table mb-0" data-testid="wallet-archive-eligible-table">
                <thead>
                    <tr>
                        <th>Wallet</th>
                        <th>Agent</th>
                        <th>Balance</th>
                        <th>Classification</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($eligible as $row)
                        <tr data-testid="wallet-archive-eligible-{{ $row['wallet_id'] }}">
                            <td>#{{ $row['wallet_id'] }}</td>
                            <td>{{ $row['agent_label'] }}</td>
                            <td>{{ $row['currency'] }} {{ number_format((float) $row['balance'], 2) }}</td>
                            <td>{{ $row['classification_label'] }}</td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="4" class="text-secondary text-center py-4">No eligible wallets for this agency.</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>

    <h3 class="mb-2">Blocked wallets</h3>
    <div class="jp-card">
        <div class="table-responsive ota-r-table-wrap">
            <table class="jp-table mb-0" data-testid="wallet-archive-blocked-table">
                <thead>
                    <tr>
                        <th>Wallet</th>
                        <th>Agent</th>
                        <th>Balance</th>
                        <th>Reason</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($blocked as $row)
                        <tr data-testid="wallet-archive-blocked-{{ $row['wallet_id'] }}">
                            <td>#{{ $row['wallet_id'] }}</td>
                            <td>{{ $row['agent_label'] }}</td>
                            <td>{{ $row['currency'] }} {{ number_format((float) $row['balance'], 2) }}</td>
                            <td class="small text-secondary">{{ $row['reason'] }}</td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="4" class="text-secondary text-center py-4">No blocked wallets listed.</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>

    @if (($summary['eligible_count'] ?? 0) > 0)
        <div class="jp-card">
            <div class="jp-card__head">
                <h3 class="jp-card__title mb-0">Confirm archive</h3>
            </div>
            <div class="jp-card__body">
                <form method="post" action="{{ route('admin.finance.wallet-audit.archive') }}" data-testid="wallet-archive-form">
                    @csrf
                    <input type="hidden" name="agency_id" value="{{ $agencyId }}">

                    <div class="mb-3">
                        <label class="jp-label" for="confirmation">Type <strong>ARCHIVE</strong> to confirm</label>
                        <input type="text" name="confirmation" id="confirmation" class="jp-control @error('confirmation') is-invalid @enderror"
                               value="{{ old('confirmation') }}" autocomplete="off" required>
                        @error('confirmation')
                            <div class="invalid-feedback">{{ $message }}</div>
                        @enderror
                    </div>

                    <div class="mb-3">
                        <label class="jp-label" for="reason">Archive reason (min. 10 characters)</label>
                        <textarea name="reason" id="reason" rows="3" class="jp-control @error('reason') is-invalid @enderror" required>{{ old('reason') }}</textarea>
                        @error('reason')
                            <div class="invalid-feedback">{{ $message }}</div>
                        @enderror
                    </div>

                    <button type="submit" class="jp-btn jp-btn--danger" data-testid="wallet-archive-submit">Archive eligible wallets</button>
                </form>
            </div>
        </div>
    @endif
@endsection
