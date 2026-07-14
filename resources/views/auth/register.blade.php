@extends(client_layout('auth', 'frontend'))

@section('title', 'Register')
@section('auth_card_class', 'auth-card--register-compact')

@php
    $countryCodes = [
        '+92' => 'Pakistan (+92)',
        '+61' => 'Australia (+61)',
        '+971' => 'UAE (+971)',
        '+966' => 'Saudi Arabia (+966)',
        '+44' => 'United Kingdom (+44)',
        '+1' => 'United States / Canada (+1)',
        '+974' => 'Qatar (+974)',
        '+965' => 'Kuwait (+965)',
        '+968' => 'Oman (+968)',
        '+973' => 'Bahrain (+973)',
    ];
    $selectedCountryCode = old('mobile_country_code', '+92');
    if (is_string($selectedCountryCode) && $selectedCountryCode !== '' && ! str_starts_with($selectedCountryCode, '+')) {
        $selectedCountryCode = '+'.$selectedCountryCode;
    }
@endphp

@push('auth_form')
    <div class="ota-register-compact">
        <header class="ota-register-header">
            <h2 class="ota-register-title">Sign up</h2>
            <p class="ota-register-subtitle">Create your account to search, book, and manage travel.</p>
        </header>

        @if (\App\Support\Auth\CheckoutReturnIntent::hasGroupBookingIntent(request()))
            <div class="ota-alert ota-alert--info" role="status">Please log in or create an account to book this group ticket.</div>
        @endif

        <p class="ota-visually-hidden">Book flights, track your booking requests, submit payment proof, and access travel documents from one place.</p>

        @if ($errors->has('social'))
            <div class="ota-alert ota-alert--danger">{{ $errors->first('social') }}</div>
        @endif

        <form method="POST" action="{{ route('register') }}" class="ota-register-form" data-register-premium-form data-ajax-validation-endpoint="{{ route('register.customer.validate-field') }}">
            @csrf
            <div class="ota-register-grid">
                <div class="ota-alert ota-alert--danger" data-global-error hidden></div>

                <div class="ota-register-grid ota-register-grid--two">
                    <div class="ota-field ota-register-field">
                        <label class="ota-label" for="first_name">First name</label>
                        <input id="first_name" class="ota-input ota-register-input" type="text" name="first_name" value="{{ old('first_name') }}" required autofocus autocomplete="given-name" pattern="[A-Za-z ]+" title="Only letters and spaces are allowed">
                        <div class="ota-error field-error" data-error-for="first_name">@error('first_name'){{ $message }}@enderror</div>
                    </div>
                    <div class="ota-field ota-register-field">
                        <label class="ota-label" for="last_name">Last name</label>
                        <input id="last_name" class="ota-input ota-register-input" type="text" name="last_name" value="{{ old('last_name') }}" required autocomplete="family-name" pattern="[A-Za-z ]+" title="Only letters and spaces are allowed">
                        <div class="ota-error field-error" data-error-for="last_name">@error('last_name'){{ $message }}@enderror</div>
                    </div>
                </div>

                <div class="ota-field ota-register-field ota-register-field-full">
                    <label class="ota-label" for="email">Email</label>
                    <input id="email" class="ota-input ota-register-input" type="email" name="email" value="{{ old('email') }}" required autocomplete="username">
                    <div class="ota-error field-error" data-error-for="email">@error('email'){{ $message }}@enderror</div>
                </div>

                <div class="ota-field ota-register-field ota-register-field-full">
                    <label class="ota-label" for="mobile">Mobile number</label>
                    <div class="ota-register-phone-row">
                        <select id="mobile_country_code" class="ota-input ota-select ota-register-input ota-country-code-select" name="mobile_country_code" required aria-label="Country code">
                            @foreach ($countryCodes as $code => $label)
                                <option value="{{ $code }}" @selected((string) $selectedCountryCode === (string) $code) title="{{ $label }}">{{ $code }}</option>
                            @endforeach
                        </select>
                        <input id="mobile" class="ota-input ota-register-input ota-mobile-number-input" type="tel" name="mobile" value="{{ old('mobile') }}" required autocomplete="tel-national" inputmode="numeric" pattern="[0-9]*" maxlength="15" placeholder="310310300" title="Digits only">
                    </div>
                    <div class="ota-error field-error" data-error-for="mobile_country_code">@error('mobile_country_code'){{ $message }}@enderror</div>
                    <div class="ota-error field-error" data-error-for="mobile">@error('mobile'){{ $message }}@enderror</div>
                </div>

                <div class="ota-register-grid ota-register-grid--two">
                    <div class="ota-field ota-register-field">
                        <label class="ota-label" for="password">Password</label>
                        <input id="password" class="ota-input ota-register-input" type="password" name="password" required autocomplete="new-password" minlength="8">
                        <div class="ota-error field-error" data-error-for="password">@error('password'){{ $message }}@enderror</div>
                    </div>
                    <div class="ota-field ota-register-field">
                        <label class="ota-label" for="password_confirmation">Confirm password</label>
                        <input id="password_confirmation" class="ota-input ota-register-input" type="password" name="password_confirmation" required autocomplete="new-password" minlength="8">
                        <div class="ota-error field-error" data-error-for="password_confirmation">@error('password_confirmation'){{ $message }}@enderror</div>
                    </div>
                </div>

                <div class="ota-field ota-register-field ota-register-field-full">
                    <label class="ota-label" for="security_answer">Security check: {{ $securityQuestion ?? session('register_security_question', 'What is 1 + 1?') }}</label>
                    <input id="security_answer" class="ota-input ota-register-input" type="text" name="security_answer" value="" required inputmode="numeric" pattern="[0-9]*" autocomplete="off">
                    <div class="ota-error field-error" data-error-for="security_answer">
                        @error('security_answer'){{ $message }}@enderror
                        @if ($errors->has('security_check'))
                            {{ $errors->first('security_check') }}
                        @endif
                    </div>
                </div>

                <div class="ota-field ota-register-field ota-register-field-full ota-register-terms-field">
                    <label class="ota-register-terms">
                        <input type="checkbox" name="terms" value="1" @checked(old('terms'))>
                        <span>I agree to {{ $brandName }} terms and privacy policy.</span>
                    </label>
                    <div class="ota-error field-error" data-error-for="terms">@error('terms'){{ $message }}@enderror</div>
                </div>

                <div class="ota-register-actions">
                    <button class="ota-btn-primary ota-btn-primary--block ota-register-submit" type="submit">Register</button>
                </div>
            </div>
        </form>

        <div class="ota-register-social">
            @include('auth.partials.social-oauth-buttons', ['verb' => 'Sign up'])
        </div>

        <nav class="ota-register-links" aria-label="Account options">
            <a href="{{ client_route('login') }}">Log in</a>
            <a href="{{ client_route('home') }}">Back to home</a>
        </nav>
    </div>
@endpush

@push('scripts')
    <script src="{{ asset('js/public-form-validation.js') }}?v=3"></script>
    <script>
        (function () {
            var form = document.querySelector('[data-register-premium-form]');
            if (!form || !window.PublicFormValidation) return;
            var csrf = document.querySelector('meta[name="csrf-token"]');
            var endpoint = form.getAttribute('data-ajax-validation-endpoint') || '';
            var validator = new window.PublicFormValidation(form, {
                endpoint: endpoint,
                csrf: csrf ? csrf.getAttribute('content') : '',
                requiredFields: [
                    'first_name',
                    'last_name',
                    'email',
                    'mobile_country_code',
                    'mobile',
                    'password',
                    'password_confirmation',
                    'security_answer'
                ],
                pairedFields: {
                    password: ['password', 'password_confirmation'],
                    mobile: ['mobile_country_code', 'mobile']
                },
                passwordMismatchMessage: "Password doesn't match.",
                mobileDigitsMessage: 'Only numbers are allowed. Do not use spaces, dashes, brackets, or special characters.'
            });
            validator.install();
        })();
    </script>
@endpush
