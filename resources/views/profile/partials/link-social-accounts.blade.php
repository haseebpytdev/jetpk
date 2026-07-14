@php
    use App\Http\Controllers\Auth\SocialAuthController;

    $googleEnabled = SocialAuthController::providerIsConfigured('google');
    $googleLinked = $user->socialAccounts()->where('provider', 'google')->exists();
@endphp
@if ($googleEnabled)
<section class="ota-profile-section ota-profile-section--social" aria-labelledby="ota-profile-social-heading">
    <div class="ota-profile-section-header">
        <h3 class="ota-profile-section-title" id="ota-profile-social-heading">Connected accounts</h3>
        <p class="ota-profile-section-lead">Link Google to sign in faster on this device.</p>
    </div>

    @if (session('status') === 'social-linked')
        <div class="ota-profile-alert alert alert-success" role="status">Google account linked successfully.</div>
    @elseif (session('status') === 'social-already-linked')
        <div class="ota-profile-alert alert alert-info" role="status">Google is already linked to your account.</div>
    @endif
    @error('social')
        <div class="ota-profile-alert alert alert-danger" role="alert">{{ $message }}</div>
    @enderror

    <div class="ota-r-action-bar">
        @if ($googleLinked)
            <span class="ota-profile-status-ok" role="status">Google account linked</span>
        @else
            <a href="{{ route('social.link', ['provider' => 'google']) }}" class="ota-btn ota-btn-secondary">Link Google account</a>
        @endif
    </div>
</section>
@endif
