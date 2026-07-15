@extends(client_layout('dashboard', 'admin'))

@section('title', 'Payment methods')

@section('page-header')
    <div class="jp-between">
        <div>
            <p class="jp-backlink"><a href="{{ route('admin.settings.index') }}">← Settings</a></p>
            <h1>Payment methods</h1>
            <p class="jp-muted mb-0">Manual payments and online gateway settings for {{ $agency->name }}.</p>
        </div>
        <div>
            <a href="{{ route('admin.settings.communications.notification-events.index') }}" class="jp-btn jp-btn--sm jp-btn--outline">Notification routing</a>
        </div>
    </div>
@endsection

@section('content')
    @if (session('status'))
        <div class="jp-alert jp-alert--success">{{ session('status') }}</div>
    @endif
    @if (session('error'))
        <div class="jp-alert jp-alert--danger">{{ session('error') }}</div>
    @endif
    @if ($errors->any())
        <div class="jp-alert jp-alert--danger"><ul class="mb-0">@foreach($errors->all() as $e)<li>{{ $e }}</li>@endforeach</ul></div>
    @endif

    <div class="jp-card">
        <div class="card-header d-flex justify-content-between align-items-center">
            <h3 class="jp-card__title mb-0">AbhiPay — online card payments</h3>
            @if ($abhiPay->hasMerchantSecretKey())
                <span class="badge bg-success-lt">Secret configured</span>
            @endif
        </div>
        <div class="jp-card__body">
            <p class="text-secondary">Store AbhiPay API credentials here. Dashboard login email/password/OTP are not used by the OTA.</p>
            <form method="post" action="{{ route('admin.settings.payments.abhipay.update', request()->only('agency_id')) }}" class="row g-3">
                @csrf
                @method('PATCH')
                <div class="col-md-4">
                    <label class="jp-label">Active</label>
                    <div class="form-check form-switch">
                        <input class="form-check-input" type="checkbox" name="is_active" value="1" @checked(old('is_active', $abhiPay->is_active))>
                        <span class="form-check-label">Show AbhiPay at checkout when configured</span>
                    </div>
                </div>
                <div class="col-md-4">
                    <label class="jp-label" for="environment">Environment</label>
                    <select id="environment" name="environment" class="jp-control" required>
                        <option value="test" @selected(old('environment', $abhiPay->environment) === 'test')>Test</option>
                        <option value="live" @selected(old('environment', $abhiPay->environment) === 'live')>Live</option>
                    </select>
                </div>
                <div class="col-md-4">
                    <label class="jp-label" for="merchant_id">Merchant ID / number</label>
                    <input id="merchant_id" name="merchant_id" type="text" class="jp-control" value="{{ old('merchant_id') }}" placeholder="{{ $abhiPay->maskedMerchantId() ? 'Configured — enter only to replace' : 'Merchant number' }}" autocomplete="off">
                </div>
                <div class="col-md-6">
                    <label class="jp-label" for="merchant_secret_key">Merchant secret key</label>
                    <input id="merchant_secret_key" name="merchant_secret_key" type="password" class="jp-control" placeholder="{{ $abhiPay->hasMerchantSecretKey() ? 'Configured — enter only to replace' : 'Required for go-live' }}" autocomplete="new-password">
                </div>
                <div class="col-md-6">
                    <label class="jp-label" for="base_url">Base URL</label>
                    <input id="base_url" name="base_url" type="url" class="jp-control" value="{{ old('base_url', $abhiPay->base_url ?: \App\Models\PaymentGateway::DEFAULT_BASE_URL) }}">
                </div>
                <div class="col-md-6">
                    <label class="jp-label">Callback URL</label>
                    <div class="input-group">
                        <input type="text" class="jp-control" value="{{ $callbackUrl }}" readonly>
                        <button type="button" class="jp-btn jp-btn--ghost" onclick="navigator.clipboard.writeText(@js($callbackUrl))">Copy</button>
                    </div>
                </div>
                <div class="col-md-6">
                    <label class="jp-label">Success URL</label>
                    <div class="input-group">
                        <input type="text" class="jp-control" value="{{ $successUrl }}" readonly>
                        <button type="button" class="jp-btn jp-btn--ghost" onclick="navigator.clipboard.writeText(@js($successUrl))">Copy</button>
                    </div>
                </div>
                <div class="col-md-6">
                    <label class="jp-label">Cancel URL</label>
                    <div class="input-group">
                        <input type="text" class="jp-control" value="{{ $cancelUrl }}" readonly>
                        <button type="button" class="jp-btn jp-btn--ghost" onclick="navigator.clipboard.writeText(@js($cancelUrl))">Copy</button>
                    </div>
                </div>
                <div class="col-md-6">
                    <label class="jp-label">Decline URL</label>
                    <div class="input-group">
                        <input type="text" class="jp-control" value="{{ $declineUrl }}" readonly>
                        <button type="button" class="jp-btn jp-btn--ghost" onclick="navigator.clipboard.writeText(@js($declineUrl))">Copy</button>
                    </div>
                </div>
                <div class="col-12 d-flex flex-wrap gap-2">
                    <button type="submit" class="jp-btn jp-btn--primary">Save AbhiPay settings</button>
                </div>
            </form>
            <form method="post" action="{{ route('admin.settings.payments.abhipay.test', request()->only('agency_id')) }}" class="mt-2">
                @csrf
                <button type="submit" class="jp-btn jp-btn--outline">Test connection</button>
            </form>
        </div>
    </div>

    <div class="row row-cards">
        <div class="col-lg-6">
            <div class="jp-card">
                <div class="jp-card__head"><h3 class="jp-card__title">Accepted methods (manual)</h3></div>
                <div class="jp-card__body">
                    <ul class="mb-0">
                        <li><strong>Bank transfer</strong> — customers upload payment proof after booking.</li>
                        <li><strong>Cash</strong> — recorded by staff when payment is received in person.</li>
                        <li><strong>Card (manual)</strong> — card payments taken outside the OTA.</li>
                        <li><strong>Easypaisa / JazzCash</strong> — mobile wallet transfers with proof upload.</li>
                        <li><strong>AbhiPay</strong> — online debit/credit card when gateway is active above.</li>
                    </ul>
                </div>
            </div>
        </div>
        <div class="col-lg-6">
            <div class="jp-card">
                <div class="jp-card__head"><h3 class="jp-card__title">Payment proof policy</h3></div>
                <div class="jp-card__body">
                    <p class="mb-2">Customers and agents can submit payment proof from their booking portal when payment is pending.</p>
                    <p class="mb-0">AbhiPay payments are verified automatically via server-side gateway verification. Manual proofs are reviewed by staff.</p>
                </div>
            </div>
        </div>
    </div>
@endsection
