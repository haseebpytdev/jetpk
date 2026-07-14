@php
    use Illuminate\Support\Carbon;

    $expiresAt = $expiresAt ?? null;
    if ($expiresAt instanceof Carbon) {
        $expiresIso = $expiresAt->toIso8601String();
        $expiryHint = $expiresAt->format('H:i').' on '.$expiresAt->format('M j, Y');
    } elseif (is_string($expiresAt) && trim($expiresAt) !== '') {
        try {
            $parsed = Carbon::parse($expiresAt);
            $expiresIso = $parsed->toIso8601String();
            $expiryHint = $parsed->format('H:i').' on '.$parsed->format('M j, Y');
        } catch (\Throwable) {
            $expiresIso = '';
            $expiryHint = '';
        }
    } else {
        $expiresIso = '';
        $expiryHint = '';
    }
@endphp
@if ($expiresIso !== '')
    <div
        class="ota-fare-session-timer ota-group-reservation-timer"
        id="gt-reservation-timer"
        data-gt-expires-at="{{ $expiresIso }}"
        role="status"
        aria-live="polite"
        aria-atomic="true"
    >
        <div class="ota-fare-session-timer__active" data-gt-timer-active>
            <span class="ota-fare-session-timer__icon" aria-hidden="true"><i class="fa fa-clock-o"></i></span>
            <span class="ota-fare-session-timer__copy">
                <span class="ota-fare-session-timer__label">Reservation held for</span>
                <span class="ota-fare-session-timer__time" data-gt-timer-display>--:--</span>
                @if ($expiryHint !== '')
                    <span class="ota-fare-session-timer__expiry-label">Complete payment before {{ $expiryHint }}</span>
                @endif
            </span>
        </div>
        <div class="ota-fare-session-timer__expired" data-gt-timer-expired hidden>
            <span class="ota-fare-session-timer__icon ota-fare-session-timer__icon--warn" aria-hidden="true"><i class="fa fa-exclamation-triangle"></i></span>
            <p class="ota-fare-session-timer__expired-text mb-0">Your reservation has expired. Please search again or contact support.</p>
        </div>
    </div>
@endif
