@if(\App\Support\Security\TurnstileVerifier::isEnabled())
    @once
        @push('scripts')
            <script src="https://challenges.cloudflare.com/turnstile/v0/api.js" async defer></script>
        @endpush
    @endonce
    <div class="ota-turnstile-wrap" data-turnstile-widget>
        <div
            class="cf-turnstile"
            data-sitekey="{{ config('services.turnstile.site_key') }}"
            data-theme="light"
        ></div>
        @error(\App\Support\Security\TurnstileVerifier::RESPONSE_FIELD)
            <div class="ota-error ota-turnstile-error">{{ $message }}</div>
        @enderror
    </div>
@endif
