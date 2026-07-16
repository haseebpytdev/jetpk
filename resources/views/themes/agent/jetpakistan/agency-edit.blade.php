{{-- JP-PORTAL-3 TASK 6 · Agent agency — edit (JetPK theme)
     Resolved by client_view('agency-edit', 'agent'); dashboard.agent.agency-edit remains the
     fallback for default/Parwaaz clients and is NOT modified.
     Route gate: agent.permission:AgencyEdit (both GET /agency/edit and PATCH /agency).
     Agent Staff without AgencyEdit never reaches this view — the restriction is unchanged.

     PRESERVED EXACTLY:
       • controller var: $details  ($d = $details ?? [])
       • form: method="post" + @method('PATCH') action=route('agent.agency.update'),
         enctype="multipart/form-data"  (REQUIRED — logo upload breaks without it), @csrf
       • field names: agency_name, license_number, code_prefix, email, phone, city, country,
         address, logo
       • agency_name: required, maxlength="160", old('agency_name', $d['agency_name'] ?? '')
       • license_number: optional, maxlength="80"
       • code_prefix three-way branch reproduced exactly:
           - if ($d['can_set_agency_prefix'])   -> editable input, maxlength="4",
             pattern="[A-Z0-9]{2,4}", defaulted to old('code_prefix', $d['suggested_agency_prefix'])
             + help "Set once. Suggested: {suggested ?? 'AG'}"
           - elseif ($d['agency_prefix'])       -> READ-ONLY plaintext display, NO input
           - else                              -> field omitted entirely
         The read-only branch must NOT become an input: the prefix is set-once.
       • email: maxlength="160" + the login-email warning copy, verbatim
       • phone maxlength="40"; city/country maxlength="120"; address textarea rows="3" maxlength="500"
       • logo: type="file" accept="image/*" + preview + "JPG or PNG up to 2 MB. Leave empty to
         keep the current logo."
       • every @error block present in legacy; none added where legacy has none
       • actions: "Save changes" + "Cancel" -> agent.agency.show (header Cancel also retained)
       • data-testids: agent-agency-edit-form-card, agent-agency-edit-form
     Validation lives in the controller/FormRequest and is untouched.
--}}
@extends(client_layout('agent-portal', 'agent'))

@section('title', 'Edit agency details')

@section('account_title', 'Edit agency details')
@section('account_subtitle', 'Update your agency information used for bookings and account verification.')

@section('account_actions')
    <a href="{{ route('agent.agency.show') }}" class="jp-btn jp-btn--ghost">Cancel</a>
@endsection

@section('account_content')
    @php $d = $details ?? []; @endphp

    <x-dashboard.breadcrumbs :items="[
        ['label' => 'Dashboard', 'href' => client_route('agent.dashboard')],
        ['label' => 'Agency details', 'href' => route('agent.agency.show')],
        ['label' => 'Edit'],
    ]" />

    <x-jp.card class="jp-portal__panel" data-testid="agent-agency-edit-form-card">
        <form method="post" action="{{ route('agent.agency.update') }}" enctype="multipart/form-data" data-testid="agent-agency-edit-form" class="jp-form">
            @csrf
            @method('PATCH')

            <fieldset class="jp-form__section">
                <legend class="jp-form__section-title">Agency identity</legend>
                <div class="jp-form__grid jp-form__grid--2">
                    <div class="jp-field">
                        <label class="jp-label" for="agency_name">Agency / business name</label>
                        <input type="text" name="agency_name" id="agency_name" class="jp-input @error('agency_name') is-invalid @enderror" value="{{ old('agency_name', $d['agency_name'] ?? '') }}" required maxlength="160">
                        @error('agency_name')<p class="jp-field__error">{{ $message }}</p>@enderror
                    </div>

                    <div class="jp-field">
                        <label class="jp-label" for="license_number">License / registration number</label>
                        <input type="text" name="license_number" id="license_number" class="jp-input @error('license_number') is-invalid @enderror" value="{{ old('license_number', $d['license_number'] ?? '') }}" maxlength="80">
                        @error('license_number')<p class="jp-field__error">{{ $message }}</p>@enderror
                    </div>

                    @if (! empty($d['can_set_agency_prefix']))
                        <div class="jp-field">
                            <label class="jp-label" for="code_prefix">Agency code prefix</label>
                            <input type="text" name="code_prefix" id="code_prefix" class="jp-input @error('code_prefix') is-invalid @enderror" value="{{ old('code_prefix', $d['suggested_agency_prefix'] ?? '') }}" maxlength="4" pattern="[A-Z0-9]{2,4}">
                            <p class="jp-field__help">Set once. Suggested: {{ $d['suggested_agency_prefix'] ?? 'AG' }}</p>
                            @error('code_prefix')<p class="jp-field__error">{{ $message }}</p>@enderror
                        </div>
                    @elseif (! empty($d['agency_prefix']))
                        <div class="jp-field">
                            <label class="jp-label">Agency code prefix</label>
                            <p class="jp-field__plaintext">{{ $d['agency_prefix'] }}</p>
                        </div>
                    @endif
                </div>
            </fieldset>

            <fieldset class="jp-form__section">
                <legend class="jp-form__section-title">Contact details</legend>
                <div class="jp-form__grid jp-form__grid--2">
                    <div class="jp-field">
                        <label class="jp-label" for="email">Business email</label>
                        <input type="email" name="email" id="email" class="jp-input @error('email') is-invalid @enderror" value="{{ old('email', $d['email'] ?? '') }}" maxlength="160">
                        <p class="jp-field__help">This address is also used to sign in to the agent portal. Changing it updates your login email.</p>
                        @error('email')<p class="jp-field__error">{{ $message }}</p>@enderror
                    </div>

                    <div class="jp-field">
                        <label class="jp-label" for="phone">Phone</label>
                        <input type="text" name="phone" id="phone" class="jp-input @error('phone') is-invalid @enderror" value="{{ old('phone', $d['phone'] ?? '') }}" maxlength="40">
                        @error('phone')<p class="jp-field__error">{{ $message }}</p>@enderror
                    </div>
                </div>
            </fieldset>

            <fieldset class="jp-form__section">
                <legend class="jp-form__section-title">Address</legend>
                <div class="jp-form__grid jp-form__grid--2">
                    <div class="jp-field">
                        <label class="jp-label" for="city">City</label>
                        <input type="text" name="city" id="city" class="jp-input @error('city') is-invalid @enderror" value="{{ old('city', $d['city'] ?? '') }}" maxlength="120">
                        @error('city')<p class="jp-field__error">{{ $message }}</p>@enderror
                    </div>

                    <div class="jp-field">
                        <label class="jp-label" for="country">Country</label>
                        <input type="text" name="country" id="country" class="jp-input @error('country') is-invalid @enderror" value="{{ old('country', $d['country'] ?? '') }}" maxlength="120">
                        @error('country')<p class="jp-field__error">{{ $message }}</p>@enderror
                    </div>

                    <div class="jp-field jp-field--full">
                        <label class="jp-label" for="address">Office address</label>
                        <textarea name="address" id="address" class="jp-textarea @error('address') is-invalid @enderror" rows="3" maxlength="500">{{ old('address', $d['address'] ?? '') }}</textarea>
                        @error('address')<p class="jp-field__error">{{ $message }}</p>@enderror
                    </div>
                </div>
            </fieldset>

            <fieldset class="jp-form__section">
                <legend class="jp-form__section-title">Logo</legend>
                <div class="jp-portal__logo-upload">
                    <div class="jp-portal__identity-logo">
                        @if (! empty($d['logo_url']))
                            <img src="{{ $d['logo_url'] }}" alt="" width="72" height="72" loading="lazy" decoding="async">
                        @else
                            <span class="jp-portal__identity-logo-placeholder"><x-jp.icon name="building-store" /></span>
                        @endif
                    </div>
                    <div class="jp-field">
                        <label class="jp-label" for="logo">Upload logo</label>
                        <input type="file" name="logo" id="logo" class="jp-input @error('logo') is-invalid @enderror" accept="image/*">
                        <p class="jp-field__help">JPG or PNG up to 2 MB. Leave empty to keep the current logo.</p>
                        @error('logo')<p class="jp-field__error">{{ $message }}</p>@enderror
                    </div>
                </div>
            </fieldset>

            <div class="jp-form__actions">
                <button type="submit" class="jp-btn jp-btn--primary">Save changes</button>
                <a href="{{ route('agent.agency.show') }}" class="jp-btn jp-btn--ghost">Cancel</a>
            </div>
        </form>
    </x-jp.card>
@endsection
