@extends(client_layout('mobile-app', 'mobile'))

@section('title', 'Request deposit')

@section('mobile_app_title', 'Request deposit')

@section('mobile_app_back')
    <a href="{{ route('agent.deposits.index') }}" class="ota-mobile-app__back-btn" aria-label="Back to deposits">
        <svg viewBox="0 0 24 24" width="22" height="22" fill="currentColor" aria-hidden="true"><path d="M20 11H7.83l5.59-5.59L12 4l-8 8 8 8 1.41-1.41L7.83 13H20v-2z"/></svg>
    </a>
@endsection

@section('content')
    <div class="ota-mobile-agent" data-testid="ota-mobile-agent-deposit-create">
        @include('mobile.agent.partials.wallet-summary-card', [
            'summary' => $summary,
            'title' => 'Current balance',
        ])

        <div class="ota-mobile-agent__card ota-mobile-agent__form-card">
            <h1 class="ota-mobile-agent__page-title">Submit deposit request</h1>
            <p class="ota-mobile-agent__note">Upload proof of bank transfer or wallet payment for finance review.</p>

            <form method="post" action="{{ route('agent.deposits.store') }}" enctype="multipart/form-data" class="ota-mobile-agent__form" data-testid="agent-deposit-form">
                @csrf

                <div class="ota-mobile-agent__field">
                    <label class="ota-mobile-agent__label" for="amount">Amount (PKR)</label>
                    <input type="number" name="amount" id="amount" class="ota-mobile-agent__input{{ $errors->has('amount') ? ' is-invalid' : '' }}" value="{{ old('amount') }}" min="1" step="0.01" required>
                    @error('amount')<p class="ota-mobile-agent__error">{{ $message }}</p>@enderror
                </div>

                <div class="ota-mobile-agent__field">
                    <label class="ota-mobile-agent__label" for="payment_method">Payment method</label>
                    <input type="text" name="payment_method" id="payment_method" class="ota-mobile-agent__input" value="{{ old('payment_method') }}" placeholder="e.g. Bank transfer, JazzCash">
                </div>

                <div class="ota-mobile-agent__field">
                    <label class="ota-mobile-agent__label" for="reference">Reference / transaction ID</label>
                    <input type="text" name="reference" id="reference" class="ota-mobile-agent__input" value="{{ old('reference') }}">
                </div>

                <div class="ota-mobile-agent__field">
                    <label class="ota-mobile-agent__label" for="agent_note">Note (optional)</label>
                    <textarea name="agent_note" id="agent_note" rows="3" class="ota-mobile-agent__input">{{ old('agent_note') }}</textarea>
                </div>

                <div class="ota-mobile-agent__field">
                    <label class="ota-mobile-agent__label" for="proof">Proof file (optional)</label>
                    <input type="file" name="proof" id="proof" class="ota-mobile-agent__input{{ $errors->has('proof') ? ' is-invalid' : '' }}" accept=".jpg,.jpeg,.png,.pdf,.webp">
                    <p class="ota-mobile-agent__note">JPG, PNG, PDF, or WebP up to 5 MB.</p>
                    @error('proof')<p class="ota-mobile-agent__error">{{ $message }}</p>@enderror
                </div>

                <button type="submit" class="ota-mobile-agent__btn ota-mobile-agent__btn--primary ota-mobile-agent__btn--block">Submit deposit request</button>
                <a href="{{ route('agent.deposits.index') }}" class="ota-mobile-agent__btn ota-mobile-agent__btn--secondary ota-mobile-agent__btn--block">Cancel</a>
            </form>
        </div>
    </div>
@endsection
