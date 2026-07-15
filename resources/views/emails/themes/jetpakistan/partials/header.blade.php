{{-- Header partial. Input: $emailBrand. Outputs one <tr>. --}}
@php
    $brand       = (isset($emailBrand) && is_array($emailBrand)) ? $emailBrand : [];
    $brandName   = $brand['brand_name']    ?? 'JetPakistan';
    $primary     = $brand['primary_color'] ?? '#00843D';
    $accent      = $brand['accent_color']  ?? '#F58220';
    $borderColor = $brand['border_color']  ?? '#d9e6ee';
    $logoUrl     = $brand['logo_url']       ?? null;
    $homeUrl     = $brand['home_url']       ?? null;
@endphp
<tr>
    <td style="padding:22px 36px; border-bottom:3px solid {{ $primary }}; background-color:#ffffff;" class="jetpk-pad">
        <table role="presentation" cellpadding="0" cellspacing="0" border="0" width="100%">
            <tr>
                <td align="left" valign="middle" style="font-family:Arial,Helvetica,sans-serif;">
                    @if(!empty($logoUrl) && !empty($homeUrl))
                        <a href="{{ $homeUrl }}" target="_blank" style="text-decoration:none;">
                            <img src="{{ $logoUrl }}" alt="{{ $brandName }}" height="38" style="display:block; height:38px; max-height:38px; width:auto; border:0;">
                        </a>
                    @elseif(!empty($logoUrl))
                        <img src="{{ $logoUrl }}" alt="{{ $brandName }}" height="38" style="display:block; height:38px; max-height:38px; width:auto; border:0;">
                    @else
                        {{-- Safe text fallback: never a Master logo --}}
                        <span style="font-family:Arial,Helvetica,sans-serif; font-size:22px; font-weight:bold; color:{{ $primary }}; letter-spacing:0.3px;">Jet<span style="color:{{ $accent }};">Pakistan</span></span>
                    @endif
                </td>
                @if(!empty($homeUrl))
                    <td align="right" valign="middle" style="font-family:Arial,Helvetica,sans-serif; font-size:13px;">
                        <a href="{{ $homeUrl }}" target="_blank" style="color:{{ $primary }}; text-decoration:none; font-weight:bold;">Visit site</a>
                    </td>
                @endif
            </tr>
        </table>
    </td>
</tr>
