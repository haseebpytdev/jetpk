{{-- Bulletproof CTA button. Inputs: $text, $url, $variant (primary|secondary), $emailBrand --}}
@php
    $brand   = (isset($emailBrand) && is_array($emailBrand)) ? $emailBrand : [];
    $variant = $variant ?? 'primary';
    $accent  = $brand['accent_color']  ?? '#F58220';
    $primary = $brand['primary_color'] ?? '#00843D';
    $btnBg   = $variant === 'secondary' ? $primary : $accent;
    $btnText = '#ffffff';
@endphp
@if(!empty($url) && !empty($text))
    <table role="presentation" class="jetpk-btn" cellpadding="0" cellspacing="0" border="0" width="100%">
        <tr>
            <td align="center" style="padding:6px 0 6px 0;">
                <!--[if mso]>
                <v:roundrect xmlns:v="urn:schemas-microsoft-com:vml" xmlns:w="urn:schemas-microsoft-com:office:word" href="{{ $url }}" style="height:50px;v-text-anchor:middle;width:260px;" arcsize="50%" strokecolor="{{ $btnBg }}" fillcolor="{{ $btnBg }}">
                <w:anchorlock/>
                <center style="color:{{ $btnText }};font-family:Arial,Helvetica,sans-serif;font-size:16px;font-weight:bold;">{{ $text }}</center>
                </v:roundrect>
                <![endif]-->
                <!--[if !mso]><!-- -->
                <a href="{{ $url }}" target="_blank" style="background-color:{{ $btnBg }}; border-radius:999px; color:{{ $btnText }}; display:inline-block; font-family:Arial,Helvetica,sans-serif; font-size:16px; font-weight:bold; line-height:20px; padding:15px 34px; text-align:center; text-decoration:none; mso-padding-alt:0;">{{ $text }}</a>
                <!--<![endif]-->
            </td>
        </tr>
    </table>
@endif
