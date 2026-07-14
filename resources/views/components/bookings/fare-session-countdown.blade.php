@props([
    'sessionKey' => '',
    'durationSeconds' => 300,
    'expiresAtIso' => null,
    'variant' => 'desktop',
    'refreshSearchUrl' => '',
])

@php
    use App\Support\Time\DisplayTimezoneResolver;
    use App\Support\Time\LocalTimeDisplay;
    use Illuminate\Support\Carbon;

    $durationSeconds = max(1, (int) $durationSeconds);
    $sessionKey = trim((string) $sessionKey) !== '' ? trim((string) $sessionKey) : 'fare-session';
    $serverNow = now()->timestamp;
    $remainingSeconds = $durationSeconds;
    $lockExpiryIso = is_string($expiresAtIso) ? trim($expiresAtIso) : '';
    if ($lockExpiryIso !== '') {
        try {
            $lockExpiry = Carbon::parse($lockExpiryIso)->timestamp;
            if ($lockExpiry > $serverNow) {
                $remainingSeconds = max(1, $lockExpiry - $serverNow);
            } else {
                $remainingSeconds = 0;
            }
        } catch (\Throwable) {
            // Fall back to durationSeconds when lock expiry is missing or invalid.
        }
    }
    $isMobile = $variant === 'mobile';
    $expiredMessage = 'This fare session may have expired. Please refresh or search again before payment.';
    $expiresAtTimestamp = '';
    if ($lockExpiryIso !== '') {
        try {
            $expiresAtTimestamp = (string) Carbon::parse($lockExpiryIso)->timestamp;
        } catch (\Throwable) {
            $expiresAtTimestamp = '';
        }
    }
    $initialMinutes = intdiv(max(0, $remainingSeconds), 60);
    $initialSeconds = max(0, $remainingSeconds) % 60;
    $initialDisplay = sprintf('%02d:%02d', $initialMinutes, $initialSeconds);
    $searchRefreshUrl = trim((string) $refreshSearchUrl) !== '' ? trim((string) $refreshSearchUrl) : url('/');
    $modalId = 'ota-fare-session-expired-'.md5($sessionKey);
    $expiryHint = '';
    if ($lockExpiryIso !== '') {
        try {
            $visitorTz = app(DisplayTimezoneResolver::class)->visitorTimezone(request());
            $expiryHint = app(LocalTimeDisplay::class)->formatExpiryHint($lockExpiryIso, $visitorTz) ?? '';
        } catch (\Throwable) {
            $expiryHint = '';
        }
    }
@endphp

<div class="ota-fare-session-wrap" data-ota-fare-session-wrap>
    <div
        class="ota-fare-session-timer{{ $isMobile ? ' ota-fare-session-timer--mobile' : '' }}"
        data-ota-fare-session-timer
        data-remaining-seconds="{{ max(0, $remainingSeconds) }}"
        @if ($expiresAtTimestamp !== '')
            data-expires-at="{{ $expiresAtTimestamp }}"
        @endif
        data-session-key="{{ e($sessionKey) }}"
        data-search-url="{{ $searchRefreshUrl }}"
        data-testid="ota-fare-session-timer"
        role="status"
        aria-live="polite"
        aria-atomic="true"
    >
        <div class="ota-fare-session-timer__active" data-ota-fare-session-active>
            <span class="ota-fare-session-timer__icon" aria-hidden="true"><i class="fa fa-clock-o"></i></span>
            <span class="ota-fare-session-timer__copy">
                <span class="ota-fare-session-timer__label">Fare held for</span>
                <span class="ota-fare-session-timer__time" data-ota-fare-session-display>{{ $initialDisplay }}</span>
                @if ($expiryHint !== '')
                    <span class="ota-fare-session-timer__expiry-label">Expires at {{ $expiryHint }}</span>
                @endif
            </span>
        </div>
        <div class="ota-fare-session-timer__expired" data-ota-fare-session-expired hidden>
            <span class="ota-fare-session-timer__icon ota-fare-session-timer__icon--warn" aria-hidden="true"><i class="fa fa-exclamation-triangle"></i></span>
            <p class="ota-fare-session-timer__expired-text">{{ $expiredMessage }}</p>
        </div>
    </div>

    <div
        id="{{ $modalId }}"
        class="ota-fare-session-expired-modal"
        data-ota-fare-session-expired-modal
        hidden
        role="dialog"
        aria-modal="true"
        aria-labelledby="{{ $modalId }}-title"
        aria-describedby="{{ $modalId }}-body"
    >
        <div class="ota-fare-session-expired-modal__backdrop" data-ota-fare-session-expired-backdrop tabindex="-1"></div>
        <div class="ota-fare-session-expired-modal__panel" role="document">
            <h2 id="{{ $modalId }}-title" class="ota-fare-session-expired-modal__title">Your checkout session has expired.</h2>
            <p id="{{ $modalId }}-body" class="ota-fare-session-expired-modal__body">
                Flight fares can change quickly. Please refresh the results and choose a flight again to continue.
            </p>
            <div class="ota-fare-session-expired-modal__actions">
                <button type="button" class="ota-fare-session-expired-modal__btn ota-fare-session-expired-modal__btn--primary" data-ota-fare-session-refresh>
                    Refresh flight results
                </button>
                <a href="{{ url('/') }}" class="ota-fare-session-expired-modal__btn ota-fare-session-expired-modal__btn--secondary" data-ota-fare-session-home>
                    Go to Home
                </a>
            </div>
        </div>
    </div>
</div>

@once
    @push('styles')
        <style>
            .ota-fare-session-timer__expired[hidden],
            .ota-fare-session-expired-modal[hidden] {
                display: none !important;
            }

            .ota-fare-session-expired-modal {
                position: fixed;
                inset: 0;
                z-index: 1060;
                display: flex;
                align-items: center;
                justify-content: center;
                padding: 16px;
            }

            .ota-fare-session-expired-modal__backdrop {
                position: absolute;
                inset: 0;
                background: rgba(15, 23, 42, 0.45);
            }

            .ota-fare-session-expired-modal__panel {
                position: relative;
                z-index: 1;
                width: 100%;
                max-width: min(28rem, calc(100vw - 2rem));
                background: #fff;
                border-radius: 12px;
                padding: 20px 22px;
                box-shadow: 0 18px 48px rgba(15, 23, 42, 0.18);
            }

            .ota-fare-session-expired-modal__title {
                margin: 0 0 12px;
                font-size: 18px;
                font-weight: 700;
                line-height: 1.35;
            }

            .ota-fare-session-expired-modal__body {
                margin: 0 0 18px;
                font-size: 15px;
                line-height: 1.5;
                color: #334155;
            }

            .ota-fare-session-expired-modal__actions {
                display: flex;
                flex-direction: column;
                gap: 10px;
            }

            .ota-fare-session-expired-modal__btn {
                display: block;
                width: 100%;
                text-align: center;
                border-radius: 8px;
                padding: 12px 16px;
                font-size: 15px;
                font-weight: 600;
                line-height: 1.3;
                text-decoration: none;
                cursor: pointer;
                border: 1px solid transparent;
            }

            .ota-fare-session-expired-modal__btn--primary {
                background: #0d6efd;
                border-color: #0d6efd;
                color: #fff;
            }

            .ota-fare-session-expired-modal__btn--secondary {
                background: #fff;
                border-color: #cbd5e1;
                color: #0f172a;
            }

            .ota-fare-session-timer__expiry-label {
                display: block;
                margin-top: 2px;
                font-size: 0.8125rem;
                font-weight: 500;
                color: #64748b;
            }

            body.ota-fare-session-expired-modal-open {
                overflow: hidden;
            }
        </style>
    @endpush
    @push('scripts')
        <script>
            (function () {
                function padTime(value) {
                    return value < 10 ? '0' + value : String(value);
                }

                function resolveFareSessionExpiry(root) {
                    var sessionKey = root.getAttribute('data-session-key') || 'fare-session';
                    var storageKey = 'ota-fare-session-expires:' + sessionKey;
                    var expiresAtAttr = parseInt(root.getAttribute('data-expires-at') || '0', 10);
                    var remainingSeconds = parseInt(root.getAttribute('data-remaining-seconds') || '0', 10);
                    var serverExpiresAt = expiresAtAttr > 0
                        ? expiresAtAttr
                        : (remainingSeconds > 0 ? Math.floor(Date.now() / 1000) + remainingSeconds : 0);
                    var storedExpiresAt = 0;

                    try {
                        storedExpiresAt = parseInt(window.sessionStorage.getItem(storageKey) || '0', 10);
                    } catch (error) {
                        storedExpiresAt = 0;
                    }

                    var expiresAt = serverExpiresAt;
                    if (serverExpiresAt > 0 && storedExpiresAt > 0) {
                        expiresAt = Math.min(serverExpiresAt, storedExpiresAt);
                    } else if (serverExpiresAt <= 0 && storedExpiresAt > 0) {
                        expiresAt = storedExpiresAt;
                    }

                    try {
                        if (expiresAt > 0) {
                            window.sessionStorage.setItem(storageKey, String(expiresAt));
                        } else {
                            window.sessionStorage.removeItem(storageKey);
                        }
                    } catch (error) {
                        // Ignore storage failures; timer still uses server expiry.
                    }

                    return expiresAt > 0 ? expiresAt : 0;
                }

                function guardCheckoutAfterExpiry() {
                    if (document.documentElement.getAttribute('data-ota-fare-session-expired') === '1') {
                        return;
                    }

                    document.documentElement.setAttribute('data-ota-fare-session-expired', '1');

                    var formSelectors = [
                        '[data-checkout-passenger-form]',
                        '[data-mobile-checkout-passenger-form]',
                        '#ota-review-submit-form',
                        '#ota-mobile-review-submit-form',
                    ];

                    formSelectors.forEach(function (selector) {
                        document.querySelectorAll(selector).forEach(function (form) {
                            form.querySelectorAll('button[type="submit"], input[type="submit"]').forEach(function (button) {
                                button.disabled = true;
                                button.setAttribute('aria-disabled', 'true');
                            });

                            form.addEventListener('submit', function (event) {
                                event.preventDefault();
                            });
                        });
                    });
                }

                function showFareSessionExpiredModal(root) {
                    if (window._otaFareSessionExpiredModalShown) {
                        return;
                    }

                    var wrap = root.closest('[data-ota-fare-session-wrap]');
                    var modal = wrap ? wrap.querySelector('[data-ota-fare-session-expired-modal]') : null;
                    if (!modal) {
                        return;
                    }

                    window._otaFareSessionExpiredModalShown = true;
                    modal.hidden = false;
                    document.body.classList.add('ota-fare-session-expired-modal-open');
                    guardCheckoutAfterExpiry();

                    var refreshButton = modal.querySelector('[data-ota-fare-session-refresh]');
                    var searchUrl = root.getAttribute('data-search-url') || '/';

                    if (refreshButton) {
                        refreshButton.addEventListener('click', function () {
                            window.location.href = searchUrl;
                        });
                    }

                    if (refreshButton && typeof refreshButton.focus === 'function') {
                        refreshButton.focus();
                    }
                }

                function markFareSessionExpired(root, displayEl, activeEl, expiredEl) {
                    displayEl.textContent = '00:00';
                    root.classList.add('ota-fare-session-timer--expired');
                    if (activeEl) {
                        activeEl.hidden = true;
                    }
                    if (expiredEl) {
                        expiredEl.hidden = false;
                    }
                    showFareSessionExpiredModal(root);
                }

                function initFareSessionTimer(root) {
                    if (!root) {
                        return;
                    }

                    var displayEl = root.querySelector('[data-ota-fare-session-display]');
                    var activeEl = root.querySelector('[data-ota-fare-session-active]');
                    var expiredEl = root.querySelector('[data-ota-fare-session-expired]');
                    if (!displayEl) {
                        return;
                    }

                    if (root._otaFareSessionIntervalId) {
                        window.clearInterval(root._otaFareSessionIntervalId);
                        root._otaFareSessionIntervalId = null;
                    }

                    var expiresAt = resolveFareSessionExpiry(root);
                    if (!expiresAt) {
                        markFareSessionExpired(root, displayEl, activeEl, expiredEl);
                        return;
                    }

                    root.classList.remove('ota-fare-session-timer--expired', 'ota-fare-session-timer--urgent');
                    if (activeEl) {
                        activeEl.hidden = false;
                    }
                    if (expiredEl) {
                        expiredEl.hidden = true;
                    }

                    function tick() {
                        var remaining = expiresAt - Math.floor(Date.now() / 1000);

                        if (remaining <= 0) {
                            markFareSessionExpired(root, displayEl, activeEl, expiredEl);
                            return false;
                        }

                        var minutes = Math.floor(remaining / 60);
                        var seconds = remaining % 60;
                        displayEl.textContent = padTime(minutes) + ':' + padTime(seconds);

                        if (remaining <= 60) {
                            root.classList.add('ota-fare-session-timer--urgent');
                        } else {
                            root.classList.remove('ota-fare-session-timer--urgent');
                        }

                        return true;
                    }

                    if (!tick()) {
                        return;
                    }

                    root._otaFareSessionIntervalId = window.setInterval(function () {
                        if (!tick()) {
                            window.clearInterval(root._otaFareSessionIntervalId);
                            root._otaFareSessionIntervalId = null;
                        }
                    }, 1000);
                }

                function bootFareSessionTimers() {
                    document.querySelectorAll('[data-ota-fare-session-timer]').forEach(initFareSessionTimer);
                }

                if (document.readyState === 'loading') {
                    document.addEventListener('DOMContentLoaded', bootFareSessionTimers);
                } else {
                    bootFareSessionTimers();
                }

                window.addEventListener('pageshow', function () {
                    bootFareSessionTimers();
                });
            })();
        </script>
    @endpush
@endonce
