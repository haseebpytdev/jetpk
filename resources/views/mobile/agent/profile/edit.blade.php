@extends(client_layout('mobile-app', 'mobile'))

@section('title', 'Account')

@section('mobile_app_title', 'Account')

@section('content')
    @php
        $profile = $userProfile ?? $user->profile;
    @endphp

    <div class="ota-mobile-agent" data-testid="ota-mobile-agent-profile">
        @if (session('status') === 'profile-updated')
            @include('mobile.components.alert', ['type' => 'success', 'message' => 'Profile saved successfully.'])
        @elseif (session('status') === 'password-updated')
            @include('mobile.components.alert', ['type' => 'success', 'message' => 'Password updated.'])
        @endif

        <section class="ota-mobile-agent__card">
            <h1 class="ota-mobile-agent__page-title">{{ $user->name }}</h1>
            <p class="ota-mobile-agent__note">{{ $user->email }}</p>
            @if (filled($profile?->phone))
                <p class="ota-mobile-agent__note">{{ $profile->phone }}</p>
            @endif
        </section>

        <section class="ota-mobile-agent__card">
            <h2 class="ota-mobile-agent__card-title">Profile details</h2>
            <form method="post" action="{{ route('profile.update') }}" class="ota-mobile-agent__form">
                @csrf
                @method('patch')
                <input type="hidden" name="username" value="{{ old('username', $user->username) }}">

                <div class="ota-mobile-agent__field">
                    <label class="ota-mobile-agent__label" for="name">Full name</label>
                    <input type="text" name="name" id="name" class="ota-mobile-agent__input{{ $errors->has('name') ? ' is-invalid' : '' }}" value="{{ old('name', $user->name) }}" required autocomplete="name">
                    @error('name')<p class="ota-mobile-agent__error">{{ $message }}</p>@enderror
                </div>

                <div class="ota-mobile-agent__field">
                    <label class="ota-mobile-agent__label" for="email">Email</label>
                    <input type="email" name="email" id="email" class="ota-mobile-agent__input{{ $errors->has('email') ? ' is-invalid' : '' }}" value="{{ old('email', $user->email) }}" required autocomplete="username">
                    @error('email')<p class="ota-mobile-agent__error">{{ $message }}</p>@enderror
                </div>

                <div class="ota-mobile-agent__field">
                    <label class="ota-mobile-agent__label" for="phone">Phone</label>
                    <input type="tel" name="phone" id="phone" class="ota-mobile-agent__input{{ $errors->has('phone') ? ' is-invalid' : '' }}" value="{{ old('phone', $profile?->phone) }}" autocomplete="tel">
                    @error('phone')<p class="ota-mobile-agent__error">{{ $message }}</p>@enderror
                </div>

                <button type="submit" class="ota-mobile-agent__btn ota-mobile-agent__btn--primary ota-mobile-agent__btn--block">Save profile</button>
            </form>
        </section>

        <section class="ota-mobile-agent__card">
            <h2 class="ota-mobile-agent__card-title">Password</h2>
            <form method="post" action="{{ route('password.update') }}" class="ota-mobile-agent__form">
                @csrf
                @method('put')
                <div class="ota-mobile-agent__field">
                    <label class="ota-mobile-agent__label" for="current_password">Current password</label>
                    <input type="password" name="current_password" id="current_password" class="ota-mobile-agent__input{{ $errors->has('current_password', 'updatePassword') ? ' is-invalid' : '' }}" autocomplete="current-password">
                    @error('current_password', 'updatePassword')<p class="ota-mobile-agent__error">{{ $message }}</p>@enderror
                </div>
                <div class="ota-mobile-agent__field">
                    <label class="ota-mobile-agent__label" for="password">New password</label>
                    <input type="password" name="password" id="password" class="ota-mobile-agent__input{{ $errors->has('password', 'updatePassword') ? ' is-invalid' : '' }}" autocomplete="new-password">
                    @error('password', 'updatePassword')<p class="ota-mobile-agent__error">{{ $message }}</p>@enderror
                </div>
                <div class="ota-mobile-agent__field">
                    <label class="ota-mobile-agent__label" for="password_confirmation">Confirm password</label>
                    <input type="password" name="password_confirmation" id="password_confirmation" class="ota-mobile-agent__input" autocomplete="new-password">
                </div>
                <button type="submit" class="ota-mobile-agent__btn ota-mobile-agent__btn--secondary ota-mobile-agent__btn--block">Update password</button>
            </form>
        </section>

        <p class="ota-mobile-agent__footer-note">
            <a href="{{ $dashboardUrl ?? route('agent.dashboard') }}" class="ota-mobile-agent__link">Back to dashboard</a>
        </p>

        <section class="ota-mobile-agent__card">
            <form method="post" action="{{ route('logout') }}" class="ota-mobile-agent__form">
                @csrf
                <button type="submit" class="ota-mobile-agent__btn ota-mobile-agent__btn--secondary ota-mobile-agent__btn--block" data-testid="mobile-agent-logout">Log out</button>
            </form>
        </section>
    </div>
@endsection
