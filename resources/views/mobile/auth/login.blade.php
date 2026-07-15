@extends('layouts.mobile-app')

@section('title', 'Log in')

@section('content')
    <div class="ota-mobile-auth" data-testid="ota-mobile-login">
        <div class="ota-mobile-auth__card">
            <header class="ota-mobile-auth__header">
                <h1 class="ota-mobile-auth__title">Log in</h1>
                <p class="ota-mobile-auth__subtitle">Sign in to access your bookings and account.</p>
            </header>

            @if (\App\Support\Auth\CheckoutReturnIntent::hasGroupBookingIntent(request()))
                @include('mobile.components.alert', ['type' => 'info', 'message' => 'Please log in or create an account to book this group ticket.'])
            @endif

            @if (session('status'))
                @include('mobile.components.alert', ['type' => 'success', 'message' => session('status')])
            @endif

            @if ($errors->has('social'))
                @include('mobile.components.alert', ['type' => 'danger', 'message' => $errors->first('social')])
            @endif

            <form method="POST" action="{{ route('login') }}" class="ota-mobile-auth__form">
                @csrf

                <div class="ota-mobile-auth__field">
                    <label class="ota-mobile-auth__label" for="login">Email or username</label>
                    <input
                        id="login"
                        class="ota-mobile-auth__input{{ $errors->has('login') || $errors->has('email') ? ' is-invalid' : '' }}"
                        type="text"
                        name="login"
                        value="{{ old('login', old('email')) }}"
                        required
                        autofocus
                        autocomplete="username"
                    >
                    @error('login')
                        <p class="ota-mobile-auth__error">{{ $message }}</p>
                    @enderror
                    @error('email')
                        <p class="ota-mobile-auth__error">{{ $message }}</p>
                    @enderror
                </div>

                <div class="ota-mobile-auth__field">
                    <label class="ota-mobile-auth__label" for="password">Password</label>
                    <div class="ota-mobile-auth__password-field" data-password-field>
                        <input
                            id="password"
                            class="ota-mobile-auth__input{{ $errors->has('password') ? ' is-invalid' : '' }}"
                            type="password"
                            name="password"
                            required
                            autocomplete="current-password"
                        >
                        <button type="button" class="ota-mobile-auth__password-toggle" data-password-toggle aria-label="Show password" aria-pressed="false">
                            <svg class="ota-password-toggle__icon ota-password-toggle__icon--show" data-icon-show xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" width="20" height="20" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                                <path d="M2 12s3.5-7 10-7 10 7 10 7-3.5 7-10 7-10-7-10-7z"/>
                                <circle cx="12" cy="12" r="3"/>
                            </svg>
                            <svg class="ota-password-toggle__icon ota-password-toggle__icon--hide" data-icon-hide xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" width="20" height="20" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true" hidden>
                                <path d="M17.94 17.94A10.07 10.07 0 0 1 12 20c-7 0-11-4-11-4"/>
                                <path d="M9.9 4.24A9.12 9.12 0 0 1 12 4c7 0 11 8 11 8a18.5 18.5 0 0 1-2.16 3.19"/>
                                <path d="M1 1l22 22"/>
                                <path d="M14.12 14.12a3 3 0 1 1-4.24-4.24"/>
                            </svg>
                        </button>
                    </div>
                    @error('password')
                        <p class="ota-mobile-auth__error">{{ $message }}</p>
                    @enderror
                </div>

                <div class="ota-mobile-auth__row">
                    <label class="ota-mobile-auth__checkbox" for="remember">
                        <input id="remember" type="checkbox" name="remember">
                        <span>Remember me</span>
                    </label>
                    @if (Route::has('password.request'))
                        <a class="ota-mobile-auth__link" href="{{ route('password.request') }}">Forgot password?</a>
                    @endif
                </div>

                <button class="ota-mobile-auth__btn ota-mobile-auth__btn--primary" type="submit">Log in</button>
            </form>

            <div class="ota-mobile-auth__social">
                <div class="ota-mobile-auth__divider" aria-hidden="true"><span>or continue with</span></div>
                @include('auth.partials.social-oauth-buttons', ['verb' => 'Log in'])
            </div>

            <nav class="ota-mobile-auth__links" aria-label="Account options">
                <a href="{{ route('register') }}">Sign up</a>
                <a href="{{ route('agent.register.form') }}">Become our agent</a>
            </nav>
        </div>
    </div>
@endsection

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
                    var showIcon = btn.querySelector('[data-icon-show]');
                    var hideIcon = btn.querySelector('[data-icon-hide]');
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
                    if (showIcon && hideIcon) {
                        showIcon.hidden = isHidden;
                        hideIcon.hidden = !isHidden;
                    }
                });
            });
        })();
    </script>
@endpush
