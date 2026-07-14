@extends(client_layout('auth', 'frontend'))

@section('title', 'Complete your profile')
@section('auth_card_class', 'auth-card--register-compact')

@php
    $selectedCountryCode = old('mobile_country_code', $defaults['mobile_country_code'] ?? '+92');
    if (is_string($selectedCountryCode) && $selectedCountryCode !== '' && ! str_starts_with($selectedCountryCode, '+')) {
        $selectedCountryCode = '+'.$selectedCountryCode;
    }
@endphp

@push('auth_form')
    <div class="ota-register-compact">
        <header class="ota-register-header">
            <h2 class="ota-register-title">Complete your profile</h2>
            <p class="ota-register-subtitle">Confirm your details to continue. Your Google email stays verified and cannot be changed here.</p>
        </header>

        <form method="POST" action="{{ route('auth.google.complete-profile.store') }}" class="ota-register-form">
            @csrf

            <div class="ota-register-grid">
                <div class="ota-register-grid ota-register-grid--two">
                    <div class="ota-field ota-register-field">
                        <label class="ota-label" for="first_name">First name</label>
                        <input id="first_name" class="ota-input ota-register-input" type="text" name="first_name" value="{{ old('first_name', $defaults['first_name'] ?? '') }}" required autofocus autocomplete="given-name" pattern="[A-Za-z ]+" title="Only letters and spaces are allowed">
                        @error('first_name')<div class="ota-error">{{ $message }}</div>@enderror
                    </div>
                    <div class="ota-field ota-register-field">
                        <label class="ota-label" for="last_name">Last name</label>
                        <input id="last_name" class="ota-input ota-register-input" type="text" name="last_name" value="{{ old('last_name', $defaults['last_name'] ?? '') }}" required autocomplete="family-name" pattern="[A-Za-z ]+" title="Only letters and spaces are allowed">
                        @error('last_name')<div class="ota-error">{{ $message }}</div>@enderror
                    </div>
                </div>

                <div class="ota-field ota-register-field ota-register-field-full">
                    <label class="ota-label" for="email">Email</label>
                    <input id="email" class="ota-input ota-register-input" type="email" value="{{ $defaults['email'] ?? '' }}" readonly disabled aria-readonly="true">
                </div>

                <div class="ota-field ota-register-field ota-register-field-full">
                    <label class="ota-label" for="mobile">Contact / mobile</label>
                    <div class="ota-register-phone-row">
                        <select id="mobile_country_code" class="ota-input ota-select ota-register-input ota-country-code-select" name="mobile_country_code" required aria-label="Country code">
                            @foreach ($countryCodes as $code => $label)
                                <option value="{{ $code }}" @selected((string) $selectedCountryCode === (string) $code) title="{{ $label }}">{{ $code }}</option>
                            @endforeach
                        </select>
                        <input id="mobile" class="ota-input ota-register-input ota-mobile-number-input" type="tel" name="mobile" value="{{ old('mobile', $defaults['mobile'] ?? '') }}" required autocomplete="tel-national" inputmode="numeric" pattern="[0-9]*" maxlength="15" placeholder="310310300" title="Digits only">
                    </div>
                    @error('mobile_country_code')<div class="ota-error">{{ $message }}</div>@enderror
                    @error('mobile')<div class="ota-error">{{ $message }}</div>@enderror
                </div>

                <button class="ota-btn-primary ota-btn-primary--block ota-register-submit" type="submit">Continue</button>
            </div>
        </form>
    </div>
@endpush
