{{-- Support ticket created email. --}}
@extends('emails.themes.jetpakistan.layouts.base')

@section('content')
    @php
        $brand = (isset($emailBrand) && is_array($emailBrand)) ? $emailBrand : [];
        $s     = (isset($support) && is_array($support)) ? $support : [];
        $ref     = $s['ticket_reference'] ?? ($s['reference'] ?? null);
        $subject = $s['subject'] ?? null;
        $status  = $s['status'] ?? 'Open';
    @endphp

    @include('emails.themes.jetpakistan.partials.alert-box', [
        'type' => 'success',
        'title' => 'We\'ve received your request',
        'message' => 'Thanks for reaching out. Our team will get back to you as soon as possible.',
    ])

    <table role="presentation" cellpadding="0" cellspacing="0" border="0" width="100%" style="margin:6px 0 14px 0; border:1px solid {{ $brand['border_color'] ?? '#d9e6ee' }}; border-radius:12px; background-color:#ffffff;">
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

    @include('emails.themes.jetpakistan.partials.support-card', ['emailBrand' => $brand, 'support' => $s])
@endsection
