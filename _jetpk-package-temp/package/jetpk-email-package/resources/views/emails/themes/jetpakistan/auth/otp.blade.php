{{-- OTP verification email. Subject: Your JetPakistan verification code --}}
@extends('emails.themes.jetpakistan.layouts.base')

@section('content')
    @php
        $brand      = (isset($emailBrand) && is_array($emailBrand)) ? $emailBrand : [];
        $sec        = (isset($security) && is_array($security)) ? $security : [];
        $primary    = $brand['primary_color'] ?? '#00843D';
        $textColor  = $brand['text_color']    ?? '#0f2435';
        $mutedColor = $brand['muted_color']   ?? '#64748b';
        $borderCol  = $brand['border_color']  ?? '#d9e6ee';
        $bgSoft     = $brand['background_color'] ?? '#eef6f9';
        $code       = $sec['otp']            ?? ($otpCode ?? null);
        $expiry     = $sec['expiry_minutes'] ?? ($otpExpiryMinutes ?? null);
        $context    = $sec['context']        ?? null;
    @endphp

    @if(!empty($code))
        <table role="presentation" cellpadding="0" cellspacing="0" border="0" width="100%" style="margin:6px 0 14px 0;">
            <tr>
                <td align="center" style="background-color:{{ $bgSoft }}; border:1px dashed {{ $primary }}; border-radius:12px; padding:22px 16px;">
                    <div style="font-family:Arial,Helvetica,sans-serif; font-size:12px; letter-spacing:0.6px; text-transform:uppercase; color:{{ $mutedColor }}; font-weight:bold; margin:0 0 8px 0;">Your verification code</div>
                    <div class="jetpk-otp" style="font-family:Arial,Helvetica,sans-serif; font-size:38px; line-height:44px; letter-spacing:12px; font-weight:bold; color:{{ $primary }}; padding-left:12px;">{{ $code }}</div>
                    @if(!empty($expiry))
                        <div style="font-family:Arial,Helvetica,sans-serif; font-size:13px; color:{{ $mutedColor }}; margin-top:10px;">This code expires in {{ $expiry }} minutes.</div>
                    @endif
                </td>
            </tr>
        </table>
    @else
        @include('emails.themes.jetpakistan.partials.alert-box', ['type' => 'warning', 'title' => 'Code not available', 'message' => 'Your verification code could not be displayed. Please request a new code.'])
    @endif

    @if(!empty($context))
        @include('emails.themes.jetpakistan.partials.info-row', ['label' => 'Request context', 'value' => $context, 'emailBrand' => $brand])
    @endif

    @include('emails.themes.jetpakistan.partials.alert-box', [
        'type' => 'info',
        'title' => 'Keep this code private',
        'message' => 'Never share this code with anyone. If this wasn\'t you, ignore this email or contact support.',
    ])
@endsection
