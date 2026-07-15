@extends(client_layout('dashboard', 'admin'))

@section('title', 'New manual adjustment')

@section('page-header')
    <x-dashboard.section-header title="New manual adjustment" subtitle="Credit or debit an agency wallet with a ledger entry.">
        <x-slot name="actions">
            <a href="{{ route('admin.finance.adjustments.index') }}" class="jp-btn jp-btn--ghost jp-btn--sm">Back to list</a>
        </x-slot>
    </x-dashboard.section-header>
@endsection

@section('content')
    <div class="jp-alert jp-alert--warn mb-4" data-testid="finance-adjustment-warning">
        <strong>Warning:</strong> Manual adjustments change agency wallet balance and create accounting ledger entries. Use only for verified corrections.
    </div>

    @if ($errors->any())
        <div class="jp-alert jp-alert--danger">
            <ul class="mb-0">
                @foreach ($errors->all() as $error)
                    <li>{{ $error }}</li>
                @endforeach
            </ul>
        </div>
    @endif

    <form method="post" action="{{ route('admin.finance.adjustments.store') }}"
          class="jp-card"
          data-testid="finance-adjustment-create-form"
          x-data="{ confirmed: {{ old('confirmation') ? 'true' : 'false' }} }">
        @csrf
        <input type="hidden" name="idempotency_key" value="{{ $idempotencyKey }}" data-testid="finance-adjustment-idempotency-key">

        <div class="jp-card__body">
            <div class="row g-3">
                <div class="col-md-6">
                    <label class="jp-label" for="agency_id">Agency</label>
                    <select name="agency_id" id="agency_id" class="jp-control" required
                            onchange="if (this.value) { window.location = '{{ route('admin.finance.adjustments.create') }}?agency_id=' + this.value; }">
                        <option value="">Select agency…</option>
                        @foreach ($agencies as $agency)
                            <option value="{{ $agency->id }}" @selected((int) old('agency_id', $selectedAgencyId) === (int) $agency->id)>{{ $agency->name }}</option>
                        @endforeach
                    </select>
                </div>

                @if ($selectedAgencyId > 0 && $canonicalSummary)
                    <div class="col-12">
                        @if ($canonicalSummary['has_duplicate_wallets'])
                            <div class="jp-alert jp-alert--info mb-3" data-testid="finance-adjustment-duplicate-wallet-warning">
                                This agency has historical duplicate wallets. Future adjustments will use canonical wallet #{{ $canonicalSummary['wallet_id'] }}.
                            </div>
                        @endif
                    </div>
                    <div class="col-md-6">
                        <label class="jp-label">Operational wallet</label>
                        <input type="hidden" name="wallet_id" value="{{ $canonicalSummary['wallet_id'] }}">
                        <p class="jp-control-plaintext mb-0" data-testid="finance-adjustment-canonical-wallet">
                            Wallet #{{ $canonicalSummary['wallet_id'] }} — {{ $canonicalSummary['owner_label'] }}
                            (balance {{ $canonicalSummary['currency'] }} {{ number_format((float) $canonicalSummary['balance'], 2) }})
                        </p>
                    </div>
                @endif

                <div class="col-md-6">
                    <label class="jp-label">Adjustment type</label>
                    <div class="d-flex flex-wrap gap-3">
                        <label class="jp-toggle">
                            <input type="radio" name="adjustment_type" value="manual_credit" @checked(old('adjustment_type', 'manual_credit') === 'manual_credit') required>
                            <span>Credit (increase balance)</span>
                        </label>
                        <label class="jp-toggle">
                            <input type="radio" name="adjustment_type" value="manual_debit" @checked(old('adjustment_type') === 'manual_debit') required>
                            <span>Debit (decrease balance)</span>
                        </label>
                    </div>
                </div>

                <div class="col-md-6">
                    <label class="jp-label" for="amount">Amount (PKR)</label>
                    <input type="number" name="amount" id="amount" class="jp-control" step="0.01" min="0.01" required value="{{ old('amount') }}">
                </div>

                <div class="col-md-6">
                    <label class="jp-label" for="adjustment_reason">Reason</label>
                    <select name="adjustment_reason" id="adjustment_reason" class="jp-control" required>
                        <option value="">Select reason…</option>
                        @foreach ($reasonCategories as $reason)
                            <option value="{{ $reason }}" @selected(old('adjustment_reason') === $reason)>{{ str_replace('_', ' ', ucfirst($reason)) }}</option>
                        @endforeach
                    </select>
                </div>

                <div class="col-12">
                    <label class="jp-label" for="adjustment_note">Note (optional)</label>
                    <textarea name="adjustment_note" id="adjustment_note" class="jp-control jp-control--textarea" rows="3">{{ old('adjustment_note') }}</textarea>
                </div>

                <div class="col-12">
                    <label class="jp-toggle">
                        <input type="checkbox" name="confirmation" value="1"
                               x-model="confirmed" @checked(old('confirmation'))>
                        <span>I confirm this adjustment is authorized and documented.</span>
                    </label>
                </div>
            </div>
        </div>

        <div class="jp-card__footer">
            <div class="jp-action-bar jp-action-bar--end">
                <button type="submit" class="jp-btn jp-btn--primary" :disabled="!confirmed" data-testid="finance-adjustment-submit">Post adjustment</button>
            </div>
        </div>
    </form>
@endsection
