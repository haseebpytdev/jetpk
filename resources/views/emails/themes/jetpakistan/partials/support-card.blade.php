{{-- Support card. Inputs: $emailBrand, $support (optional overrides). --}}
@php
    $brand        = (isset($emailBrand) && is_array($emailBrand)) ? $emailBrand : [];
    $s            = (isset($support) && is_array($support)) ? $support : [];
    $brandName    = $brand['brand_name']    ?? 'JetPakistan';
    $borderColor  = $brand['border_color']  ?? '#d9e6ee';
    $mutedColor   = $brand['muted_color']   ?? '#64748b';
    $textColor    = $brand['text_color']    ?? '#0f2435';
    $primary      = $brand['primary_color'] ?? '#00843D';
    $bgSoft       = $brand['background_color'] ?? '#eef6f9';
    $supportEmail = $s['email'] ?? ($brand['support_email'] ?? null);
    $supportPhone = $s['phone'] ?? ($brand['support_phone'] ?? null);
    $hours        = $s['hours'] ?? null;
@endphp
<table role="presentation" cellpadding="0" cellspacing="0" border="0" width="100%" style="margin:6px 0 8px 0; border:1px solid {{ $borderColor }}; border-radius:12px; background-color:{{ $bgSoft }};">
    <tr>
        <td style="padding:16px 18px; font-family:Arial,Helvetica,sans-serif;">
            <div style="font-size:15px; font-weight:bold; color:{{ $textColor }}; margin:0 0 4px 0;">Need help?</div>
            <div style="font-size:14px; line-height:21px; color:{{ $mutedColor }};">
                Contact {{ $brandName }} support and we'll be glad to assist.
            </div>
            @if(!empty($supportEmail) || !empty($supportPhone))
                <div style="font-size:14px; line-height:22px; margin-top:8px;">
                    @if(!empty($supportEmail))
                        <a href="mailto:{{ $supportEmail }}" style="color:{{ $primary }}; text-decoration:none; font-weight:bold;">{{ $supportEmail }}</a>
                    @endif
                    @if(!empty($supportEmail) && !empty($supportPhone)) <br> @endif
                    @if(!empty($supportPhone))
                        <a href="tel:{{ preg_replace('/[^0-9+]/', '', $supportPhone) }}" style="color:{{ $primary }}; text-decoration:none; font-weight:bold;">{{ $supportPhone }}</a>
                    @endif
                </div>
            @endif
            @if(!empty($hours))
                <div style="font-size:12px; color:{{ $mutedColor }}; margin-top:6px;">{{ $hours }}</div>
            @endif
        </td>
    </tr>
</table>
