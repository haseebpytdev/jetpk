{{-- Refund status updated email. --}}
@extends('emails.themes.jetpakistan.layouts.base')

@section('content')
    @php
        $brand = (isset($emailBrand) && is_array($emailBrand)) ? $emailBrand : [];
        $p     = (isset($payment) && is_array($payment)) ? $payment : [];
        $b     = (isset($booking) && is_array($booking)) ? $booking : [];
        $meta  = (isset($meta) && is_array($meta)) ? $meta : [];
        $amount   = $p['amount']   ?? null;
        $currency = $p['currency'] ?? null;
        $total    = ($amount !== null) ? trim(($currency ? $currency.' ' : '').$amount) : null;
        $status   = $p['status'] ?? null;
        $note     = $meta['refund_note'] ?? null;
        $isDone   = $status && in_array(strtolower($status), ['refunded', 'completed', 'processed', 'success']);
    @endphp

    @include('emails.themes.jetpakistan.partials.alert-box', [
        'type' => $isDone ? 'success' : 'info',
        'title' => 'Refund update',
        'message' => !empty($note) ? $note : ('Your refund status is now: '.($status ?? 'updated').'.'),
    ])

    <table role="presentation" cellpadding="0" cellspacing="0" border="0" width="100%" style="margin:6px 0 14px 0; border:1px solid {{ $brand['border_color'] ?? '#d9e6ee' }}; border-radius:12px; background-color:#ffffff;">
        <tr>
            <td style="padding:16px 18px;">
                <table role="presentation" cellpadding="0" cellspacing="0" border="0" width="100%">
                    @include('emails.themes.jetpakistan.partials.info-row', ['label' => 'Booking reference', 'value' => $b['reference'] ?? null, 'emailBrand' => $brand])
                    @include('emails.themes.jetpakistan.partials.info-row', ['label' => 'Refund amount', 'value' => $total, 'emailBrand' => $brand])
                    @include('emails.themes.jetpakistan.partials.info-row', ['label' => 'Status', 'value' => $status, 'emailBrand' => $brand])
                </table>
            </td>
        </tr>
    </table>

    @include('emails.themes.jetpakistan.partials.support-card', ['emailBrand' => $brand, 'support' => $support ?? null])
@endsection
