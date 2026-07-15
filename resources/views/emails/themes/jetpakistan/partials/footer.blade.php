{{-- Footer partial. Input: $emailBrand. Outputs one <tr>. --}}
@php
    $brand        = (isset($emailBrand) && is_array($emailBrand)) ? $emailBrand : [];
    $brandName    = $brand['brand_name']    ?? 'JetPakistan';
    $legalName    = $brand['legal_name']    ?? $brandName;
    $primary      = $brand['primary_color'] ?? '#00843D';
    $mutedColor   = $brand['muted_color']   ?? '#64748b';
    $borderColor  = $brand['border_color']  ?? '#d9e6ee';
    $supportEmail = $brand['support_email'] ?? null;
    $supportPhone = $brand['support_phone'] ?? null;
    $homeUrl      = $brand['home_url']       ?? null;
    $manageUrl    = $brand['manage_url']     ?? null;
    $address      = $brand['address']        ?? null;
    $currentYear  = date('Y');
@endphp
<tr>
    <td style="padding:24px 36px 28px 36px; border-top:1px solid {{ $borderColor }}; background-color:#fafcfd;" class="jetpk-pad">
        <table role="presentation" cellpadding="0" cellspacing="0" border="0" width="100%">
            @if(!empty($supportEmail) || !empty($supportPhone))
                <tr>
                    <td style="padding:0 0 10px 0; font-family:Arial,Helvetica,sans-serif; font-size:13px; line-height:20px; color:{{ $mutedColor }};">
                        Need help?
                        @if(!empty($supportEmail))
                            <a href="mailto:{{ $supportEmail }}" style="color:{{ $primary }}; text-decoration:none; font-weight:bold;">{{ $supportEmail }}</a>
                        @endif
                        @if(!empty($supportEmail) && !empty($supportPhone)) &nbsp;·&nbsp; @endif
                        @if(!empty($supportPhone))
                            <a href="tel:{{ preg_replace('/[^0-9+]/', '', $supportPhone) }}" style="color:{{ $primary }}; text-decoration:none; font-weight:bold;">{{ $supportPhone }}</a>
                        @endif
                    </td>
                </tr>
            @endif

            @if(!empty($homeUrl) || !empty($manageUrl))
                <tr>
                    <td style="padding:0 0 10px 0; font-family:Arial,Helvetica,sans-serif; font-size:13px; line-height:20px;">
                        @if(!empty($manageUrl))
                            <a href="{{ $manageUrl }}" target="_blank" style="color:{{ $primary }}; text-decoration:none;">Manage booking</a>
                        @endif
                        @if(!empty($manageUrl) && !empty($homeUrl)) &nbsp;·&nbsp; @endif
                        @if(!empty($homeUrl))
                            <a href="{{ $homeUrl }}" target="_blank" style="color:{{ $primary }}; text-decoration:none;">Home</a>
                        @endif
                    </td>
                </tr>
            @endif

            @if(!empty($address))
                <tr>
                    <td style="padding:0 0 8px 0; font-family:Arial,Helvetica,sans-serif; font-size:12px; line-height:18px; color:{{ $mutedColor }};">{{ $address }}</td>
                </tr>
            @endif

            <tr>
                <td style="padding:2px 0 0 0; font-family:Arial,Helvetica,sans-serif; font-size:12px; line-height:18px; color:{{ $mutedColor }};">
                    &copy; {{ $currentYear }} {{ $legalName }}. All rights reserved.
                </td>
            </tr>
        </table>
    </td>
</tr>
