@extends(client_layout('agent-portal', 'agent'))

@section('title', 'Edit agency details')

@section('account_title', 'Edit agency details')
@section('account_subtitle', 'Update your agency information used for bookings and account verification.')

@section('account_actions')
    <a href="{{ route('agent.agency.show') }}" class="ota-account-btn ota-account-btn--secondary">Cancel</a>
@endsection

@section('account_content')
    @php $d = $details ?? []; @endphp

    <div class="ota-account-card ota-account-form-card ota-agent-agency-form" data-testid="agent-agency-edit-form-card">
        <div class="ota-account-card__body">
            <form method="post" action="{{ route('agent.agency.update') }}" enctype="multipart/form-data" data-testid="agent-agency-edit-form">
                @csrf
                @method('PATCH')

                <fieldset class="ota-agent-form-section ota-agent-form-section--card">
                    <legend class="ota-agent-form-section__title">Agency identity</legend>
                    <div class="ota-agent-form-grid ota-agent-form-grid--identity">
                        <div class="ota-agent-field">
                            <label class="form-label" for="agency_name">Agency / business name</label>
                            <input type="text" name="agency_name" id="agency_name" class="form-control @error('agency_name') is-invalid @enderror" value="{{ old('agency_name', $d['agency_name'] ?? '') }}" required maxlength="160">
                            @error('agency_name')<div class="invalid-feedback">{{ $message }}</div>@enderror
                        </div>
                        <div class="ota-agent-field">
                            <label class="form-label" for="license_number">License / registration number</label>
                            <input type="text" name="license_number" id="license_number" class="form-control @error('license_number') is-invalid @enderror" value="{{ old('license_number', $d['license_number'] ?? '') }}" maxlength="80">
                            @error('license_number')<div class="invalid-feedback">{{ $message }}</div>@enderror
                        </div>
                        @if (! empty($d['can_set_agency_prefix']))
                            <div class="ota-agent-field">
                                <label class="form-label" for="code_prefix">Agency code prefix</label>
                                <input type="text" name="code_prefix" id="code_prefix" class="form-control @error('code_prefix') is-invalid @enderror" value="{{ old('code_prefix', $d['suggested_agency_prefix'] ?? '') }}" maxlength="4" pattern="[A-Z0-9]{2,4}">
                                <div class="form-text">Set once. Suggested: {{ $d['suggested_agency_prefix'] ?? 'AG' }}</div>
                                @error('code_prefix')<div class="invalid-feedback">{{ $message }}</div>@enderror
                            </div>
                        @elseif (! empty($d['agency_prefix']))
                            <div class="ota-agent-field">
                                <label class="form-label">Agency code prefix</label>
                                <div class="form-control-plaintext">{{ $d['agency_prefix'] }}</div>
                            </div>
                        @endif
                    </div>
                </fieldset>

                <fieldset class="ota-agent-form-section ota-agent-form-section--card">
                    <legend class="ota-agent-form-section__title">Contact details</legend>
                    <div class="ota-agent-form-grid">
                        <div class="ota-agent-field">
                            <label class="form-label" for="email">Business email</label>
                            <input type="email" name="email" id="email" class="form-control @error('email') is-invalid @enderror" value="{{ old('email', $d['email'] ?? '') }}" maxlength="160">
                            <div class="form-text">This address is also used to sign in to the agent portal. Changing it updates your login email.</div>
                            @error('email')<div class="invalid-feedback">{{ $message }}</div>@enderror
                        </div>
                        <div class="ota-agent-field">
                            <label class="form-label" for="phone">Phone</label>
                            <input type="text" name="phone" id="phone" class="form-control @error('phone') is-invalid @enderror" value="{{ old('phone', $d['phone'] ?? '') }}" maxlength="40">
                            @error('phone')<div class="invalid-feedback">{{ $message }}</div>@enderror
                        </div>
                    </div>
                </fieldset>

                <fieldset class="ota-agent-form-section ota-agent-form-section--card">
                    <legend class="ota-agent-form-section__title">Address</legend>
                    <div class="ota-agent-form-grid">
                        <div class="ota-agent-field">
                            <label class="form-label" for="city">City</label>
                            <input type="text" name="city" id="city" class="form-control @error('city') is-invalid @enderror" value="{{ old('city', $d['city'] ?? '') }}" maxlength="120">
                            @error('city')<div class="invalid-feedback">{{ $message }}</div>@enderror
                        </div>
                        <div class="ota-agent-field">
                            <label class="form-label" for="country">Country</label>
                            <input type="text" name="country" id="country" class="form-control @error('country') is-invalid @enderror" value="{{ old('country', $d['country'] ?? '') }}" maxlength="120">
                            @error('country')<div class="invalid-feedback">{{ $message }}</div>@enderror
                        </div>
                        <div class="ota-agent-field ota-agent-field--full">
                            <label class="form-label" for="address">Office address</label>
                            <textarea name="address" id="address" class="form-control @error('address') is-invalid @enderror" rows="3" maxlength="500">{{ old('address', $d['address'] ?? '') }}</textarea>
                            @error('address')<div class="invalid-feedback">{{ $message }}</div>@enderror
                        </div>
                    </div>
                </fieldset>

                <fieldset class="ota-agent-form-section ota-agent-form-section--card mb-0">
                    <legend class="ota-agent-form-section__title">Logo</legend>
                    <div class="ota-agent-logo-upload">
                        <div class="ota-agent-agency-logo-preview">
                            @if (! empty($d['logo_url']))
                                <img src="{{ $d['logo_url'] }}" alt="" width="72" height="72" loading="lazy" decoding="async">
                            @else
                                <span class="ota-agent-agency-profile__logo-placeholder"><i class="ti ti-building-store"></i></span>
                            @endif
                        </div>
                        <div class="ota-agent-logo-upload__field ota-agent-field">
                            <label class="form-label" for="logo">Upload logo</label>
                            <input type="file" name="logo" id="logo" class="form-control @error('logo') is-invalid @enderror" accept="image/*">
                            <div class="form-text">JPG or PNG up to 2 MB. Leave empty to keep the current logo.</div>
                            @error('logo')<div class="invalid-feedback">{{ $message }}</div>@enderror
                        </div>
                    </div>
                </fieldset>

                <div class="ota-agent-form-actions">
                    <button type="submit" class="ota-account-btn ota-account-btn--primary">Save changes</button>
                    <a href="{{ route('agent.agency.show') }}" class="ota-account-btn ota-account-btn--secondary">Cancel</a>
                </div>
            </form>
        </div>
    </div>
@endsection
