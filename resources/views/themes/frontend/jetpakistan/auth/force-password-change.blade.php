@extends('themes.frontend.jetpakistan.layouts.auth')

@section('title', 'Change your password')

@push('auth_form')
    <header class="jp-auth-form-head">
        <h2 class="jp-auth-form-title">Change your password</h2>
        <p class="jp-auth-form-lead">For security, set a new password before continuing to your account.</p>
    </header>

    @if (isset($errors) && $errors->any())
        <x-jp.alert variant="danger">
            <ul class="jp-auth-error-list">
                @foreach ($errors->all() as $error)
                    <li>{{ $error }}</li>
                @endforeach
            </ul>
        </x-jp.alert>
    @endif

    <form method="POST" action="{{ client_route('password.force.store') }}" class="jp-form jp-auth-form">
        @csrf
        <x-jp.form-group label="New password" for="password" :error="isset($errors) ? $errors->first('password') : null">
            <input id="password" class="jp-input @if(isset($errors) && $errors->has('password')) jp-input--invalid @endif" type="password" name="password" required autocomplete="new-password" autofocus>
        </x-jp.form-group>
        <x-jp.form-group label="Confirm password" for="password_confirmation">
            <input id="password_confirmation" class="jp-input" type="password" name="password_confirmation" required autocomplete="new-password">
        </x-jp.form-group>
        <x-jp.button type="submit" variant="primary" block>Save password</x-jp.button>
    </form>

    <form method="POST" action="{{ client_route('logout') }}" class="jp-auth-logout-form">
        @csrf
        <button type="submit" class="jp-auth-link-btn">Log out instead</button>
    </form>
@endpush

@push('styles')
<style>
    .jp-auth-error-list { margin: 0; padding-left: 1.1rem; }
    .jp-auth-logout-form { margin-top: 1rem; text-align: center; }
    .jp-auth-link-btn {
        background: none;
        border: none;
        color: var(--jp-muted, #64748b);
        font-size: 0.9rem;
        cursor: pointer;
        text-decoration: underline;
        padding: 0;
    }
    .jp-auth-link-btn:hover { color: var(--jp-primary, #00843D); }
</style>
@endpush
