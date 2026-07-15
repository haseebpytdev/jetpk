@extends(client_layout('agent-portal', 'agent'))

@section('title', 'Request deposit')

@section('account_title', 'Request deposit')
@section('account_subtitle', 'Submit amount and payment proof for finance review.')

@section('account_actions')
    <a href="{{ route('agent.deposits.index') }}" class="ota-account-btn ota-account-btn--secondary">Back to deposits</a>
@endsection

@section('account_content')
    @php $ws = $summary ?? []; @endphp

    <div class="ota-account-grid ota-account-grid--kpis mb-4">
        <div class="ota-account-kpi">
            <div class="ota-account-kpi__label">Current balance</div>
            <div class="ota-account-kpi__value">Rs {{ number_format((float) ($ws['balance'] ?? 0), 2) }}</div>
        </div>
    </div>

    <div class="ota-account-card ota-account-form-card" data-testid="agent-deposit-form-card">
        <div class="ota-account-card__body">
            <form method="post" action="{{ route('agent.deposits.store') }}" enctype="multipart/form-data" data-testid="agent-deposit-form">
                @csrf
                <div class="mb-3">
                    <label class="form-label" for="amount">Amount (PKR)</label>
                    <input type="number" name="amount" id="amount" class="form-control @error('amount') is-invalid @enderror" value="{{ old('amount') }}" min="1" step="0.01" required>
                    @error('amount')<div class="invalid-feedback">{{ $message }}</div>@enderror
                </div>
                <div class="mb-3">
                    <label class="form-label" for="payment_method">Payment method</label>
                    <input type="text" name="payment_method" id="payment_method" class="form-control" value="{{ old('payment_method') }}" placeholder="e.g. Bank transfer, JazzCash">
                </div>
                <div class="mb-3">
                    <label class="form-label" for="reference">Reference / transaction ID</label>
                    <input type="text" name="reference" id="reference" class="form-control" value="{{ old('reference') }}">
                </div>
                <div class="mb-3">
                    <label class="form-label" for="agent_note">Note (optional)</label>
                    <textarea name="agent_note" id="agent_note" class="form-control" rows="3">{{ old('agent_note') }}</textarea>
                </div>
                <div class="mb-3">
                    <label class="form-label" for="proof">Proof file (optional)</label>
                    <input type="file" name="proof" id="proof" class="form-control @error('proof') is-invalid @enderror" accept=".jpg,.jpeg,.png,.pdf,.webp">
                    <div class="form-text">JPG, PNG, PDF, or WebP up to 5 MB.</div>
                    @error('proof')<div class="invalid-feedback">{{ $message }}</div>@enderror
                </div>
                <button type="submit" class="ota-account-btn ota-account-btn--primary">Submit deposit request</button>
            </form>
        </div>
    </div>
@endsection
