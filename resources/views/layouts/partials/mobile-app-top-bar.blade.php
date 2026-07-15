@php
    $topBarTitle = trim($__env->yieldContent('mobile_app_title'));
    $topBarTitle = $topBarTitle !== '' ? $topBarTitle : ($brandName ?? config('app.name'));
@endphp
<header class="ota-mobile-app__top-bar" data-testid="ota-mobile-app-top-bar">
    <div class="ota-mobile-app__top-bar-inner">
        @hasSection('mobile_app_back')
            <div class="ota-mobile-app__top-bar-start">
                @yield('mobile_app_back')
            </div>
        @endif
        <div class="ota-mobile-app__top-bar-brand">
            @if(! empty($logoPath))
                <img
                    src="{{ asset('storage/'.$logoPath) }}"
                    alt=""
                    class="ota-mobile-app__top-bar-logo"
                    width="28"
                    height="28"
                >
            @else
                <span class="ota-mobile-app__top-bar-mark" aria-hidden="true">
                    <svg viewBox="0 0 24 24" width="18" height="18" fill="currentColor" aria-hidden="true">
                        <path d="M2.5 19l1.5-5.5L16 3l5 5-12 12-5.5 1.5L2.5 19zm3.1-2.2l.8-2.9 7.4-7.4-1.5-1.5-7.4 7.4-2.9.8 3.6 3.6zM18.5 7.5L17 6l1.5-1.5L20 6 18.5 7.5z"/>
                    </svg>
                </span>
            @endif
            <span class="ota-mobile-app__top-bar-title">{{ $topBarTitle }}</span>
        </div>
        <div class="ota-mobile-app__top-bar-actions">
            @yield('mobile_app_top_actions')
        </div>
    </div>
</header>
