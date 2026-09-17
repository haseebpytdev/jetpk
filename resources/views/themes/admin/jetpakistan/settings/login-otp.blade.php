@extends(client_layout('dashboard', 'admin'))

@section('title', 'Login OTP')

@section('page-header')
    <div class="jp-between">
        <div>
            <h1>Login OTP</h1>
            <p>Require email OTP after password login for customer, agent, staff, and admin.</p>
        </div>
        <a href="{{ client_route('admin.settings.index') }}" class="jp-btn jp-btn--sm">Back to settings</a>
    </div>
@endsection

@section('content')
    <div class="jp-card" data-testid="login-otp-settings">
        <form method="post" action="{{ client_route('admin.settings.login-otp.update') }}" class="jp-stack">
            @csrf
            @method('PATCH')

            <label class="jp-field">
                <span class="jp-field__label">Require login OTP</span>
                <select name="require_login_otp" class="jp-input" data-testid="require-login-otp-select">
                    <option value="1" @selected(! empty($snapshot['required']))>Enabled — send OTP email and show verification screen</option>
                    <option value="0" @selected(empty($snapshot['required']))>Disabled — authenticate and redirect to dashboard</option>
                </select>
            </label>

            <dl class="jp-meta-grid" style="margin-top:1rem">
                <div>
                    <dt>Runtime state</dt>
                    <dd data-testid="login-otp-runtime">{{ ! empty($snapshot['required']) ? 'ON' : 'OFF' }}</dd>
                </div>
                <div>
                    <dt>Authority source</dt>
                    <dd>{{ $snapshot['source'] ?? 'default' }}</dd>
                </div>
                <div>
                    <dt>Env default</dt>
                    <dd>{{ ! empty($snapshot['env_default']) ? 'true' : 'false' }}</dd>
                </div>
                <div>
                    <dt>Applies to</dt>
                    <dd>{{ implode(', ', $snapshot['applies_to_roles'] ?? []) }}</dd>
                </div>
            </dl>

            <p class="text-secondary" style="margin-top:1rem">
                Dashboard Security → MFA policy preview does not control this gate.
                When OTP is disabled: no OTP record, no OTP email, no OTP screen.
            </p>

            <div class="jp-actions" style="margin-top:1.25rem">
                <button type="submit" class="jp-btn jp-btn--primary" data-testid="login-otp-save">Save login OTP setting</button>
            </div>
        </form>
    </div>
@endsection
