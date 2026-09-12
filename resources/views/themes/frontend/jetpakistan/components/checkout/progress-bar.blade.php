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
                            <x-jp.icon name="check" style="width:12px;height:12px" />
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
