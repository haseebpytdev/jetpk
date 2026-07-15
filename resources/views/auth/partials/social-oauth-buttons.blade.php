@php
    use App\Http\Controllers\Auth\SocialAuthController;

    $verb = $verb ?? 'Log in';
    $googleEnabled = SocialAuthController::providerIsConfigured('google');
    $facebookEnabled = SocialAuthController::providerIsConfigured('facebook');
@endphp
@if ($googleEnabled || $facebookEnabled)
<div class="auth-social-stack">
    @if ($googleEnabled)
    <a class="ota-btn-secondary auth-social-btn auth-social-btn--brand"
       href="{{ client_route('social.redirect', ['provider' => 'google']) }}"
       aria-label="{{ $verb }} with Google"
       title="{{ $verb }} with Google">
        <span class="auth-social-btn__icon" aria-hidden="true">
            <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 48 48" width="24" height="24" focusable="false">
                <path fill="#FFC107" d="M43.611 20.083H42V20H24v8h11.303c-1.649 4.657-6.08 8-11.303 8-6.627 0-12-5.373-12-12s5.373-12 12-12c3.059 0 5.842 1.154 7.961 3.039l5.657-5.657C34.046 6.053 29.268 4 24 4 12.955 4 4 12.955 4 24s8.955 20 20 20 20-8.955 20-20c0-1.341-.138-2.65-.389-3.917z"/>
                <path fill="#FF3D00" d="M6.306 14.691l6.571 4.819C14.655 15.108 18.961 12 24 12c3.059 0 5.842 1.154 7.961 3.039l5.657-5.657C34.046 6.053 29.268 4 24 4 16.318 4 9.656 8.337 6.306 14.691z"/>
                <path fill="#4CAF50" d="M24 44c5.166 0 9.86-1.977 13.409-5.192l-6.19-5.238A11.91 11.91 0 0 1 24 36c-5.202 0-9.619-3.317-11.283-7.946l-6.522 5.025C9.505 39.556 16.227 44 24 44z"/>
                <path fill="#1976D2" d="M43.611 20.083H42V20H24v8h11.303a12.04 12.04 0 0 1-4.087 5.571l.003-.002 6.19 5.238C36.971 39.205 44 34 44 24c0-1.341-.138-2.65-.389-3.917z"/>
            </svg>
        </span>
    </a>
    @endif
    @if ($facebookEnabled)
    <a class="ota-btn-secondary auth-social-btn auth-social-btn--brand"
       href="{{ client_route('social.redirect', ['provider' => 'facebook']) }}"
       aria-label="{{ $verb }} with Facebook"
       title="{{ $verb }} with Facebook">
        <span class="auth-social-btn__icon" aria-hidden="true">
            <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" width="24" height="24" focusable="false">
                <path fill="#0866FF" d="M24 12.073C24 5.446 18.627 0 12 0S0 5.446 0 12.073c0 5.99 4.388 10.954 10.125 11.854v-8.385H7.078v-3.47h3.047V9.43c0-3.007 1.792-4.669 4.533-4.669 1.312 0 2.686.235 2.686.235v2.953H15.83c-1.491 0-1.956.925-1.956 1.874v2.25h3.328l-.532 3.47h-2.796v8.385C19.612 23.027 24 18.062 24 12.073z"/>
            </svg>
        </span>
    </a>
    @endif
</div>
@endif
