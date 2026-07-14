@extends(client_layout('auth', 'frontend'))

@section('title', 'Confirm password')

@push('auth_form')
    <div class="ota-auth-flow ota-auth-flow--confirm">
        <header class="ota-auth-flow__header">
            <h2>Confirm your password</h2>
            <p class="ota-auth-help">For security, confirm your password before continuing to this account area.</p>
        </header>

        <form method="POST" action="{{ route('password.confirm') }}" class="ota-auth-flow__form">
            @csrf
            <div class="ota-auth-group">
                <label class="ota-auth-label" for="password">Password</label>
                <input id="password" class="ota-auth-input" type="password" name="password" required autocomplete="current-password">
                @error('password')<div class="ota-auth-error">{{ $message }}</div>@enderror
            </div>
            <button class="ota-auth-btn" type="submit">Confirm password</button>
        </form>
    </div>
@endpush
