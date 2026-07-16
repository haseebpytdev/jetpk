{{-- JetPK portal profile form — preserves profile.update contract; no ota-public.css. --}}
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
        AccountType::AgentStaff => 'Agency staff',
        AccountType::Customer => 'Customer',
        default => null,
    } : null;
@endphp

@if (session('status') === 'profile-updated')
    <div class="jp-portal-alert jp-portal-alert--info" role="status">Profile saved successfully.</div>
@endif

<div class="jp-portal-profile" data-testid="jp-portal-profile-settings">
    <form method="post" action="{{ route('profile.update') }}" enctype="multipart/form-data" class="jp-portal-profile__form">
        @csrf
        @method('patch')

        <div class="jp-portal-card jp-portal-profile__hero">
            <div class="jp-portal-card__body">
                <div class="jp-portal-profile__hero-grid">
                    <div class="jp-portal-profile__avatar" id="jp-profile-avatar-preview" aria-hidden="true">
                        @if ($avatarUrl)
                            <img src="{{ $avatarUrl }}" alt="" width="96" height="96" loading="lazy" decoding="async">
                        @else
                            <span class="jp-portal-profile__initials">{{ $initials }}</span>
                        @endif
                    </div>
                    <div>
                        <div class="jp-portal-profile__title-row">
                            <h2 class="jp-portal-profile__name">{{ $user->name }}</h2>
                            @if ($roleLabel)
                                <span class="jp-portal-badge">{{ $roleLabel }}</span>
                            @endif
                        </div>
                        <p class="jp-portal-profile__email">{{ $user->email }}</p>
                        <p class="jp-portal-profile__note">These details help prefill future bookings.</p>
                        <div class="jp-portal-profile__upload">
                            <label for="profile_photo" class="jp-portal-btn jp-portal-btn--ghost jp-portal-btn--sm">Upload photo</label>
                            <input type="file" class="jp-portal-profile__file @error('profile_photo') is-invalid @enderror" id="profile_photo" name="profile_photo" accept="image/jpeg,image/png,image/webp,image/gif" data-jp-profile-file-input>
                            <p class="jp-portal-profile__hint">JPG, PNG, or WebP. Max 2 MB.</p>
                            <p class="jp-portal-profile__file-name" id="jp-profile-file-name" data-jp-profile-file-name aria-live="polite"></p>
                            @error('profile_photo')<p class="jp-portal-profile__error">{{ $message }}</p>@enderror
                            @if ($profile?->profile_photo_path)
                                <label class="jp-portal-profile__check">
                                    <input type="checkbox" name="remove_profile_photo" value="1" @checked(old('remove_profile_photo'))>
                                    <span>Remove current photo</span>
                                </label>
                            @endif
                        </div>
                    </div>
                    @isset($dashboardUrl)
                        <div class="jp-portal-profile__back">
                            <a href="{{ $dashboardUrl }}" class="jp-portal-btn jp-portal-btn--ghost jp-portal-btn--sm">Back to dashboard</a>
                        </div>
                    @endisset
                </div>
            </div>
        </div>

        <div class="jp-portal-card">
            <div class="jp-portal-card__head"><h2 class="jp-portal-card__title">Personal information</h2></div>
            <div class="jp-portal-card__body">
                <div class="jp-portal-profile__grid">
                    <div class="jp-portal-profile__field jp-portal-profile__field--full">
                        <label class="jp-portal-profile__label" for="name">Full name</label>
                        <input type="text" class="jp-portal-profile__input @error('name') is-invalid @enderror" id="name" name="name" value="{{ old('name', $user->name) }}" required autocomplete="name">
                        @error('name')<p class="jp-portal-profile__error">{{ $message }}</p>@enderror
                    </div>
                    <div class="jp-portal-profile__field jp-portal-profile__field--full">
                        <label class="jp-portal-profile__label" for="email">Email</label>
                        <input type="email" class="jp-portal-profile__input @error('email') is-invalid @enderror" id="email" name="email" value="{{ old('email', $user->email) }}" required autocomplete="username">
                        @error('email')<p class="jp-portal-profile__error">{{ $message }}</p>@enderror
                        @if ($user instanceof \Illuminate\Contracts\Auth\MustVerifyEmail && ! $user->hasVerifiedEmail())
                            <p class="jp-portal-profile__hint">
                                Email unverified.
                                <button form="send-verification" type="submit" class="jp-portal-profile__link-btn">Resend verification</button>
                            </p>
                        @endif
                    </div>
                    <div class="jp-portal-profile__field jp-portal-profile__field--full">
                        <label class="jp-portal-profile__label" for="username">Username</label>
                        <input type="text" class="jp-portal-profile__input @error('username') is-invalid @enderror" id="username" name="username" value="{{ old('username', $user->username) }}" required autocomplete="nickname" autocapitalize="off" spellcheck="false">
                        @error('username')<p class="jp-portal-profile__error">{{ $message }}</p>@enderror
                    </div>
                    <div class="jp-portal-profile__field">
                        <label class="jp-portal-profile__label" for="date_of_birth">Date of birth</label>
                        <input type="date" class="jp-portal-profile__input @error('date_of_birth') is-invalid @enderror" id="date_of_birth" name="date_of_birth" value="{{ old('date_of_birth', $profile?->date_of_birth?->format('Y-m-d')) }}">
                        @error('date_of_birth')<p class="jp-portal-profile__error">{{ $message }}</p>@enderror
                    </div>
                    <div class="jp-portal-profile__field">
                        <label class="jp-portal-profile__label" for="gender">Gender</label>
                        <select class="jp-portal-profile__input @error('gender') is-invalid @enderror" id="gender" name="gender">
                            <option value="">Select</option>
                            @foreach ($genders as $code => $label)
                                <option value="{{ $code }}" @selected(old('gender', $profile?->gender) === $code)>{{ $label }}</option>
                            @endforeach
                        </select>
                        @error('gender')<p class="jp-portal-profile__error">{{ $message }}</p>@enderror
                    </div>
                    <div class="jp-portal-profile__field">
                        <label class="jp-portal-profile__label" for="nationality">Nationality</label>
                        <select class="jp-portal-profile__input @error('nationality') is-invalid @enderror" id="nationality" name="nationality">
                            <option value="">Select country</option>
                            @foreach ($countries as $country)
                                <option value="{{ $country['code'] }}" @selected(strtoupper((string) old('nationality', $profile?->nationality ?? '')) === $country['code'])>{{ $country['name'] }} ({{ $country['code'] }})</option>
                            @endforeach
                        </select>
                        @error('nationality')<p class="jp-portal-profile__error">{{ $message }}</p>@enderror
                    </div>
                </div>
            </div>
        </div>

        <div class="jp-portal-card">
            <div class="jp-portal-card__head"><h2 class="jp-portal-card__title">Contact details</h2></div>
            <div class="jp-portal-card__body">
                <div class="jp-portal-profile__grid">
                    <div class="jp-portal-profile__field">
                        <label class="jp-portal-profile__label" for="phone">Phone</label>
                        <input type="tel" class="jp-portal-profile__input @error('phone') is-invalid @enderror" id="phone" name="phone" value="{{ old('phone', $profile?->phone) }}" autocomplete="tel">
                        @error('phone')<p class="jp-portal-profile__error">{{ $message }}</p>@enderror
                    </div>
                    <div class="jp-portal-profile__field">
                        <label class="jp-portal-profile__label" for="whatsapp">WhatsApp</label>
                        <input type="tel" class="jp-portal-profile__input @error('whatsapp') is-invalid @enderror" id="whatsapp" name="whatsapp" value="{{ old('whatsapp', $profile?->whatsapp) }}">
                        @error('whatsapp')<p class="jp-portal-profile__error">{{ $message }}</p>@enderror
                    </div>
                    <div class="jp-portal-profile__field">
                        <label class="jp-portal-profile__label" for="country_code">Country</label>
                        <select class="jp-portal-profile__input @error('country_code') is-invalid @enderror" id="country_code" name="country_code">
                            <option value="">Select country</option>
                            @foreach ($countries as $country)
                                <option value="{{ $country['code'] }}" @selected(strtoupper((string) old('country_code', $profile?->country_code ?? '')) === $country['code'])>{{ $country['name'] }} ({{ $country['code'] }})</option>
                            @endforeach
                        </select>
                        @error('country_code')<p class="jp-portal-profile__error">{{ $message }}</p>@enderror
                    </div>
                    <div class="jp-portal-profile__field">
                        <label class="jp-portal-profile__label" for="city">City</label>
                        <input type="text" class="jp-portal-profile__input @error('city') is-invalid @enderror" id="city" name="city" value="{{ old('city', $profile?->city) }}" autocomplete="address-level2">
                        @error('city')<p class="jp-portal-profile__error">{{ $message }}</p>@enderror
                    </div>
                </div>
            </div>
        </div>

        <div class="jp-portal-card">
            <div class="jp-portal-card__head"><h2 class="jp-portal-card__title">Travel documents</h2></div>
            <div class="jp-portal-card__body">
                <div class="jp-portal-profile__grid">
                    <div class="jp-portal-profile__field">
                        <label class="jp-portal-profile__label" for="passport_number">Passport number</label>
                        <input type="text" class="jp-portal-profile__input @error('passport_number') is-invalid @enderror" id="passport_number" name="passport_number" value="{{ old('passport_number', $profile?->passport_number) }}" autocomplete="off">
                        @error('passport_number')<p class="jp-portal-profile__error">{{ $message }}</p>@enderror
                    </div>
                    <div class="jp-portal-profile__field">
                        <label class="jp-portal-profile__label" for="passport_issuing_country">Passport issuing country</label>
                        <select class="jp-portal-profile__input @error('passport_issuing_country') is-invalid @enderror" id="passport_issuing_country" name="passport_issuing_country">
                            <option value="">Select country</option>
                            @foreach ($countries as $country)
                                <option value="{{ $country['code'] }}" @selected(strtoupper((string) old('passport_issuing_country', $profile?->passport_issuing_country ?? '')) === $country['code'])>{{ $country['name'] }} ({{ $country['code'] }})</option>
                            @endforeach
                        </select>
                        @error('passport_issuing_country')<p class="jp-portal-profile__error">{{ $message }}</p>@enderror
                    </div>
                    <div class="jp-portal-profile__field">
                        <label class="jp-portal-profile__label" for="passport_expiry_date">Passport expiry</label>
                        <input type="date" class="jp-portal-profile__input @error('passport_expiry_date') is-invalid @enderror" id="passport_expiry_date" name="passport_expiry_date" value="{{ old('passport_expiry_date', $profile?->passport_expiry_date?->format('Y-m-d')) }}">
                        @error('passport_expiry_date')<p class="jp-portal-profile__error">{{ $message }}</p>@enderror
                    </div>
                    <div class="jp-portal-profile__field jp-portal-profile__field--full">
                        <label class="jp-portal-profile__label" for="national_id">National ID / CNIC <span class="jp-portal-profile__optional">(optional)</span></label>
                        <input type="text" class="jp-portal-profile__input @error('national_id') is-invalid @enderror" id="national_id" name="national_id" value="{{ old('national_id', $profile?->national_id) }}" autocomplete="off">
                        @error('national_id')<p class="jp-portal-profile__error">{{ $message }}</p>@enderror
                    </div>
                </div>
            </div>
        </div>

        <div class="jp-portal-card">
            <div class="jp-portal-card__head"><h2 class="jp-portal-card__title">Emergency contact</h2></div>
            <div class="jp-portal-card__body">
                <div class="jp-portal-profile__grid">
                    <div class="jp-portal-profile__field">
                        <label class="jp-portal-profile__label" for="emergency_contact_name">Contact name</label>
                        <input type="text" class="jp-portal-profile__input @error('emergency_contact_name') is-invalid @enderror" id="emergency_contact_name" name="emergency_contact_name" value="{{ old('emergency_contact_name', $profile?->emergency_contact_name) }}">
                        @error('emergency_contact_name')<p class="jp-portal-profile__error">{{ $message }}</p>@enderror
                    </div>
                    <div class="jp-portal-profile__field">
                        <label class="jp-portal-profile__label" for="emergency_contact_phone">Contact phone</label>
                        <input type="tel" class="jp-portal-profile__input @error('emergency_contact_phone') is-invalid @enderror" id="emergency_contact_phone" name="emergency_contact_phone" value="{{ old('emergency_contact_phone', $profile?->emergency_contact_phone) }}">
                        @error('emergency_contact_phone')<p class="jp-portal-profile__error">{{ $message }}</p>@enderror
                    </div>
                </div>
                <div class="jp-portal-profile__actions">
                    <button type="submit" class="jp-portal-btn jp-portal-btn--primary">Save profile</button>
                </div>
            </div>
        </div>
    </form>

    <form id="send-verification" method="post" action="{{ route('verification.send') }}" class="jp-portal-profile__hidden">
        @csrf
    </form>

    @include('themes.frontend.jetpakistan.components.portal.profile-password')
    @include('profile.partials.link-social-accounts')
    @include('themes.frontend.jetpakistan.components.portal.profile-delete')
</div>

@push('scripts')
<script>
(function () {
    var input = document.querySelector('[data-jp-profile-file-input]');
    var preview = document.getElementById('jp-profile-avatar-preview');
    var fileNameEl = document.querySelector('[data-jp-profile-file-name]');
    if (!input) return;
    input.addEventListener('change', function () {
        var file = input.files && input.files[0];
        if (fileNameEl) fileNameEl.textContent = file ? file.name : '';
        if (!file || !file.type.match(/^image\//)) return;
        var reader = new FileReader();
        reader.onload = function (e) {
            if (preview) preview.innerHTML = '<img src="' + e.target.result + '" alt="" width="96" height="96">';
        };
        reader.readAsDataURL(file);
    });
})();
</script>
@endpush
