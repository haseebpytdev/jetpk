<div class="ota-mobile-app__desktop-link" data-testid="ota-mobile-app-desktop-toggle">
    <form method="post" action="{{ route('view-preference.desktop') }}">
        @csrf
        <input type="hidden" name="redirect" value="{{ url()->current() }}">
        <button type="submit" class="ota-mobile-app__desktop-link-btn" aria-label="Switch to desktop view">
            <svg viewBox="0 0 24 24" width="14" height="14" fill="currentColor" aria-hidden="true">
                <path d="M21 2H3c-1.1 0-2 .9-2 2v12c0 1.1.9 2 2 2h7v2H8v2h8v-2h-2v-2h7c1.1 0 2-.9 2-2V4c0-1.1-.9-2-2-2zm0 14H3V4h18v12z"/>
            </svg>
            <span>Desktop</span>
        </button>
    </form>
</div>
