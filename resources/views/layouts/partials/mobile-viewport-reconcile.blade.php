@php
    $viewportBreakpoint = (int) config('ota-mobile.viewport_breakpoint', 768);
    $viewModeCookie = (string) config('ota-mobile.cookie_name', 'ota_view_mode');
@endphp
<script>
(function () {
    'use strict';

    var BREAKPOINT = {{ $viewportBreakpoint }};
    var COOKIE_NAME = @json($viewModeCookie);
    var hasMobileShell = document.body.classList.contains('ota-mobile-app');

    function readCookie(name) {
        var prefix = name + '=';
        var parts = document.cookie ? document.cookie.split(';') : [];
        for (var i = 0; i < parts.length; i++) {
            var part = parts[i].trim();
            if (part.indexOf(prefix) === 0) {
                return decodeURIComponent(part.substring(prefix.length));
            }
        }
        return '';
    }

    function viewportWidth() {
        return window.innerWidth || document.documentElement.clientWidth || BREAKPOINT + 1;
    }

    function wantsMobileShell() {
        return viewportWidth() <= BREAKPOINT;
    }

    function stripAutoShellParam() {
        try {
            var url = new URL(window.location.href);
            if (!url.searchParams.has('_ota_auto_shell')) {
                return;
            }
            url.searchParams.delete('_ota_auto_shell');
            window.history.replaceState(null, '', url.toString());
        } catch (e) {
            // Ignore malformed URLs.
        }
    }

    function reconcile(force) {
        var manual = readCookie(COOKIE_NAME);
        if (manual === 'mobile' || manual === 'desktop') {
            stripAutoShellParam();
            return;
        }

        var wantsMobile = wantsMobileShell();
        var matches = wantsMobile === hasMobileShell;

        if (!force && matches) {
            stripAutoShellParam();
            return;
        }

        var bucket = wantsMobile ? 'mobile' : 'desktop';
        var guardKey = 'ota_vp_reconcile:' + window.location.pathname + ':' + bucket;
        if (!force && window.sessionStorage && window.sessionStorage.getItem(guardKey) === '1') {
            return;
        }
        if (window.sessionStorage) {
            window.sessionStorage.setItem(guardKey, '1');
        }

        try {
            var target = new URL(window.location.href);
            if (target.searchParams.get('_ota_auto_shell') === bucket) {
                return;
            }
            target.searchParams.set('_ota_auto_shell', bucket);
            window.location.replace(target.toString());
        } catch (e) {
            // Ignore malformed URLs.
        }
    }

    stripAutoShellParam();

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', function () { reconcile(false); });
    } else {
        reconcile(false);
    }

    var resizeTimer;
    window.addEventListener('resize', function () {
        clearTimeout(resizeTimer);
        resizeTimer = setTimeout(function () {
            if (window.sessionStorage) {
                Object.keys(window.sessionStorage).forEach(function (key) {
                    if (key.indexOf('ota_vp_reconcile:') === 0) {
                        window.sessionStorage.removeItem(key);
                    }
                });
            }
            reconcile(false);
        }, 200);
    });
})();
</script>
