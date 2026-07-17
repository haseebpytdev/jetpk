{{-- JP-PORTAL-3 TASK 5 · Agent / Agent Staff deposits — create (JetPK theme)
     Resolved by client_view('deposits.create', 'agent'); dashboard.agent.deposits.create remains
     the fallback for standalone mode is off\.
     Route gate: agent.permission:PaymentsUpload + platform.module:agent_deposits.
     Controller also enforces Gate::authorize('create', AgentDepositRequest::class) and
     PlatformModuleEnforcer::ensureAgentDepositsEnabled() on store.
     Mobile branch: mobile.agent.deposits.create — see Task 12.

     PRESERVED EXACTLY:
       • controller var: $summary  ($ws = $summary ?? [])
       • single KPI: "Current balance" — Rs hardcoded, as legacy (see deposits/index note)
       • form: method="post" action=route('agent.deposits.store'),
         enctype="multipart/form-data"  (REQUIRED — the proof file upload breaks without it),
         @csrf, NO @method spoof
       • field names: amount, payment_method, reference, agent_note, proof
       • amount: type="number" min="1" step="0.01" required + old('amount') + @error
       • payment_method: optional, placeholder "e.g. Bank transfer, JazzCash", old()
         — NOTE: legacy has NO @error block for payment_method or reference; not invented here
       • agent_note: textarea rows="3", optional, old()
       • proof: type="file" accept=".jpg,.jpeg,.png,.pdf,.webp", optional, @error,
         help text "JPG, PNG, PDF, or WebP up to 5 MB."
       • submit label "Submit deposit request"
       • data-testids: agent-deposit-form-card, agent-deposit-form
     Validation rules live in StoreAgentDepositRequest and are untouched.
--}}
@extends(client_layout('agent-portal', 'agent'))

@section('title', 'Request deposit')

@section('account_title', 'Request deposit')
@section('account_subtitle', 'Submit amount and payment proof for finance review.')

@section('account_actions')
    <a href="{{ route('agent.deposits.index') }}" class="jp-btn jp-btn--ghost">Back to deposits</a>
@endsection

@section('account_content')
    @php $ws = $summary ?? []; @endphp

    <x-dashboard.breadcrumbs :items="[
        ['label' => 'Dashboard', 'href' => client_route('agent.dashboard')],
        ['label' => 'Deposits', 'href' => route('agent.deposits.index')],
        ['label' => 'Request deposit'],
    ]" />

    <div class="jp-kpi-grid">
        <div class="jp-kpi">
            <p class="jp-kpi__label">Current balance</p>
            <p class="jp-kpi__value jp-money">Rs {{ number_format((float) ($ws['balance'] ?? 0), 2) }}</p>
        </div>
    </div>

    <x-jp.card class="jp-portal__panel" data-testid="agent-deposit-form-card">
        <form method="post" action="{{ route('agent.deposits.store') }}" enctype="multipart/form-data" data-testid="agent-deposit-form" class="jp-form">
            @csrf

            <div class="jp-field">
                <label class="jp-label" for="amount">Amount (PKR)</label>
                <input type="number" name="amount" id="amount" class="jp-input @error('amount') is-invalid @enderror" value="{{ old('amount') }}" min="1" step="0.01" required>
                @error('amount')<p class="jp-field__error">{{ $message }}</p>@enderror
            </div>

            <div class="jp-field">
                <label class="jp-label" for="payment_method">Payment method</label>
                <input type="text" name="payment_method" id="payment_method" class="jp-input" value="{{ old('payment_method') }}" placeholder="e.g. Bank transfer, JazzCash">
            </div>

            <div class="jp-field">
                <label class="jp-label" for="reference">Reference / transaction ID</label>
                <input type="text" name="reference" id="reference" class="jp-input" value="{{ old('reference') }}">
            </div>

            <div class="jp-field">
                <label class="jp-label" for="agent_note">Note (optional)</label>
                <textarea name="agent_note" id="agent_note" class="jp-textarea" rows="3">{{ old('agent_note') }}</textarea>
            </div>

            <div class="jp-field">
                <label class="jp-label" for="proof">Proof file (optional)</label>
                <input type="file" name="proof" id="proof" class="jp-input @error('proof') is-invalid @enderror" accept=".jpg,.jpeg,.png,.pdf,.webp">
                <p class="jp-field__help">JPG, PNG, PDF, or WebP up to 5 MB.</p>
                @error('proof')<p class="jp-field__error">{{ $message }}</p>@enderror
            </div>

            <div class="jp-form__actions">
                <button type="submit" class="jp-btn jp-btn--primary">Submit deposit request</button>
            </div>
        </form>
    </x-jp.card>
@endsection
