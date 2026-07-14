@extends('layouts.developer-auth')

@section('title', 'Developer Control Panel Login')

@section('content')
    <h1 class="dev-cp-auth-title">Developer Control Panel Login</h1>
    <p class="dev-cp-auth-lead">
        This area is restricted to the product owner/developer. It is separate from client admin access.
    </p>
    <ul class="dev-cp-auth-notes">
        <li>No public registration</li>
        <li>No forgot-password recovery from this page</li>
    </ul>

    @if ($errors->has('email'))
        <div class="dev-cp-alert" role="alert">{{ $errors->first('email') }}</div>
    @endif

    <form method="POST" action="{{ route('dev.cp.login.store') }}" autocomplete="off">
        @csrf
        <div class="dev-cp-field">
            <label class="dev-cp-label" for="dev-cp-email">Email</label>
            <input
                id="dev-cp-email"
                class="dev-cp-input"
                type="email"
                name="email"
                value="{{ old('email') }}"
                required
                autofocus
                autocomplete="username"
            >
        </div>
        <div class="dev-cp-field">
            <label class="dev-cp-label" for="dev-cp-password">Password</label>
            <input
                id="dev-cp-password"
                class="dev-cp-input"
                type="password"
                name="password"
                required
                autocomplete="current-password"
            >
        </div>
        <button type="submit" class="dev-cp-btn">Sign in</button>
    </form>

    <p class="dev-cp-footnote">Not linked from public or client admin areas. Use your developer account only.</p>
@endsection
