@php
    $brand = (isset($emailBrand) && is_array($emailBrand)) ? $emailBrand : [];
    $payment = (isset($payment) && is_array($payment)) ? $payment : [];
    $instructions = $payment['instructions'] ?? ($meta['payment_instructions'] ?? null);
    $textColor = $brand['text_color'] ?? '#0f2435';
@endphp

@if(!empty($instructions))
    @include('emails.themes.jetpakistan.partials.alert-box', [
        'type' => 'info',
        'title' => 'Payment instructions',
        'message' => $instructions,
    ])
@endif
