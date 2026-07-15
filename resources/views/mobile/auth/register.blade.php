@extends('layouts.mobile-app')

@section('title', 'Register')

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

@section('content')
    <div class="ota-mobile-auth" data-testid="ota-mobile-register">
        <div class="ota-mobile-auth__card">
            <header class="ota-mobile-auth__header">
                <h1 class="ota-mobile-auth__title">Sign up</h1>
                <p class="ota-mobile-auth__subtitle">Create your account to search, book, and manage travel.</p>
            </header>

            @if ($errors->has('social'))
                @include('mobile.components.alert', ['type' => 'danger', 'message' => $errors->first('social')])
            @endif

            <form
                method="POST"
                action="{{ route('register') }}"
                class="ota-mobile-auth__form"
                data-register-premium-form
                data-ajax-validation-endpoint="{{ route('register.customer.validate-field') }}"
            >
                @csrf

                <div class="ota-mobile-auth__alert ota-mobile-auth__alert--danger" data-global-error hidden></div>

                <div class="ota-mobile-auth__grid ota-mobile-auth__grid--two">
                    @include('mobile.components.form-field', [
                        'name' => 'first_name',
                        'label' => 'First name',
                        'required' => true,
                        'autocomplete' => 'given-name',
                        'pattern' => '[A-Za-z ]+',
                        'title' => 'Only letters and spaces are allowed',
                    ])
                    @include('mobile.components.form-field', [
                        'name' => 'last_name',
                        'label' => 'Last name',
                        'required' => true,
                        'autocomplete' => 'family-name',
                        'pattern' => '[A-Za-z ]+',
                        'title' => 'Only letters and spaces are allowed',
                    ])
                </div>

                @include('mobile.components.form-field', [
                    'name' => 'email',
                    'label' => 'Email',
                    'type' => 'email',
                    'required' => true,
                    'autocomplete' => 'username',
                ])

                <div class="ota-mobile-auth__field">
                    <label class="ota-mobile-auth__label" for="mobile">Mobile number</label>
                    <div class="ota-mobile-auth__phone-row">
                        <select
                            id="mobile_country_code"
                            class="ota-mobile-auth__input ota-mobile-auth__phone-code"
                            name="mobile_country_code"
                            required
                            aria-label="Country code"
                        >
                            @foreach ($countryCodes as $code => $label)
                                <option value="{{ $code }}" @selected((string) $selectedCountryCode === (string) $code) title="{{ $label }}">{{ $code }}</option>
                            @endforeach
                        </select>
                        <input
                            id="mobile"
                            class="ota-mobile-auth__input{{ $errors->has('mobile') ? ' is-invalid' : '' }}"
                            type="tel"
                            name="mobile"
                            value="{{ old('mobile') }}"
                            required
                            autocomplete="tel-national"
                            inputmode="numeric"
                            pattern="[0-9]*"
                            maxlength="15"
                            placeholder="310310300"
                            title="Digits only"
                        >
                    </div>
                    @error('mobile_country_code')
                        <p class="ota-mobile-auth__error">{{ $message }}</p>
                    @enderror
                    @error('mobile')
                        <p class="ota-mobile-auth__error">{{ $message }}</p>
                    @enderror
                </div>

                <div class="ota-mobile-auth__grid ota-mobile-auth__grid--two">
                    @include('mobile.components.form-field', [
                        'name' => 'password',
                        'label' => 'Password',
                        'type' => 'password',
                        'required' => true,
                        'autocomplete' => 'new-password',
                        'minlength' => '8',
                    ])
                    @include('mobile.components.form-field', [
                        'name' => 'password_confirmation',
                        'label' => 'Confirm password',
                        'type' => 'password',
                        'required' => true,
                        'autocomplete' => 'new-password',
                        'minlength' => '8',
                    ])
                </div>

                <div class="ota-mobile-auth__field">
                    <label class="ota-mobile-auth__label" for="security_answer">
                        Security check: {{ $securityQuestion ?? session('register_security_question', 'What is 1 + 1?') }}
                    </label>
                    <input
                        id="security_answer"
                        class="ota-mobile-auth__input{{ $errors->has('security_answer') || $errors->has('security_check') ? ' is-invalid' : '' }}"
                        type="text"
                        name="security_answer"
                        value=""
                        required
                        inputmode="numeric"
                        pattern="[0-9]*"
                        autocomplete="off"
                    >
                    @error('security_answer')
                        <p class="ota-mobile-auth__error">{{ $message }}</p>
                    @enderror
                    @if ($errors->has('security_check'))
                        <p class="ota-mobile-auth__error">{{ $errors->first('security_check') }}</p>
                    @endif
                </div>

                <div class="ota-mobile-auth__field">
                    <label class="ota-mobile-auth__checkbox ota-mobile-auth__checkbox--terms">
                        <input type="checkbox" name="terms" value="1" @checked(old('terms'))>
                        <span>I agree to {{ $brandName }} terms and privacy policy.</span>
                    </label>
                    @error('terms')
                        <p class="ota-mobile-auth__error">{{ $message }}</p>
                    @enderror
                </div>

                <button class="ota-mobile-auth__btn ota-mobile-auth__btn--primary" type="submit">Register</button>
            </form>

            @include('auth.partials.social-oauth-buttons', ['verb' => 'Sign up'])

            <nav class="ota-mobile-auth__links" aria-label="Account options">
                <a href="{{ route('login') }}">Log in</a>
                <a href="{{ route('home') }}">Back to home</a>
            </nav>
        </div>
    </div>
@endsection

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
