{{-- Sign-in success / new login notification email --}}
@extends('emails.themes.jetpakistan.layouts.base')

@section('content')
    @php
        $brand = (isset($emailBrand) && is_array($emailBrand)) ? $emailBrand : [];
        $sec   = (isset($security) && is_array($security)) ? $security : [];
        $time    = $sec['login_time'] ?? null;
        $device  = $sec['device']     ?? null;
        $browser = $sec['browser']    ?? null;
        $ip      = $sec['ip']         ?? null;
        $location = $sec['location']  ?? null;
        $deviceLine = trim(($device ?? '').(($device && $browser) ? ' · ' : '').($browser ?? ''));
    @endphp

    <table role="presentation" cellpadding="0" cellspacing="0" border="0" width="100%" style="margin:6px 0 14px 0; border:1px solid {{ $brand['border_color'] ?? '#d9e6ee' }}; border-radius:12px; background-color:#ffffff;">
        <tr>
            <td style="padding:16px 18px;">
                <div style="font-family:Arial,Helvetica,sans-serif; font-size:12px; letter-spacing:0.6px; text-transform:uppercase; color:{{ $brand['primary_color'] ?? '#00843D' }}; font-weight:bold; margin:0 0 8px 0;">Sign-in details</div>
                <table role="presentation" cellpadding="0" cellspacing="0" border="0" width="100%">
                    @include('emails.themes.jetpakistan.partials.info-row', ['label' => 'Time', 'value' => $time, 'emailBrand' => $brand])
                    @include('emails.themes.jetpakistan.partials.info-row', ['label' => 'Device', 'value' => $deviceLine, 'emailBrand' => $brand])
                    @include('emails.themes.jetpakistan.partials.info-row', ['label' => 'Location', 'value' => $location, 'emailBrand' => $brand])
                    @include('emails.themes.jetpakistan.partials.info-row', ['label' => 'IP address', 'value' => $ip, 'emailBrand' => $brand])
                </table>
            </td>
        </tr>
    </table>

    @include('emails.themes.jetpakistan.partials.alert-box', [
        'type' => 'info',
        'title' => 'Was this you?',
        'message' => 'If you recognise this activity, no action is needed. If not, reset your password and contact support right away.',
    ])

    @include('emails.themes.jetpakistan.partials.support-card', ['emailBrand' => $brand, 'support' => $support ?? null])
@endsection
