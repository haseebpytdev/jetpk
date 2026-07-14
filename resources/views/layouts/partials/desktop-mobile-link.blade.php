@php
    use App\Support\Ui\MobileViewPreference;

    $viewPref = app(MobileViewPreference::class);
    $showMobileLink = $viewPref->currentMode(request()) !== MobileViewPreference::MODE_MOBILE;
@endphp
@if ($showMobileLink)
    <div class="ota-desktop-mobile-link" data-testid="ota-desktop-mobile-toggle">
        <a
            href="{{ route('view-preference.mobile-get', ['redirect' => url()->current()]) }}"
            class="ota-desktop-mobile-link__btn"
            aria-label="Switch to mobile view"
        >
            <svg viewBox="0 0 24 24" width="14" height="14" fill="currentColor" aria-hidden="true">
                <path d="M17 1H7c-1.1 0-2 .9-2 2v18c0 1.1.9 2 2 2h10c1.1 0 2-.9 2-2V3c0-1.1-.9-2-2-2zm0 18H7V5h10v14z"/>
            </svg>
            <span>Mobile</span>
        </a>
    </div>
    <style>
        .ota-desktop-mobile-link {
            position: fixed;
            right: 0.75rem;
            bottom: 0.75rem;
            z-index: 1200;
            pointer-events: none;
        }
        .ota-desktop-mobile-link__btn {
            pointer-events: auto;
            display: inline-flex;
            align-items: center;
            gap: 0.35rem;
            padding: 0.35rem 0.65rem;
            border-radius: 999px;
            border: 1px solid rgba(15, 23, 42, 0.12);
            background: rgba(255, 255, 255, 0.96);
            color: #334155;
            font-size: 0.72rem;
            font-weight: 600;
            line-height: 1;
            text-decoration: none;
            box-shadow: 0 2px 8px rgba(15, 23, 42, 0.08);
        }
        .ota-desktop-mobile-link__btn:hover {
            color: #0f172a;
            border-color: rgba(15, 23, 42, 0.2);
        }
    </style>
@endif
