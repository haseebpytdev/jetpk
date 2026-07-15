{{-- Payment success email. --}}
@extends('emails.themes.jetpakistan.layouts.base')

@section('content')
    @php
        $brand = (isset($emailBrand) && is_array($emailBrand)) ? $emailBrand : [];
        $p     = (isset($payment) && is_array($payment)) ? $payment : [];
        $hasInvoiceUrl = !empty($p['invoice_url']);
    @endphp

    @include('emails.themes.jetpakistan.partials.alert-box', [
        'type' => 'success',
        'title' => 'Payment successful',
        'message' => 'Your payment has been received. A summary is below.',
    ])

    @include('emails.themes.jetpakistan.partials.payment-summary', ['payment' => $p, 'emailBrand' => $brand])

    @if(!empty($booking))
        @include('emails.themes.jetpakistan.partials.booking-summary', ['booking' => $booking, 'emailBrand' => $brand])
    @endif

    @unless($hasInvoiceUrl)
        <p style="font-family:Arial,Helvetica,sans-serif; font-size:13px; line-height:20px; color:{{ $brand['muted_color'] ?? '#64748b' }};">
            Your invoice is attached to this email or available in your account.
        </p>
    @endunless

    @include('emails.themes.jetpakistan.partials.support-card', ['emailBrand' => $brand, 'support' => $support ?? null])
@endsection
