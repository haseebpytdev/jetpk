@extends(client_layout('auth', 'frontend'))

@section('title', 'Log in')
@section('auth_card_class', 'auth-card--login-premium')

@push('auth_form')
    <div class="ota-login-compact">
        <div class="ota-login-card-inner">
            <header class="ota-login-header">
                <h2 class="ota-login-title">Log in</h2>
                <p class="ota-login-subtitle">Sign in to access your bookings and account.</p>
            </header>

            @if (\App\Support\Auth\CheckoutReturnIntent::hasGroupBookingIntent(request()))
                <div class="ota-alert ota-alert--info" role="status">Please log in or create an account to book this group ticket.</div>
            @endif

            @if (session('status'))
                <div class="ota-alert ota-alert--success">{{ session('status') }}</div>
            @endif
            @if ($errors->has('social'))
                <div class="ota-alert ota-alert--danger">{{ $errors->first('social') }}</div>
            @endif

            <form method="POST" action="{{ route('login') }}" class="ota-login-form ota-form-grid" data-login-premium-form>
                @csrf
                <div class="ota-field">
                    <label class="ota-label" for="login">Email or username</label>
                    <input id="login" class="ota-input ota-login-input" type="text" name="login" value="{{ old('login', old('email')) }}" required autofocus autocomplete="username">
                    @error('login')<div class="ota-error">{{ $message }}</div>@enderror
                    @error('email')<div class="ota-error">{{ $message }}</div>@enderror
                </div>

                <div class="ota-field">
                    <label class="ota-label" for="password">Password</label>
                    <div class="ota-password-field" data-password-field>
                        <input id="password" class="ota-input ota-login-input" type="password" name="password" required autocomplete="current-password">
                        <button type="button" class="ota-password-toggle" data-password-toggle aria-label="Show password" aria-pressed="false">
                            <i class="fa fa-eye" aria-hidden="true"></i>
                        </button>
                    </div>
                    @error('password')<div class="ota-error">{{ $message }}</div>@enderror
                </div>

                <div class="ota-auth-row ota-login-options">
                    <label class="ota-login-remember" for="remember">
                        <input id="remember" class="ota-login-checkbox" type="checkbox" name="remember">
                        <span>Remember me</span>
                    </label>
                    @if (Route::has('password.request'))
                        <a class="ota-auth-link ota-login-forgot" href="{{ client_route('password.request') }}">Forgot password?</a>
                    @endif
                </div>

                <button class="ota-btn-primary ota-btn-primary--block ota-login-submit" type="submit">Log in</button>
            </form>

            <div class="ota-login-social">
                <div class="ota-login-divider" aria-hidden="true"><span>or continue with</span></div>
                @include('auth.partials.social-oauth-buttons', ['verb' => 'Log in'])
            </div>

            <nav class="ota-login-footer-links" aria-label="Account options">
                <a href="{{ client_route('register') }}">Sign up</a>
                <a href="{{ client_route('agent.register.form') }}">Become our agent</a>
            </nav>
        </div>
    </div>
@endpush

@push('scripts')
    <script>
        (function () {
            document.querySelectorAll('[data-password-toggle]').forEach(function (btn) {
                btn.addEventListener('click', function () {
                    var field = btn.closest('[data-password-field]');
                    if (!field) {
                        return;
                    }
                    var input = field.querySelector('input');
                    var icon = btn.querySelector('i');
                    if (!input) {
                        return;
                    }
                    var isHidden = input.type === 'password';
                    input.type = isHidden ? 'text' : 'password';
                    btn.setAttribute('aria-label', isHidden ? 'Hide password' : 'Show password');
                    btn.setAttribute('aria-pressed', isHidden ? 'true' : 'false');
                    if (icon) {
                        icon.classList.toggle('fa-eye', !isHidden);
                        icon.classList.toggle('fa-eye-slash', isHidden);
                    }
                });
            });
        })();
    </script>
@endpush
