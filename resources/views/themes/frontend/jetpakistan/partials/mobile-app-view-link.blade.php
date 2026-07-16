@php
    use App\Support\Ui\MobileViewPreference;

    $viewPref = app(MobileViewPreference::class);
    $showMobileAppLink = $viewPref->currentMode(request()) !== MobileViewPreference::MODE_MOBILE
        && ! $viewPref->shouldUseMobileShell(request());
@endphp
@if ($showMobileAppLink)
    <div class="jp-mobile-app-view-link" data-testid="jp-desktop-mobile-app-toggle">
        <a
            href="{{ route('view-preference.mobile-get', ['redirect' => url()->current()]) }}"
            class="jp-mobile-app-view-link__btn"
            aria-label="Switch to mobile app view"
        >
            <svg viewBox="0 0 24 24" width="15" height="15" fill="currentColor" aria-hidden="true">
                <path d="M17 1H7c-1.1 0-2 .9-2 2v18c0 1.1.9 2 2 2h10c1.1 0 2-.9 2-2V3c0-1.1-.9-2-2-2zm0 18H7V5h10v14z"/>
            </svg>
            <span>Mobile App</span>
        </a>
    </div>
    <style>
        .jp-mobile-app-view-link {
            position: fixed;
            right: max(0.75rem, env(safe-area-inset-right));
            bottom: max(0.75rem, env(safe-area-inset-bottom));
            z-index: 1200;
            pointer-events: none;
        }
        .jp-mobile-app-view-link__btn {
            pointer-events: auto;
            display: inline-flex;
            align-items: center;
            gap: 0.4rem;
            padding: 0.45rem 0.75rem;
            border-radius: 999px;
            border: 1px solid color-mix(in srgb, var(--jp-action, var(--brand-primary, #00843d)) 24%, transparent);
            background: color-mix(in srgb, var(--jp-surface, #fff) 94%, transparent);
            color: var(--jp-text, #0f172a);
            font-family: var(--jp-font-body, Inter, system-ui, sans-serif);
            font-size: 0.72rem;
            font-weight: 600;
            line-height: 1;
            text-decoration: none;
            box-shadow: 0 4px 14px rgba(15, 23, 42, 0.1);
            backdrop-filter: blur(8px);
        }
        .jp-mobile-app-view-link__btn:hover {
            color: var(--jp-action, var(--brand-primary, #00843d));
            border-color: color-mix(in srgb, var(--jp-action, var(--brand-primary, #00843d)) 40%, transparent);
        }
        .jp-mobile-app-view-link__btn:focus-visible {
            outline: 2px solid var(--jp-action, var(--brand-primary, #00843d));
            outline-offset: 2px;
        }
    </style>
@endif
