{{-- Manual payment pending email. Bank/instructions come ONLY from configured data. --}}
@extends('emails.themes.jetpakistan.layouts.base')

@section('content')
    @php
        $brand   = (isset($emailBrand) && is_array($emailBrand)) ? $emailBrand : [];
        $b       = (isset($booking) && is_array($booking)) ? $booking : [];
        $pay     = (isset($payment) && is_array($payment)) ? $payment : [];
        $meta    = (isset($meta) && is_array($meta)) ? $meta : [];
        $deadline     = $b['payment_deadline'] ?? ($meta['payment_deadline'] ?? null);
        $instructions = $pay['instructions'] ?? ($meta['payment_instructions'] ?? null);
        $primary = $brand['primary_color'] ?? '#00843D';
        $muted   = $brand['muted_color']   ?? '#64748b';
        $border  = $brand['border_color']  ?? '#d9e6ee';
    @endphp

    @include('emails.themes.jetpakistan.partials.alert-box', [
        'type' => 'warning',
        'title' => 'Payment required to confirm your booking',
        'message' => !empty($deadline)
            ? 'Please complete payment by '.$deadline.' to secure your seats.'
            : 'Please complete payment to secure your seats.',
    ])

    @include('emails.themes.jetpakistan.partials.booking-summary', ['booking' => $b, 'emailBrand' => $brand])

    @if(!empty($instructions))
        <table role="presentation" cellpadding="0" cellspacing="0" border="0" width="100%" style="margin:6px 0 14px 0; border:1px solid {{ $border }}; border-radius:12px; background-color:#ffffff;">
            <tr>
                <td style="padding:16px 18px; font-family:Arial,Helvetica,sans-serif;">
                    <div style="font-size:12px; letter-spacing:0.6px; text-transform:uppercase; color:{{ $primary }}; font-weight:bold; margin:0 0 8px 0;">Payment instructions</div>
                    <div style="font-size:14px; line-height:22px; color:{{ $brand['text_color'] ?? '#0f2435' }}; white-space:pre-line;">{{ $instructions }}</div>
                </td>
            </tr>
        </table>
    @else
        <p style="font-family:Arial,Helvetica,sans-serif; font-size:14px; line-height:21px; color:{{ $muted }};">
            Payment instructions will be shared shortly. If you need them now, please contact support.
        </p>
    @endif

    @include('emails.themes.jetpakistan.partials.support-card', ['emailBrand' => $brand, 'support' => $support ?? null])
@endsection
