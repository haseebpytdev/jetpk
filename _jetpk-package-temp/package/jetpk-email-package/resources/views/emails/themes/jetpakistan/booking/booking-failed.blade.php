{{-- Booking failed email. NEVER expose raw supplier errors. --}}
@extends('emails.themes.jetpakistan.layouts.base')

@section('content')
    @php
        $brand = (isset($emailBrand) && is_array($emailBrand)) ? $emailBrand : [];
        $b     = (isset($booking) && is_array($booking)) ? $booking : [];
        $ref   = $b['reference'] ?? null;
    @endphp

    @include('emails.themes.jetpakistan.partials.alert-box', [
        'type' => 'error',
        'title' => 'We couldn\'t complete your booking',
        'message' => 'Unfortunately your booking could not be completed. No payment has been taken for a failed booking. Please try again or contact support and we\'ll help you finish it.',
    ])

    @if(!empty($ref))
        <table role="presentation" cellpadding="0" cellspacing="0" border="0" width="100%" style="margin:6px 0 14px 0; border:1px solid {{ $brand['border_color'] ?? '#d9e6ee' }}; border-radius:12px; background-color:#ffffff;">
            <tr>
                <td style="padding:16px 18px;">
                    <table role="presentation" cellpadding="0" cellspacing="0" border="0" width="100%">
                        @include('emails.themes.jetpakistan.partials.info-row', ['label' => 'Reference', 'value' => $ref, 'emailBrand' => $brand])
                    </table>
                </td>
            </tr>
        </table>
    @endif

    @include('emails.themes.jetpakistan.partials.support-card', ['emailBrand' => $brand, 'support' => $support ?? null])
@endsection
