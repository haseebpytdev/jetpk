@extends(client_layout('mobile-app', 'mobile'))

@section('title', 'Account')

@section('mobile_app_title', 'Account')

@section('content')
    @php
        $profile = $userProfile ?? $user->profile;
    @endphp

    <div class="ota-mobile-customer" data-testid="ota-mobile-customer-profile">
        @if (session('status') === 'profile-updated')
            @include('mobile.components.alert', ['type' => 'success', 'message' => 'Profile saved successfully.'])
        @elseif (session('status') === 'password-updated')
            @include('mobile.components.alert', ['type' => 'success', 'message' => 'Password updated.'])
        @elseif (session('status'))
            @include('mobile.components.alert', ['type' => 'success', 'message' => session('status')])
        @endif

        <section class="ota-mobile-customer__card ota-mobile-customer__profile-hero">
            <h1 class="ota-mobile-customer__page-title">{{ $user->name }}</h1>
            <p class="ota-mobile-customer__note ota-mobile-customer__text-safe">{{ $user->email }}</p>
            @if (filled($profile?->phone))
                <p class="ota-mobile-customer__note">{{ $profile->phone }}</p>
            @endif
        </section>

        <section class="ota-mobile-customer__card ota-mobile-customer__form-card">
            <h2 class="ota-mobile-customer__card-title">Profile details</h2>
            <form method="post" action="{{ route('profile.update') }}" class="ota-mobile-customer__form">
                @csrf
                @method('patch')
                <input type="hidden" name="username" value="{{ old('username', $user->username) }}">

                <div class="ota-mobile-customer__field">
                    <label class="ota-mobile-customer__label" for="name">Full name</label>
                    <input type="text" name="name" id="name" class="ota-mobile-customer__input{{ $errors->has('name') ? ' is-invalid' : '' }}" value="{{ old('name', $user->name) }}" required autocomplete="name">
                    @error('name')<p class="ota-mobile-customer__error">{{ $message }}</p>@enderror
                </div>

                <div class="ota-mobile-customer__field">
                    <label class="ota-mobile-customer__label" for="email">Email</label>
                    <input type="email" name="email" id="email" class="ota-mobile-customer__input{{ $errors->has('email') ? ' is-invalid' : '' }}" value="{{ old('email', $user->email) }}" required autocomplete="username">
                    @error('email')<p class="ota-mobile-customer__error">{{ $message }}</p>@enderror
                </div>

                <div class="ota-mobile-customer__field">
                    <label class="ota-mobile-customer__label" for="phone">Phone</label>
                    <input type="tel" name="phone" id="phone" class="ota-mobile-customer__input{{ $errors->has('phone') ? ' is-invalid' : '' }}" value="{{ old('phone', $profile?->phone) }}" autocomplete="tel">
                    @error('phone')<p class="ota-mobile-customer__error">{{ $message }}</p>@enderror
                </div>

                <button type="submit" class="ota-mobile-customer__btn ota-mobile-customer__btn--primary ota-mobile-customer__btn--block">Save profile</button>
            </form>
        </section>

        <section class="ota-mobile-customer__card ota-mobile-customer__form-card">
            <h2 class="ota-mobile-customer__card-title">Password</h2>
            <form method="post" action="{{ route('password.update') }}" class="ota-mobile-customer__form">
                @csrf
                @method('put')

                <div class="ota-mobile-customer__field">
                    <label class="ota-mobile-customer__label" for="current_password">Current password</label>
                    <input type="password" name="current_password" id="current_password" class="ota-mobile-customer__input{{ $errors->has('current_password', 'updatePassword') ? ' is-invalid' : '' }}" autocomplete="current-password">
                    @error('current_password', 'updatePassword')<p class="ota-mobile-customer__error">{{ $message }}</p>@enderror
                </div>

                <div class="ota-mobile-customer__field">
                    <label class="ota-mobile-customer__label" for="password">New password</label>
                    <input type="password" name="password" id="password" class="ota-mobile-customer__input{{ $errors->has('password', 'updatePassword') ? ' is-invalid' : '' }}" autocomplete="new-password">
                    @error('password', 'updatePassword')<p class="ota-mobile-customer__error">{{ $message }}</p>@enderror
                </div>

                <div class="ota-mobile-customer__field">
                    <label class="ota-mobile-customer__label" for="password_confirmation">Confirm password</label>
                    <input type="password" name="password_confirmation" id="password_confirmation" class="ota-mobile-customer__input" autocomplete="new-password">
                </div>

                <button type="submit" class="ota-mobile-customer__btn ota-mobile-customer__btn--secondary ota-mobile-customer__btn--block">Update password</button>
            </form>
        </section>

        <section class="ota-mobile-customer__card ota-mobile-customer__danger">
            <details class="ota-mobile-customer__danger-details">
                <summary class="ota-mobile-customer__danger-summary">Delete account</summary>
                <p class="ota-mobile-customer__note">Permanently delete your account and associated profile data. This cannot be undone.</p>
                <form method="post" action="{{ route('profile.destroy') }}" class="ota-mobile-customer__form">
                    @csrf
                    @method('delete')
                    <div class="ota-mobile-customer__field">
                        <label class="ota-mobile-customer__label" for="delete_password">Confirm with password</label>
                        <input type="password" name="password" id="delete_password" class="ota-mobile-customer__input{{ $errors->has('password', 'userDeletion') ? ' is-invalid' : '' }}" autocomplete="current-password">
                        @error('password', 'userDeletion')<p class="ota-mobile-customer__error">{{ $message }}</p>@enderror
                    </div>
                    <button type="submit" class="ota-mobile-customer__btn ota-mobile-customer__btn--danger ota-mobile-customer__btn--block" onclick="return confirm('Delete your account permanently?');">Delete my account</button>
                </form>
            </details>
        </section>

        <p class="ota-mobile-customer__footer-note">
            <a href="{{ $dashboardUrl ?? route('customer.dashboard') }}" class="ota-mobile-customer__link">Back to dashboard</a>
        </p>
    </div>
@endsection
