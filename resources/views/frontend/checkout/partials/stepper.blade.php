@php
    $activeStep = (string) ($activeStep ?? 'passengers');
    $steps = [
        'passengers' => 'Passenger details',
        'review' => 'Review',
        'payment' => 'Payment',
        'confirmation' => 'Confirmation',
    ];
    $stepKeys = array_keys($steps);
    $activeIndex = array_search($activeStep, $stepKeys, true);
    if ($activeIndex === false) {
        $activeIndex = 0;
    }
@endphp
<nav class="ota-checkout-stepper" aria-label="Checkout progress">
    @foreach ($steps as $key => $label)
        @if (! $loop->first)
            <span aria-hidden="true">→</span>
        @endif
        <span @if ($key === $activeStep) aria-current="step" @endif @if ($loop->index > $activeIndex) class="ota-checkout-stepper__future" @endif>{{ $label }}</span>
    @endforeach
</nav>
