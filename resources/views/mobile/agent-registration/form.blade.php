@extends('layouts.mobile-app')

@section('title', 'Agency application')

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
    <div class="ota-mobile-auth" data-testid="ota-mobile-agent-registration-form">
        <div class="ota-mobile-auth__card">
            <header class="ota-mobile-auth__header">
                <h1 class="ota-mobile-auth__title">Agency application</h1>
                <p class="ota-mobile-auth__subtitle">Submit your agency details. Our team will review your application and provide access after approval.</p>
            </header>

            <div class="ota-mobile-auth__alert ota-mobile-auth__alert--info">
                Agent applications are reviewed by {{ $brandName }}. Access is provided after approval.
            </div>

            <form method="POST" action="{{ route('agent.register.store') }}" class="ota-mobile-auth__form" data-agent-registration-premium data-agent-registration-form data-ajax-validation-endpoint="{{ route('agent.register.validate-field') }}">
                @csrf

                <section data-agent-section="business">
                    <h2 class="ota-mobile-auth__card-title">Agency details</h2>
                    <div class="ota-mobile-auth__field">
                        <label class="ota-mobile-auth__label" for="company_name">Agency name</label>
                        <input id="company_name" class="ota-mobile-auth__input" type="text" name="company_name" value="{{ old('company_name') }}" required autocomplete="organization">
                        <p class="ota-mobile-auth__error field-error" data-error-for="company_name">@error('company_name'){{ $message }}@enderror</p>
                    </div>
                    <div class="ota-mobile-auth__field">
                        <label class="ota-mobile-auth__label" for="city">City</label>
                        <input id="city" class="ota-mobile-auth__input" type="text" name="city" value="{{ old('city') }}" required autocomplete="address-level2" pattern="[A-Za-z \-]+" title="Letters, spaces, and hyphens only">
                        <p class="ota-mobile-auth__error field-error" data-error-for="city">@error('city'){{ $message }}@enderror</p>
                    </div>
                    <div class="ota-mobile-auth__field">
                        <label class="ota-mobile-auth__label" for="business_type">Business type</label>
                        <input id="business_type" class="ota-mobile-auth__input" type="text" name="business_type" value="{{ old('business_type', 'Travel Agency') }}" required>
                        <p class="ota-mobile-auth__error field-error" data-error-for="business_type">@error('business_type'){{ $message }}@enderror</p>
                    </div>
                </section>

                <section data-agent-section="personal">
                    <h2 class="ota-mobile-auth__card-title">Contact details</h2>
                    <div class="ota-mobile-auth__field">
                        <label class="ota-mobile-auth__label" for="first_name">Contact person</label>
                        <input id="first_name" class="ota-mobile-auth__input" type="text" name="first_name" value="{{ old('first_name') }}" required autocomplete="name" pattern="[A-Za-z ]+" title="Only letters and spaces are allowed">
                        <p class="ota-mobile-auth__error field-error" data-error-for="first_name">@error('first_name'){{ $message }}@enderror</p>
                    </div>
                    <div class="ota-mobile-auth__field">
                        <label class="ota-mobile-auth__label" for="email">Email</label>
                        <input id="email" class="ota-mobile-auth__input" type="email" name="email" value="{{ old('email') }}" required autocomplete="email">
                        <p class="ota-mobile-auth__error field-error" data-error-for="email">@error('email'){{ $message }}@enderror</p>
                    </div>
                    <div class="ota-mobile-auth__field">
                        <label class="ota-mobile-auth__label" for="mobile">Phone</label>
                        <div class="ota-mobile-auth__phone-row">
                            <select id="mobile_country_code" class="ota-mobile-auth__input ota-mobile-auth__phone-code" name="mobile_country_code" required aria-label="Country code">
                                @foreach ($countryCodes as $code => $label)
                                    <option value="{{ $code }}" @selected((string) $selectedCountryCode === (string) $code) title="{{ $label }}">{{ $code }}</option>
                                @endforeach
                            </select>
                            <input id="mobile" class="ota-mobile-auth__input" type="tel" name="mobile" value="{{ old('mobile') }}" required autocomplete="tel-national" inputmode="numeric" pattern="[0-9]*" maxlength="15" placeholder="310310300" title="Digits only">
                        </div>
                        <p class="ota-mobile-auth__error field-error" data-error-for="mobile_country_code">@error('mobile_country_code'){{ $message }}@enderror</p>
                        <p class="ota-mobile-auth__error field-error" data-error-for="mobile">@error('mobile'){{ $message }}@enderror</p>
                    </div>
                </section>

                <p class="ota-mobile-auth__subtitle" data-agent-section="verification">Our team may request business verification documents after you submit this application.</p>

                <section data-agent-section="expected-volume">
                    <h2 class="ota-mobile-auth__card-title">Services &amp; volume</h2>
                    <div class="ota-mobile-auth__field">
                        <label class="ota-mobile-auth__label" for="notes">Message</label>
                        <textarea id="notes" class="ota-mobile-auth__input" name="notes" rows="4" maxlength="2000">{{ old('notes') }}</textarea>
                        <p class="ota-mobile-auth__error field-error" data-error-for="notes">@error('notes'){{ $message }}@enderror</p>
                    </div>
                </section>

                <section data-agent-section="agreement">
                    <div class="ota-mobile-auth__field">
                        <label class="ota-mobile-auth__checkbox ota-mobile-auth__checkbox--terms">
                            <input type="checkbox" name="terms" value="1" @checked(old('terms'))>
                            <span>I confirm submitted information is accurate.</span>
                        </label>
                        <p class="ota-mobile-auth__error field-error" data-error-for="terms">@error('terms'){{ $message }}@enderror</p>
                    </div>
                </section>

                <input type="hidden" name="last_name" value="{{ old('last_name', 'Applicant') }}">
                <input type="hidden" name="country" value="{{ old('country', 'Pakistan') }}">
                <input type="hidden" name="office_address" value="{{ old('office_address', 'To be shared during onboarding') }}">

                <button class="ota-mobile-auth__btn ota-mobile-auth__btn--primary" type="submit" data-agent-registration-submit>Submit Agent Application</button>
            </form>

            <nav class="ota-mobile-auth__links" aria-label="Agent application options">
                <a href="{{ route('agent.register') }}">Agent info</a>
                <a href="{{ route('login') }}">Log in</a>
                <a href="{{ route('home') }}">Back to home</a>
            </nav>
        </div>
    </div>
@endsection

@push('scripts')
    <script src="{{ asset('js/public-form-validation.js') }}?v=4"></script>
    <script>
        (function () {
            var form = document.querySelector('[data-agent-registration-form]');
            if (!form) return;

            form.addEventListener('submit', function () {
                var submit = form.querySelector('[data-agent-registration-submit]');
                if (!submit || submit.disabled) return;

                submit.disabled = true;
                submit.setAttribute('aria-disabled', 'true');
                submit.textContent = 'Submitting application...';
            });

            if (!window.AgentRegistrationFormValidation) return;

            var csrf = document.querySelector('meta[name="csrf-token"]');
            var endpoint = form.getAttribute('data-ajax-validation-endpoint') || '';
            var validator = new window.AgentRegistrationFormValidation(form, {
                endpoint: endpoint,
                csrf: csrf ? csrf.getAttribute('content') : '',
                fields: [
                    'company_name',
                    'city',
                    'business_type',
                    'first_name',
                    'email',
                    'mobile_country_code',
                    'mobile',
                    'notes',
                    'terms'
                ],
                mobileDigitsMessage: 'Only numbers are allowed. Do not use spaces, dashes, brackets, or special characters.'
            });
            validator.install();
        })();
    </script>
@endpush
