@extends(client_layout('mobile-app', 'mobile'))

@section('title', 'Edit agency details')

@section('mobile_app_title', 'Edit agency')

@section('mobile_app_back')
    <a href="{{ route('agent.agency.show') }}" class="ota-mobile-app__back-btn" aria-label="Back to agency profile">
        <svg viewBox="0 0 24 24" width="22" height="22" fill="currentColor" aria-hidden="true"><path d="M20 11H7.83l5.59-5.59L12 4l-8 8 8 8 1.41-1.41L7.83 13H20v-2z"/></svg>
    </a>
@endsection

@section('content')
    @php $d = $details ?? []; @endphp

    <div class="ota-mobile-agent" data-testid="ota-mobile-agent-agency-edit">
        <div class="ota-mobile-agent__card ota-mobile-agent__form-card">
            <h1 class="ota-mobile-agent__page-title">Edit agency details</h1>

            <form method="post" action="{{ route('agent.agency.update') }}" enctype="multipart/form-data" class="ota-mobile-agent__form" data-testid="agent-agency-edit-form">
                @csrf
                @method('PATCH')

                <fieldset class="ota-mobile-agent__fieldset">
                    <legend class="ota-mobile-agent__fieldset-title">Agency identity</legend>
                    <div class="ota-mobile-agent__field">
                        <label class="ota-mobile-agent__label" for="agency_name">Agency / business name</label>
                        <input type="text" name="agency_name" id="agency_name" class="ota-mobile-agent__input{{ $errors->has('agency_name') ? ' is-invalid' : '' }}" value="{{ old('agency_name', $d['agency_name'] ?? '') }}" required maxlength="160">
                        @error('agency_name')<p class="ota-mobile-agent__error">{{ $message }}</p>@enderror
                    </div>
                    <div class="ota-mobile-agent__field">
                        <label class="ota-mobile-agent__label" for="license_number">License / registration number</label>
                        <input type="text" name="license_number" id="license_number" class="ota-mobile-agent__input" value="{{ old('license_number', $d['license_number'] ?? '') }}" maxlength="80">
                    </div>
                    @if (! empty($d['can_set_agency_prefix']))
                        <div class="ota-mobile-agent__field">
                            <label class="ota-mobile-agent__label" for="code_prefix">Agency code prefix</label>
                            <input type="text" name="code_prefix" id="code_prefix" class="ota-mobile-agent__input{{ $errors->has('code_prefix') ? ' is-invalid' : '' }}" value="{{ old('code_prefix', $d['suggested_agency_prefix'] ?? '') }}" maxlength="4" pattern="[A-Z0-9]{2,4}">
                            <p class="ota-mobile-agent__note">Set once. Suggested: {{ $d['suggested_agency_prefix'] ?? 'AG' }}</p>
                            @error('code_prefix')<p class="ota-mobile-agent__error">{{ $message }}</p>@enderror
                        </div>
                    @elseif (! empty($d['agency_prefix']))
                        <div class="ota-mobile-agent__field">
                            <span class="ota-mobile-agent__label">Agency code prefix</span>
                            <p class="ota-mobile-agent__note">{{ $d['agency_prefix'] }}</p>
                        </div>
                    @endif
                </fieldset>

                <fieldset class="ota-mobile-agent__fieldset">
                    <legend class="ota-mobile-agent__fieldset-title">Contact details</legend>
                    <div class="ota-mobile-agent__field">
                        <label class="ota-mobile-agent__label" for="email">Business email</label>
                        <input type="email" name="email" id="email" class="ota-mobile-agent__input{{ $errors->has('email') ? ' is-invalid' : '' }}" value="{{ old('email', $d['email'] ?? '') }}" maxlength="160">
                        <p class="ota-mobile-agent__note">Also used to sign in. Changing it updates your login email.</p>
                        @error('email')<p class="ota-mobile-agent__error">{{ $message }}</p>@enderror
                    </div>
                    <div class="ota-mobile-agent__field">
                        <label class="ota-mobile-agent__label" for="phone">Phone</label>
                        <input type="text" name="phone" id="phone" class="ota-mobile-agent__input" value="{{ old('phone', $d['phone'] ?? '') }}" maxlength="40">
                    </div>
                </fieldset>

                <fieldset class="ota-mobile-agent__fieldset">
                    <legend class="ota-mobile-agent__fieldset-title">Address</legend>
                    <div class="ota-mobile-agent__field">
                        <label class="ota-mobile-agent__label" for="city">City</label>
                        <input type="text" name="city" id="city" class="ota-mobile-agent__input" value="{{ old('city', $d['city'] ?? '') }}" maxlength="120">
                    </div>
                    <div class="ota-mobile-agent__field">
                        <label class="ota-mobile-agent__label" for="country">Country</label>
                        <input type="text" name="country" id="country" class="ota-mobile-agent__input" value="{{ old('country', $d['country'] ?? '') }}" maxlength="120">
                    </div>
                    <div class="ota-mobile-agent__field">
                        <label class="ota-mobile-agent__label" for="address">Office address</label>
                        <textarea name="address" id="address" rows="3" class="ota-mobile-agent__input" maxlength="500">{{ old('address', $d['address'] ?? '') }}</textarea>
                    </div>
                </fieldset>

                <fieldset class="ota-mobile-agent__fieldset">
                    <legend class="ota-mobile-agent__fieldset-title">Logo</legend>
                    @if (! empty($d['logo_url']))
                        <div class="ota-mobile-agent__agency-logo ota-mobile-agent__agency-logo--preview">
                            <img src="{{ $d['logo_url'] }}" alt="" width="72" height="72" loading="lazy" decoding="async">
                        </div>
                    @endif
                    <div class="ota-mobile-agent__field">
                        <label class="ota-mobile-agent__label" for="logo">Upload logo</label>
                        <input type="file" name="logo" id="logo" class="ota-mobile-agent__input{{ $errors->has('logo') ? ' is-invalid' : '' }}" accept="image/*">
                        <p class="ota-mobile-agent__note">JPG or PNG up to 2 MB. Leave empty to keep current logo.</p>
                        @error('logo')<p class="ota-mobile-agent__error">{{ $message }}</p>@enderror
                    </div>
                </fieldset>

                <button type="submit" class="ota-mobile-agent__btn ota-mobile-agent__btn--primary ota-mobile-agent__btn--block">Save changes</button>
                <a href="{{ route('agent.agency.show') }}" class="ota-mobile-agent__btn ota-mobile-agent__btn--secondary ota-mobile-agent__btn--block">Cancel</a>
            </form>
        </div>
    </div>
@endsection
