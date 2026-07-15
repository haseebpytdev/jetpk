@php
    /** @var int|string $index */
    $countries = is_array($countries ?? null) ? $countries : [];
    $open = (bool) ($open ?? false);
@endphp
@include('frontend.checkout.partials.passenger-card', [
    'index' => $index,
    'prefix' => 'passengers',
    'countries' => $countries,
    'open' => $open,
])
