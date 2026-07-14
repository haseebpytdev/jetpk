@extends('themes.frontend.jetpakistan.layouts.frontend')

@section('title', 'Agent application — JetPakistan')

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
<section class="jp-page jp-page--agent-form" aria-labelledby="jp-agent-form-heading">
  <div class="wrap jp-page-wrap jp-page-wrap--narrow">
    <x-jp.page-hero
      id="jp-agent-form-heading"
      kicker="Agency application"
      title="Apply to become a JetPakistan agent"
      description="Submit your agency details. Our team will review your application and provide access after approval."
    />

    <x-jp.alert variant="warning">
      Agent applications are reviewed by {{ $brandName ?? client_branding()->companyName() }}. After approval, you will receive an activation email.
    </x-jp.alert>

    <form method="POST" action="{{ route('agent.register.store') }}" class="jp-form jp-agent-form" data-agent-registration-form data-ajax-validation-endpoint="{{ route('agent.register.validate-field') }}">
      @csrf

      <x-jp.card title="Agency details">
        <div class="jp-form-grid jp-form-grid--2">
          <x-jp.form-group label="Agency name" for="company_name" :error="$errors->first('company_name')">
            <input id="company_name" class="jp-input @error('company_name') jp-input--invalid @enderror" type="text" name="company_name" value="{{ old('company_name') }}" required autocomplete="organization">
          </x-jp.form-group>
          <x-jp.form-group label="City" for="city" :error="$errors->first('city')">
            <input id="city" class="jp-input @error('city') jp-input--invalid @enderror" type="text" name="city" value="{{ old('city') }}" required autocomplete="address-level2" pattern="[A-Za-z \-]+" title="Letters, spaces, and hyphens only">
          </x-jp.form-group>
        </div>
        <x-jp.form-group label="Business type" for="business_type" :error="$errors->first('business_type')">
          <input id="business_type" class="jp-input @error('business_type') jp-input--invalid @enderror" type="text" name="business_type" value="{{ old('business_type', 'Travel Agency') }}" required>
        </x-jp.form-group>
      </x-jp.card>

      <x-jp.card title="Contact details">
        <x-jp.form-group label="Contact person" for="first_name" :error="$errors->first('first_name')">
          <input id="first_name" class="jp-input @error('first_name') jp-input--invalid @enderror" type="text" name="first_name" value="{{ old('first_name') }}" required autocomplete="name" pattern="[A-Za-z ]+" title="Only letters and spaces are allowed">
        </x-jp.form-group>
        <x-jp.form-group label="Email" for="email" :error="$errors->first('email')">
          <input id="email" class="jp-input @error('email') jp-input--invalid @enderror" type="email" name="email" value="{{ old('email') }}" required autocomplete="email">
        </x-jp.form-group>
        <x-jp.form-group label="Phone" for="mobile" :error="$errors->first('mobile') ?: $errors->first('mobile_country_code')">
          <div class="jp-phone-row">
            <select id="mobile_country_code" class="jp-select" name="mobile_country_code" required aria-label="Country code">
              @foreach ($countryCodes as $code => $label)
                <option value="{{ $code }}" @selected((string) $selectedCountryCode === (string) $code) title="{{ $label }}">{{ $code }}</option>
              @endforeach
            </select>
            <input id="mobile" class="jp-input @error('mobile') jp-input--invalid @enderror" type="tel" name="mobile" value="{{ old('mobile') }}" required autocomplete="tel-national" inputmode="numeric" pattern="[0-9]*" maxlength="15" placeholder="3103103000">
          </div>
        </x-jp.form-group>
      </x-jp.card>

      <x-jp.card title="Services & volume">
        <p class="jp-field-hint">Our team may request business verification documents after you submit this application.</p>
        <x-jp.form-group label="Message (optional)" for="notes" :error="$errors->first('notes')">
          <textarea id="notes" class="jp-textarea @error('notes') jp-input--invalid @enderror" name="notes" rows="4" maxlength="2000">{{ old('notes') }}</textarea>
        </x-jp.form-group>
      </x-jp.card>

      <x-jp.card>
        <label class="jp-auth-remember" for="terms">
          <input id="terms" type="checkbox" name="terms" value="1" @checked(old('terms'))>
          <span>I confirm submitted information is accurate.</span>
        </label>
        @error('terms')
          <p class="jp-field-error">{{ $message }}</p>
        @enderror
        <x-jp.button type="submit" variant="primary" block data-agent-registration-submit>Submit agent application</x-jp.button>
      </x-jp.card>

      <input type="hidden" name="last_name" value="{{ old('last_name', 'Applicant') }}">
      <input type="hidden" name="country" value="{{ old('country', 'Pakistan') }}">
      <input type="hidden" name="office_address" value="{{ old('office_address', 'To be shared during onboarding') }}">
    </form>

    <nav class="jp-form-foot" aria-label="Agent application options">
      <a href="{{ client_route('agent.register') }}">Agent info</a>
      <a href="{{ client_route('login') }}">Log in</a>
      <a href="{{ client_route('support') }}">Contact support</a>
    </nav>
  </div>
</section>
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
    fields: ['company_name', 'city', 'business_type', 'first_name', 'email', 'mobile_country_code', 'mobile', 'notes', 'terms'],
    mobileDigitsMessage: 'Only numbers are allowed. Do not use spaces, dashes, brackets, or special characters.'
  });
  validator.install();
})();
</script>
@endpush
