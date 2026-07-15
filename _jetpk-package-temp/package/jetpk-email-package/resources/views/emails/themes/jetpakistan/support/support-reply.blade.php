{{-- Support reply email. --}}
@extends('emails.themes.jetpakistan.layouts.base')

@section('content')
    @php
        $brand = (isset($emailBrand) && is_array($emailBrand)) ? $emailBrand : [];
        $s     = (isset($support) && is_array($support)) ? $support : [];
        $ref      = $s['ticket_reference'] ?? ($s['reference'] ?? null);
        $subject  = $s['subject'] ?? null;
        $status   = $s['status'] ?? null;
        $response = $s['response'] ?? ($s['message'] ?? null);
        $next     = $s['next_action'] ?? null;
        $primary  = $brand['primary_color'] ?? '#00843D';
        $border   = $brand['border_color']  ?? '#d9e6ee';
        $textColor = $brand['text_color']   ?? '#0f2435';
    @endphp

    @if(!empty($ref) || !empty($subject) || !empty($status))
        <table role="presentation" cellpadding="0" cellspacing="0" border="0" width="100%" style="margin:6px 0 14px 0; border:1px solid {{ $border }}; border-radius:12px; background-color:#ffffff;">
            <tr>
                <td style="padding:16px 18px;">
                    <table role="presentation" cellpadding="0" cellspacing="0" border="0" width="100%">
                        @include('emails.themes.jetpakistan.partials.info-row', ['label' => 'Ticket reference', 'value' => $ref, 'emailBrand' => $brand])
                        @include('emails.themes.jetpakistan.partials.info-row', ['label' => 'Subject', 'value' => $subject, 'emailBrand' => $brand])
                        @include('emails.themes.jetpakistan.partials.info-row', ['label' => 'Status', 'value' => $status, 'emailBrand' => $brand])
                    </table>
                </td>
            </tr>
        </table>
    @endif

    @if(!empty($response))
        <table role="presentation" cellpadding="0" cellspacing="0" border="0" width="100%" style="margin:6px 0 14px 0; border:1px solid {{ $border }}; border-radius:12px; background-color:#ffffff;">
            <tr>
                <td style="padding:16px 18px; font-family:Arial,Helvetica,sans-serif;">
                    <div style="font-size:12px; letter-spacing:0.6px; text-transform:uppercase; color:{{ $primary }}; font-weight:bold; margin:0 0 8px 0;">Reply from support</div>
                    <div style="font-size:14px; line-height:22px; color:{{ $textColor }}; white-space:pre-line;">{{ $response }}</div>
                </td>
            </tr>
        </table>
    @endif

    @if(!empty($next))
        @include('emails.themes.jetpakistan.partials.alert-box', ['type' => 'info', 'title' => 'Next step', 'message' => $next])
    @endif

    @include('emails.themes.jetpakistan.partials.support-card', ['emailBrand' => $brand, 'support' => $s])
@endsection
