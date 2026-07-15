{{-- Invoice email. Printable-friendly and mobile-readable. No card data. --}}
@extends('emails.themes.jetpakistan.layouts.base')

@section('content')
    @php
        $brand   = (isset($emailBrand) && is_array($emailBrand)) ? $emailBrand : [];
        $p       = (isset($payment) && is_array($payment)) ? $payment : [];
        $b       = (isset($booking) && is_array($booking)) ? $booking : [];
        $meta    = (isset($meta) && is_array($meta)) ? $meta : [];

        $primary   = $brand['primary_color'] ?? '#00843D';
        $textColor = $brand['text_color']    ?? '#0f2435';
        $muted     = $brand['muted_color']   ?? '#64748b';
        $border    = $brand['border_color']  ?? '#d9e6ee';
        $bgSoft     = $brand['background_color'] ?? '#eef6f9';

        $invoiceNo = $p['invoice_number'] ?? ($meta['invoice_number'] ?? null);
        $currency  = $p['currency'] ?? null;
        $status    = $p['status']   ?? null;
        $reference = $p['reference'] ?? ($p['transaction_id'] ?? null);
        $bookingRef = $b['reference'] ?? null;
        $customer  = $recipientName ?? ($meta['customer_name'] ?? null);

        // Line items: [ ['label'=>'Base fare','amount'=>'45000'], ... ]
        $items = (isset($meta['items']) && is_array($meta['items'])) ? $meta['items'] : [];
        $subtotal = $meta['subtotal'] ?? null;
        $taxes    = $meta['taxes']    ?? null;
        $fees     = $meta['fees']     ?? null;
        $total    = $p['amount']      ?? ($meta['total'] ?? null);
        $money = function ($v) use ($currency) {
            if ($v === null || $v === '') return null;
            return trim(($currency ? $currency.' ' : '').$v);
        };
    @endphp

    {{-- Invoice header block --}}
    <table role="presentation" cellpadding="0" cellspacing="0" border="0" width="100%" style="margin:2px 0 14px 0; border:1px solid {{ $border }}; border-radius:12px; background-color:#ffffff;">
        <tr>
            <td style="padding:18px;">
                <table role="presentation" cellpadding="0" cellspacing="0" border="0" width="100%">
                    <tr>
                        <td valign="top" class="jetpk-stack" style="font-family:Arial,Helvetica,sans-serif;">
                            <div style="font-size:18px; font-weight:bold; color:{{ $textColor }};">Invoice</div>
                            @if(!empty($invoiceNo))<div style="font-size:13px; color:{{ $muted }}; margin-top:2px;">No. {{ $invoiceNo }}</div>@endif
                        </td>
                        <td valign="top" align="right" class="jetpk-stack" style="font-family:Arial,Helvetica,sans-serif;">
                            @if(!empty($status))
                                <span style="display:inline-block; font-size:12px; font-weight:bold; color:#0f7a3d; background-color:#e9f7ef; border:1px solid #a7e0bf; border-radius:999px; padding:5px 12px;">{{ $status }}</span>
                            @endif
                        </td>
                    </tr>
                </table>
                <table role="presentation" cellpadding="0" cellspacing="0" border="0" width="100%" style="margin-top:12px;">
                    @include('emails.themes.jetpakistan.partials.info-row', ['label' => 'Billed to', 'value' => $customer, 'emailBrand' => $brand])
                    @include('emails.themes.jetpakistan.partials.info-row', ['label' => 'Booking reference', 'value' => $bookingRef, 'emailBrand' => $brand])
                    @include('emails.themes.jetpakistan.partials.info-row', ['label' => 'Transaction reference', 'value' => $reference, 'emailBrand' => $brand])
                    @include('emails.themes.jetpakistan.partials.info-row', ['label' => 'Paid at', 'value' => $p['paid_at'] ?? null, 'emailBrand' => $brand])
                </table>
            </td>
        </tr>
    </table>

    {{-- Line items --}}
    <table role="presentation" cellpadding="0" cellspacing="0" border="0" width="100%" style="margin:2px 0 14px 0; border:1px solid {{ $border }}; border-radius:12px; background-color:#ffffff;">
        <tr>
            <td style="padding:18px;">
                <table role="presentation" cellpadding="0" cellspacing="0" border="0" width="100%">
                    @if(!empty($items))
                        @foreach($items as $it)
                            @php $it = is_array($it) ? $it : []; @endphp
                            @include('emails.themes.jetpakistan.partials.info-row', ['label' => $it['label'] ?? 'Item', 'value' => $money($it['amount'] ?? null), 'emailBrand' => $brand])
                        @endforeach
                        <tr><td colspan="2" style="border-top:1px solid {{ $border }}; font-size:0; line-height:0; padding:6px 0;">&nbsp;</td></tr>
                    @endif
                    @include('emails.themes.jetpakistan.partials.info-row', ['label' => 'Subtotal', 'value' => $money($subtotal), 'emailBrand' => $brand])
                    @include('emails.themes.jetpakistan.partials.info-row', ['label' => 'Taxes', 'value' => $money($taxes), 'emailBrand' => $brand])
                    @include('emails.themes.jetpakistan.partials.info-row', ['label' => 'Fees', 'value' => $money($fees), 'emailBrand' => $brand])
                </table>
                @if(!empty($total))
                    <table role="presentation" cellpadding="0" cellspacing="0" border="0" width="100%" style="margin-top:10px; background-color:{{ $bgSoft }}; border-radius:10px;">
                        <tr>
                            <td style="padding:12px 14px; font-family:Arial,Helvetica,sans-serif; font-size:15px; font-weight:bold; color:{{ $textColor }};">Total</td>
                            <td align="right" style="padding:12px 14px; font-family:Arial,Helvetica,sans-serif; font-size:17px; font-weight:bold; color:{{ $primary }};">{{ $money($total) }}</td>
                        </tr>
                    </table>
                @endif
            </td>
        </tr>
    </table>

    <p style="font-family:Arial,Helvetica,sans-serif; font-size:12px; line-height:18px; color:{{ $muted }};">
        This invoice does not contain any card details. Keep it for your records.
    </p>

    @include('emails.themes.jetpakistan.partials.support-card', ['emailBrand' => $brand, 'support' => $support ?? null])
@endsection
