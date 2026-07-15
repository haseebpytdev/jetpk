{{-- Password reset email. CTA (Reset password) provided via $ctaUrl / $ctaText. --}}
@extends('emails.themes.jetpakistan.layouts.base')

@section('content')
    @php
        $brand   = (isset($emailBrand) && is_array($emailBrand)) ? $emailBrand : [];
        $sec     = (isset($security) && is_array($security)) ? $security : [];
        $expiry  = $sec['expiry_minutes'] ?? ($sec['expiry'] ?? null);
        $resetUrl = $ctaUrl ?? ($sec['reset_url'] ?? null);
        $primary = $brand['primary_color'] ?? '#00843D';
        $muted   = $brand['muted_color']   ?? '#64748b';
    @endphp

    @if(!empty($expiry))
        @include('emails.themes.jetpakistan.partials.alert-box', [
            'type' => 'warning',
            'title' => 'This link expires soon',
            'message' => 'For your security, the reset link is valid for '.$expiry.' minutes. Request a new one if it has expired.',
        ])
    @endif

    @if(!empty($resetUrl))
        <p style="font-family:Arial,Helvetica,sans-serif; font-size:13px; line-height:20px; color:{{ $muted }}; margin:10px 0 0 0;">
            If the button above doesn't work, copy and paste this link into your browser:
        </p>
        <p style="font-family:Arial,Helvetica,sans-serif; font-size:13px; line-height:20px; word-break:break-all; margin:4px 0 0 0;">
            <a href="{{ $resetUrl }}" target="_blank" style="color:{{ $primary }}; text-decoration:underline;">{{ $resetUrl }}</a>
        </p>
    @endif

    @include('emails.themes.jetpakistan.partials.alert-box', [
        'type' => 'info',
        'title' => 'Didn\'t request this?',
        'message' => 'If you didn\'t ask to reset your password, you can safely ignore this email. Your password stays the same.',
    ])
@endsection
