@php
    $productLabel = (string) ($productLabel ?? 'Group Ticketing');
    $title = (string) ($title ?? 'Complete your booking');
    $lead = isset($lead) ? (string) $lead : null;
    $activeStep = isset($activeStep) ? (string) $activeStep : null;
@endphp
<div class="ota-checkout-page-head ota-checkout-page-head--flush">
    <p class="ota-checkout-page-kicker">{{ e($productLabel) }}</p>
    <h1 class="ota-checkout-page-title">{{ e($title) }}</h1>
    @if ($lead !== null && $lead !== '')
        <p class="ota-checkout-page-lead">{!! $lead !!}</p>
    @endif
</div>
@if ($activeStep !== null && $activeStep !== '')
    @include('frontend.checkout.partials.stepper', ['activeStep' => $activeStep])
@endif
