{{-- Payment summary. Input: $payment (array), $emailBrand. No card data ever. --}}
@php
    $brand       = (isset($emailBrand) && is_array($emailBrand)) ? $emailBrand : [];
    $p           = (isset($payment) && is_array($payment)) ? $payment : [];
    $borderColor = $brand['border_color']  ?? '#d9e6ee';
    $mutedColor  = $brand['muted_color']   ?? '#64748b';
    $textColor   = $brand['text_color']    ?? '#0f2435';
    $primary     = $brand['primary_color'] ?? '#00843D';

    $amount    = $p['amount']    ?? null;
    $currency  = $p['currency']  ?? null;
    $method    = $p['method']    ?? null;
    $status    = $p['status']    ?? null;
    $reference = $p['reference'] ?? ($p['transaction_id'] ?? null);
    $invoiceNo = $p['invoice_number'] ?? null;
    $paidAt    = $p['paid_at']   ?? null;
    $total     = ($amount !== null) ? trim(($currency ? $currency.' ' : '').$amount) : null;
@endphp
<table role="presentation" cellpadding="0" cellspacing="0" border="0" width="100%" style="margin:6px 0 14px 0; border:1px solid {{ $borderColor }}; border-radius:12px; background-color:#ffffff;">
    <tr>
        <td style="padding:16px 18px;">
            <div style="font-family:Arial,Helvetica,sans-serif; font-size:12px; letter-spacing:0.6px; text-transform:uppercase; color:{{ $primary }}; font-weight:bold; margin:0 0 8px 0;">Payment summary</div>
            <table role="presentation" cellpadding="0" cellspacing="0" border="0" width="100%">
                @include('emails.themes.jetpakistan.partials.info-row', ['label' => 'Amount', 'value' => $total, 'emailBrand' => $brand])
                @include('emails.themes.jetpakistan.partials.info-row', ['label' => 'Method', 'value' => $method, 'emailBrand' => $brand])
                @include('emails.themes.jetpakistan.partials.info-row', ['label' => 'Status', 'value' => $status, 'emailBrand' => $brand])
                @include('emails.themes.jetpakistan.partials.info-row', ['label' => 'Invoice number', 'value' => $invoiceNo, 'emailBrand' => $brand])
                @include('emails.themes.jetpakistan.partials.info-row', ['label' => 'Reference', 'value' => $reference, 'emailBrand' => $brand])
                @include('emails.themes.jetpakistan.partials.info-row', ['label' => 'Paid at', 'value' => $paidAt, 'emailBrand' => $brand])
            </table>
        </td>
    </tr>
</table>
