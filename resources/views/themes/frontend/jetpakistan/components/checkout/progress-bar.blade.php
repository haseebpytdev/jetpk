@php
    $steps = [
        1 => 'Flight selected',
        2 => 'Passenger details',
        3 => 'Review & payment',
        4 => 'Confirmation',
    ];
    $active = max(1, min(4, (int) ($activeStep ?? 1)));
@endphp

<nav class="jp-checkout-progress jp-checkout-progress--pill" aria-label="Booking progress" data-jp-booking-progress>
    <div class="jp-checkout-progress__track">
        <ol class="jp-checkout-progress__list">
            @foreach ($steps as $number => $label)
                <li @class([
                    'jp-checkout-progress__item',
                    'is-complete' => $number < $active,
                    'is-active' => $number === $active,
                ])>
                    <span class="jp-checkout-progress__marker" aria-hidden="true">
                        @if ($number < $active)
                            <svg viewBox="0 0 16 16" width="12" height="12" aria-hidden="true"><path fill="currentColor" d="M6.2 11.6 3.4 8.8l1-1 1.8 1.8 4.4-4.4 1 1z"/></svg>
                        @else
                            {{ $number }}
                        @endif
                    </span>
                    <span class="jp-checkout-progress__label">{{ $label }}</span>
                </li>
            @endforeach
        </ol>
    </div>
</nav>
