@extends(client_layout('auth', 'frontend'))

@section('title', 'Set new password')

@push('auth_form')
    <div class="ota-auth-flow ota-auth-flow--reset">
        <header class="ota-auth-flow__header">
            <h2>Set a new password</h2>
            <p class="ota-auth-help">Create a secure password to continue managing your bookings and account.</p>
        </header>

        <form method="POST" action="{{ route('password.store') }}" class="ota-auth-flow__form">
            @csrf
            <input type="hidden" name="token" value="{{ $request->route('token') }}">

            <div class="ota-auth-group">
                <label class="ota-auth-label" for="email">Email</label>
                <input id="email" class="ota-auth-input" type="email" name="email" value="{{ old('email', $request->email) }}" required autofocus autocomplete="username">
                @error('email')<div class="ota-auth-error">{{ $message }}</div>@enderror
            </div>
            <div class="ota-auth-group">
                <label class="ota-auth-label" for="password">Password</label>
                <input id="password" class="ota-auth-input" type="password" name="password" required autocomplete="new-password">
                <p class="ota-auth-field-hint">Use a strong password that is not used on another account.</p>
                @error('password')<div class="ota-auth-error">{{ $message }}</div>@enderror
            </div>
            <div class="ota-auth-group">
                <label class="ota-auth-label" for="password_confirmation">Confirm password</label>
                <input id="password_confirmation" class="ota-auth-input" type="password" name="password_confirmation" required autocomplete="new-password">
                @error('password_confirmation')<div class="ota-auth-error">{{ $message }}</div>@enderror
            </div>
            <button class="ota-auth-btn" type="submit">Reset password</button>
        </form>

        <nav class="ota-auth-flow__footer" aria-label="Password reset options">
            <a class="ota-auth-link" href="{{ client_route('login') }}">Back to login</a>
        </nav>
    </div>
@endpush
