{{-- Booking summary card. Input: $booking (array), $emailBrand. --}}
@php
    $brand       = (isset($emailBrand) && is_array($emailBrand)) ? $emailBrand : [];
    $b           = (isset($booking) && is_array($booking)) ? $booking : [];
    $borderColor = $brand['border_color'] ?? '#d9e6ee';
    $mutedColor  = $brand['muted_color']  ?? '#64748b';
    $textColor   = $brand['text_color']   ?? '#0f2435';
    $primary     = $brand['primary_color'] ?? '#00843D';

    $reference   = $b['reference']      ?? null;
    $pnr         = $b['pnr']            ?? null;
    $status      = $b['status']         ?? null;
    $paymentSt   = $b['payment_status'] ?? null;
    $route       = $b['route']          ?? null;
    $tripType    = $b['trip_type']      ?? null;
    $passengers  = $b['passenger_count'] ?? null;
    $amount      = $b['amount']         ?? null;
    $currency    = $b['currency']       ?? null;
    $total       = ($amount !== null) ? trim(($currency ? $currency.' ' : '').$amount) : null;
    $pnrText     = $pnr ?: ($status && strtolower($status) === 'confirmed' ? null : 'Will appear once confirmed');
@endphp
<table role="presentation" cellpadding="0" cellspacing="0" border="0" width="100%" style="margin:6px 0 14px 0; border:1px solid {{ $borderColor }}; border-radius:12px; background-color:#ffffff;">
    <tr>
        <td style="padding:16px 18px 4px 18px;">
            <div style="font-family:Arial,Helvetica,sans-serif; font-size:12px; letter-spacing:0.6px; text-transform:uppercase; color:{{ $primary }}; font-weight:bold; margin:0 0 8px 0;">Booking summary</div>
            <table role="presentation" cellpadding="0" cellspacing="0" border="0" width="100%">
                @include('emails.themes.jetpakistan.partials.info-row', ['label' => 'Booking reference', 'value' => $reference, 'emailBrand' => $brand])
                @include('emails.themes.jetpakistan.partials.info-row', ['label' => 'PNR', 'value' => $pnrText, 'emailBrand' => $brand])
                @include('emails.themes.jetpakistan.partials.info-row', ['label' => 'Route', 'value' => $route, 'emailBrand' => $brand])
                @include('emails.themes.jetpakistan.partials.info-row', ['label' => 'Trip type', 'value' => $tripType, 'emailBrand' => $brand])
                @include('emails.themes.jetpakistan.partials.info-row', ['label' => 'Passengers', 'value' => $passengers, 'emailBrand' => $brand])
                @include('emails.themes.jetpakistan.partials.info-row', ['label' => 'Booking status', 'value' => $status, 'emailBrand' => $brand])
                @include('emails.themes.jetpakistan.partials.info-row', ['label' => 'Payment status', 'value' => $paymentSt, 'emailBrand' => $brand])
                @include('emails.themes.jetpakistan.partials.info-row', ['label' => 'Total amount', 'value' => $total, 'emailBrand' => $brand])
            </table>
        </td>
    </tr>
</table>
