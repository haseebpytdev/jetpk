@extends(client_layout('auth', 'frontend'))

@section('title', 'Agent application')
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
    <div class="ota-register-compact ota-agent-register">
        <header class="ota-register-header">
            <h2 class="ota-register-title">Agency application</h2>
            <p class="ota-register-subtitle">Submit your agency details. Our team will review your application and provide access after approval.</p>
        </header>

        <div class="ota-alert ota-alert--info">
            Agent applications are reviewed by {{ $brandName }}. After approval, you will receive an activation email.
        </div>

        <form method="POST" action="{{ route('agent.register.store') }}" class="ota-register-form ota-agent-register-form" data-agent-registration-premium data-agent-registration-form data-ajax-validation-endpoint="{{ route('agent.register.validate-field') }}">
            @csrf
            <div class="ota-register-grid">
                <section class="ota-agent-register-section" data-agent-section="business">
                    <h3 class="ota-agent-register-section-title">Agency details</h3>
                    <div class="ota-register-grid ota-register-grid--two">
                        <div class="ota-field ota-register-field">
                            <label class="ota-label" for="company_name">Agency name</label>
                            <input id="company_name" class="ota-input ota-register-input" type="text" name="company_name" value="{{ old('company_name') }}" required autocomplete="organization">
                            <div class="ota-error field-error" data-error-for="company_name">@error('company_name'){{ $message }}@enderror</div>
                        </div>
                        <div class="ota-field ota-register-field">
                            <label class="ota-label" for="city">City</label>
                            <input id="city" class="ota-input ota-register-input" type="text" name="city" value="{{ old('city') }}" required autocomplete="address-level2" pattern="[A-Za-z \-]+" title="Letters, spaces, and hyphens only">
                            <div class="ota-error field-error" data-error-for="city">@error('city'){{ $message }}@enderror</div>
                        </div>
                    </div>
                    <div class="ota-field ota-register-field ota-register-field-full">
                        <label class="ota-label" for="business_type">Business type</label>
                        <input id="business_type" class="ota-input ota-register-input" type="text" name="business_type" value="{{ old('business_type', 'Travel Agency') }}" required>
                        <div class="ota-error field-error" data-error-for="business_type">@error('business_type'){{ $message }}@enderror</div>
                    </div>
                </section>

                <section class="ota-agent-register-section" data-agent-section="personal">
                    <h3 class="ota-agent-register-section-title">Contact details</h3>
                    <div class="ota-field ota-register-field ota-register-field-full">
                        <label class="ota-label" for="first_name">Contact person</label>
                        <input id="first_name" class="ota-input ota-register-input" type="text" name="first_name" value="{{ old('first_name') }}" required autocomplete="name" pattern="[A-Za-z ]+" title="Only letters and spaces are allowed">
                        <div class="ota-error field-error" data-error-for="first_name">@error('first_name'){{ $message }}@enderror</div>
                    </div>
                    <div class="ota-field ota-register-field ota-register-field-full">
                        <label class="ota-label" for="email">Email</label>
                        <input id="email" class="ota-input ota-register-input" type="email" name="email" value="{{ old('email') }}" required autocomplete="email">
                        <div class="ota-error field-error" data-error-for="email">@error('email'){{ $message }}@enderror</div>
                    </div>
                    <div class="ota-field ota-register-field ota-register-field-full">
                        <label class="ota-label" for="mobile">Phone</label>
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
                </section>

                <p class="ota-agent-register-note" data-agent-section="verification">Our team may request business verification documents after you submit this application.</p>

                <section class="ota-agent-register-section" data-agent-section="expected-volume">
                    <h3 class="ota-agent-register-section-title">Services &amp; volume</h3>
                    <div class="ota-field ota-register-field ota-register-field-full">
                        <label class="ota-label" for="notes">Message</label>
                        <textarea id="notes" class="ota-input ota-register-input ota-agent-register-textarea" name="notes" rows="4" maxlength="2000">{{ old('notes') }}</textarea>
                        <div class="ota-error field-error" data-error-for="notes">@error('notes'){{ $message }}@enderror</div>
                    </div>
                </section>

                <section class="ota-agent-register-section" data-agent-section="agreement">
                    <div class="ota-field ota-register-field ota-register-field-full ota-register-terms-field">
                        <label class="ota-register-terms">
                            <input type="checkbox" name="terms" value="1" @checked(old('terms'))>
                            <span>I confirm submitted information is accurate.</span>
                        </label>
                        <div class="ota-error field-error" data-error-for="terms">@error('terms'){{ $message }}@enderror</div>
                    </div>
                </section>

                <input type="hidden" name="last_name" value="{{ old('last_name', 'Applicant') }}">
                <input type="hidden" name="country" value="{{ old('country', 'Pakistan') }}">
                <input type="hidden" name="office_address" value="{{ old('office_address', 'To be shared during onboarding') }}">

                <div class="ota-register-actions">
                    <button class="ota-btn-primary ota-btn-primary--block ota-register-submit" type="submit" data-agent-registration-submit>Submit Agent Application</button>
                </div>
            </div>
        </form>

        <nav class="ota-register-links" aria-label="Agent application options">
            <a href="{{ client_route('agent.register') }}">Agent info</a>
            <a href="{{ client_route('login') }}">Log in</a>
            <a href="{{ client_route('home') }}">Back to home</a>
        </nav>
    </div>
@endpush

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
