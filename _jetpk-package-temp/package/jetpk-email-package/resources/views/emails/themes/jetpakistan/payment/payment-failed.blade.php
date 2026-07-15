{{-- Payment failed email. --}}
@extends('emails.themes.jetpakistan.layouts.base')

@section('content')
    @php
        $brand = (isset($emailBrand) && is_array($emailBrand)) ? $emailBrand : [];
        $p     = (isset($payment) && is_array($payment)) ? $payment : [];
        $b     = (isset($booking) && is_array($booking)) ? $booking : [];
        $amount   = $p['amount']   ?? null;
        $currency = $p['currency'] ?? null;
        $total    = ($amount !== null) ? trim(($currency ? $currency.' ' : '').$amount) : null;
    @endphp

    @include('emails.themes.jetpakistan.partials.alert-box', [
        'type' => 'error',
        'title' => 'Your payment didn\'t go through',
        'message' => 'We couldn\'t process your payment. Your booking is still held for a short time. Please try again to secure it.',
    ])

    <table role="presentation" cellpadding="0" cellspacing="0" border="0" width="100%" style="margin:6px 0 14px 0; border:1px solid {{ $brand['border_color'] ?? '#d9e6ee' }}; border-radius:12px; background-color:#ffffff;">
        <tr>
            <td style="padding:16px 18px;">
                <table role="presentation" cellpadding="0" cellspacing="0" border="0" width="100%">
                    @include('emails.themes.jetpakistan.partials.info-row', ['label' => 'Booking reference', 'value' => $b['reference'] ?? null, 'emailBrand' => $brand])
                    @include('emails.themes.jetpakistan.partials.info-row', ['label' => 'Amount', 'value' => $total, 'emailBrand' => $brand])
                </table>
            </td>
        </tr>
    </table>

    @include('emails.themes.jetpakistan.partials.support-card', ['emailBrand' => $brand, 'support' => $support ?? null])
@endsection
