@php
    use App\Enums\AccountType;

    $profile = $userProfile ?? $user->profile;
    $avatarUrl = $user->avatarUrl();
    $initials = $user->displayInitials();
    $genders = ['M' => 'Male', 'F' => 'Female', 'X' => 'Unspecified'];
    $roleLabel = $user->account_type ? match ($user->account_type) {
        AccountType::PlatformAdmin => 'Platform admin',
        AccountType::AgencyAdmin => 'Agency admin',
        AccountType::Staff => 'Staff',
        AccountType::Agent => 'Agent',
        AccountType::Customer => 'Customer',
        default => null,
    } : null;
@endphp

@if (session('status') === 'profile-updated')
    <div class="ota-profile-alert alert alert-success" role="status">Profile saved successfully.</div>
@endif

<div class="ota-profile-wrap">
    <form method="post" action="{{ route('profile.update') }}" enctype="multipart/form-data" class="ota-profile-form">
        @csrf
        @method('patch')

        <header class="ota-profile-hero ota-profile-summary-card">
            <div class="ota-profile-hero__avatar-wrap">
                <div class="ota-profile-hero__avatar" id="ota-profile-avatar-preview" aria-hidden="true">
                    @if ($avatarUrl)
                        <img src="{{ $avatarUrl }}" alt="" width="120" height="120" loading="lazy" decoding="async">
                    @else
                        <span class="ota-profile-hero__initials">{{ $initials }}</span>
                    @endif
                </div>
            </div>
            <div class="ota-profile-hero__body">
                <div class="ota-profile-hero__title-row">
                    <h2 class="ota-profile-hero__name">{{ $user->name }}</h2>
                    @if ($roleLabel)
                        <span class="ota-profile-role-badge">{{ $roleLabel }}</span>
                    @endif
                </div>
                <p class="ota-profile-hero__email">{{ $user->email }}</p>
                <p class="ota-profile-hero__note">These details help prefill future bookings.</p>
                <div class="ota-profile-hero__upload">
                    <label for="profile_photo" class="ota-profile-upload-btn">Upload new photo</label>
                    <input type="file" class="ota-profile-file-input @error('profile_photo') is-invalid @enderror" id="profile_photo" name="profile_photo" accept="image/jpeg,image/png,image/webp,image/gif" data-ota-profile-file-input>
                    <p class="ota-profile-file-hint">JPG, PNG, or WebP. Max 2 MB.</p>
                    <p class="ota-profile-file-name" id="ota-profile-file-name" data-ota-profile-file-name aria-live="polite"></p>
                    @error('profile_photo')<div class="ota-profile-field-error">{{ $message }}</div>@enderror
                    @if ($profile?->profile_photo_path)
                        <label class="ota-profile-check">
                            <input type="checkbox" class="ota-profile-check-input" name="remove_profile_photo" value="1" @checked(old('remove_profile_photo'))>
                            <span class="ota-profile-check-label">Remove current photo</span>
                        </label>
                    @endif
                </div>
            </div>
            @isset($dashboardUrl)
                <div class="ota-profile-hero__actions">
                    <a href="{{ $dashboardUrl }}" class="ota-btn ota-btn-ghost ota-profile-back-btn">Back to dashboard</a>
                </div>
            @endisset
        </header>

        <div class="ota-profile-grid ota-profile-grid--single">
            <div class="ota-profile-main">
                <section class="ota-profile-section" aria-labelledby="ota-profile-personal-heading">
                    <div class="ota-profile-section-header">
                        <h3 class="ota-profile-section-title" id="ota-profile-personal-heading">Personal information</h3>
                        <p class="ota-profile-section-lead">Your legal name and identity details used on bookings.</p>
                    </div>
                    <div class="ota-profile-form-grid">
                        <div class="ota-profile-field ota-profile-field--full">
                            <label class="ota-profile-label" for="name">Full name</label>
                            <input type="text" class="ota-profile-input form-control @error('name') is-invalid @enderror" id="name" name="name" value="{{ old('name', $user->name) }}" required autocomplete="name">
                            @error('name')<div class="ota-profile-field-error">{{ $message }}</div>@enderror
                        </div>
                        <div class="ota-profile-field ota-profile-field--full">
                            <label class="ota-profile-label" for="email">Email</label>
                            <input type="email" class="ota-profile-input form-control @error('email') is-invalid @enderror" id="email" name="email" value="{{ old('email', $user->email) }}" required autocomplete="username">
                            @error('email')<div class="ota-profile-field-error">{{ $message }}</div>@enderror
                            @if ($user instanceof \Illuminate\Contracts\Auth\MustVerifyEmail && ! $user->hasVerifiedEmail())
                                <p class="ota-profile-field-hint">
                                    Email unverified.
                                    <button form="send-verification" type="submit" class="ota-profile-link-btn">Resend verification</button>
                                </p>
                            @endif
                        </div>
                        <div class="ota-profile-field ota-profile-field--full">
                            <label class="ota-profile-label" for="username">Username</label>
                            <input type="text" class="ota-profile-input form-control @error('username') is-invalid @enderror" id="username" name="username" value="{{ old('username', $user->username) }}" required autocomplete="nickname" autocapitalize="off" spellcheck="false">
                            @error('username')<div class="ota-profile-field-error">{{ $message }}</div>@enderror
                            <p class="ota-profile-field-hint">You can use this username or your email address to sign in.</p>
                        </div>
                        <div class="ota-profile-field">
                            <label class="ota-profile-label" for="date_of_birth">Date of birth</label>
                            <input type="date" class="ota-profile-input form-control @error('date_of_birth') is-invalid @enderror" id="date_of_birth" name="date_of_birth" value="{{ old('date_of_birth', $profile?->date_of_birth?->format('Y-m-d')) }}">
                            @error('date_of_birth')<div class="ota-profile-field-error">{{ $message }}</div>@enderror
                        </div>
                        <div class="ota-profile-field">
                            <label class="ota-profile-label" for="gender">Gender</label>
                            <select class="ota-profile-select form-select @error('gender') is-invalid @enderror" id="gender" name="gender">
                                <option value="">Select</option>
                                @foreach ($genders as $code => $label)
                                    <option value="{{ $code }}" @selected(old('gender', $profile?->gender) === $code)>{{ $label }}</option>
                                @endforeach
                            </select>
                            @error('gender')<div class="ota-profile-field-error">{{ $message }}</div>@enderror
                        </div>
                        <div class="ota-profile-field">
                            <label class="ota-profile-label" for="nationality">Nationality</label>
                            <select class="ota-profile-select form-select @error('nationality') is-invalid @enderror" id="nationality" name="nationality">
                                <option value="">Select country</option>
                                @foreach ($countries as $country)
                                    <option value="{{ $country['code'] }}" @selected(strtoupper((string) old('nationality', $profile?->nationality ?? '')) === $country['code'])>{{ $country['name'] }} ({{ $country['code'] }})</option>
                                @endforeach
                            </select>
                            @error('nationality')<div class="ota-profile-field-error">{{ $message }}</div>@enderror
                        </div>
                    </div>
                </section>

                <section class="ota-profile-section" aria-labelledby="ota-profile-contact-heading">
                    <div class="ota-profile-section-header">
                        <h3 class="ota-profile-section-title" id="ota-profile-contact-heading">Contact details</h3>
                        <p class="ota-profile-section-lead">How we reach you for booking updates and support.</p>
                    </div>
                    <div class="ota-profile-form-grid">
                        <div class="ota-profile-field">
                            <label class="ota-profile-label" for="phone">Phone</label>
                            <input type="tel" class="ota-profile-input form-control @error('phone') is-invalid @enderror" id="phone" name="phone" value="{{ old('phone', $profile?->phone) }}" autocomplete="tel">
                            @error('phone')<div class="ota-profile-field-error">{{ $message }}</div>@enderror
                        </div>
                        <div class="ota-profile-field">
                            <label class="ota-profile-label" for="whatsapp">WhatsApp</label>
                            <input type="tel" class="ota-profile-input form-control @error('whatsapp') is-invalid @enderror" id="whatsapp" name="whatsapp" value="{{ old('whatsapp', $profile?->whatsapp) }}">
                            @error('whatsapp')<div class="ota-profile-field-error">{{ $message }}</div>@enderror
                        </div>
                        <div class="ota-profile-field">
                            <label class="ota-profile-label" for="country_code">Country</label>
                            <select class="ota-profile-select form-select @error('country_code') is-invalid @enderror" id="country_code" name="country_code">
                                <option value="">Select country</option>
                                @foreach ($countries as $country)
                                    <option value="{{ $country['code'] }}" @selected(strtoupper((string) old('country_code', $profile?->country_code ?? '')) === $country['code'])>{{ $country['name'] }} ({{ $country['code'] }})</option>
                                @endforeach
                            </select>
                            @error('country_code')<div class="ota-profile-field-error">{{ $message }}</div>@enderror
                        </div>
                        <div class="ota-profile-field">
                            <label class="ota-profile-label" for="city">City</label>
                            <input type="text" class="ota-profile-input form-control @error('city') is-invalid @enderror" id="city" name="city" value="{{ old('city', $profile?->city) }}" autocomplete="address-level2">
                            @error('city')<div class="ota-profile-field-error">{{ $message }}</div>@enderror
                        </div>
                    </div>
                </section>

                <section class="ota-profile-section" aria-labelledby="ota-profile-travel-heading">
                    <div class="ota-profile-section-header">
                        <h3 class="ota-profile-section-title" id="ota-profile-travel-heading">Travel documents</h3>
                        <p class="ota-profile-section-lead">Passport and ID details speed up passenger forms.</p>
                    </div>
                    <div class="ota-profile-form-grid">
                        <div class="ota-profile-field">
                            <label class="ota-profile-label" for="passport_number">Passport number</label>
                            <input type="text" class="ota-profile-input form-control @error('passport_number') is-invalid @enderror" id="passport_number" name="passport_number" value="{{ old('passport_number', $profile?->passport_number) }}" autocomplete="off">
                            @error('passport_number')<div class="ota-profile-field-error">{{ $message }}</div>@enderror
                        </div>
                        <div class="ota-profile-field">
                            <label class="ota-profile-label" for="passport_issuing_country">Passport issuing country</label>
                            <select class="ota-profile-select form-select @error('passport_issuing_country') is-invalid @enderror" id="passport_issuing_country" name="passport_issuing_country">
                                <option value="">Select country</option>
                                @foreach ($countries as $country)
                                    <option value="{{ $country['code'] }}" @selected(strtoupper((string) old('passport_issuing_country', $profile?->passport_issuing_country ?? '')) === $country['code'])>{{ $country['name'] }} ({{ $country['code'] }})</option>
                                @endforeach
                            </select>
                            @error('passport_issuing_country')<div class="ota-profile-field-error">{{ $message }}</div>@enderror
                        </div>
                        <div class="ota-profile-field">
                            <label class="ota-profile-label" for="passport_expiry_date">Passport expiry</label>
                            <input type="date" class="ota-profile-input form-control @error('passport_expiry_date') is-invalid @enderror" id="passport_expiry_date" name="passport_expiry_date" value="{{ old('passport_expiry_date', $profile?->passport_expiry_date?->format('Y-m-d')) }}">
                            @error('passport_expiry_date')<div class="ota-profile-field-error">{{ $message }}</div>@enderror
                        </div>
                        <div class="ota-profile-field ota-profile-field--full">
                            <label class="ota-profile-label" for="national_id">National ID / CNIC <span class="ota-profile-label-optional">(optional)</span></label>
                            <input type="text" class="ota-profile-input form-control @error('national_id') is-invalid @enderror" id="national_id" name="national_id" value="{{ old('national_id', $profile?->national_id) }}" autocomplete="off">
                            @error('national_id')<div class="ota-profile-field-error">{{ $message }}</div>@enderror
                        </div>
                    </div>
                </section>

                <section class="ota-profile-section" aria-labelledby="ota-profile-emergency-heading">
                    <div class="ota-profile-section-header">
                        <h3 class="ota-profile-section-title" id="ota-profile-emergency-heading">Emergency contact</h3>
                        <p class="ota-profile-section-lead">Someone we can contact if needed during your trip.</p>
                    </div>
                    <div class="ota-profile-form-grid">
                        <div class="ota-profile-field">
                            <label class="ota-profile-label" for="emergency_contact_name">Contact name</label>
                            <input type="text" class="ota-profile-input form-control @error('emergency_contact_name') is-invalid @enderror" id="emergency_contact_name" name="emergency_contact_name" value="{{ old('emergency_contact_name', $profile?->emergency_contact_name) }}">
                            @error('emergency_contact_name')<div class="ota-profile-field-error">{{ $message }}</div>@enderror
                        </div>
                        <div class="ota-profile-field">
                            <label class="ota-profile-label" for="emergency_contact_phone">Contact phone</label>
                            <input type="tel" class="ota-profile-input form-control @error('emergency_contact_phone') is-invalid @enderror" id="emergency_contact_phone" name="emergency_contact_phone" value="{{ old('emergency_contact_phone', $profile?->emergency_contact_phone) }}">
                            @error('emergency_contact_phone')<div class="ota-profile-field-error">{{ $message }}</div>@enderror
                        </div>
                    </div>
                </section>

                <div class="ota-profile-section-actions ota-r-action-bar">
                    <button type="submit" class="ota-profile-save-btn ota-btn ota-btn-primary">Save profile</button>
                </div>
            </div>
        </div>
    </form>

    <form id="send-verification" method="post" action="{{ route('verification.send') }}" class="d-none">
        @csrf
    </form>

    @include('profile.partials.update-password-form')
    @include('profile.partials.link-social-accounts')
    @include('profile.partials.delete-user-form')
</div>

@push('scripts')
<script>
(function () {
    var input = document.querySelector('[data-ota-profile-file-input]') || document.getElementById('profile_photo');
    var preview = document.getElementById('ota-profile-avatar-preview');
    var fileNameEl = document.querySelector('[data-ota-profile-file-name]') || document.getElementById('ota-profile-file-name');
    if (!input) return;

    function setPreview(src) {
        var imgHtml = '<img src="' + src + '" alt="" width="120" height="120">';
        if (preview) preview.innerHTML = imgHtml;
    }

    input.addEventListener('change', function () {
        var file = input.files && input.files[0];
        if (fileNameEl) fileNameEl.textContent = file ? file.name : '';
        if (!file || !file.type.match(/^image\//)) return;
        var reader = new FileReader();
        reader.onload = function (e) { setPreview(e.target.result); };
        reader.readAsDataURL(file);
    });
})();
</script>
@endpush
