{{--
    JetPakistan Universal Email Base Layout
    View key: emails.themes.jetpakistan.layouts.base

    Email-safe rules honoured here:
      - Table-based outer layout, inline critical CSS, system fonts only.
      - No web fonts, no JS, no background images, no CSS grid/flex for layout.
      - Max width 640px, responsive via a minimal <style> media block.
      - Brand colours resolved to PHP locals so they can be inlined (no CSS vars).

    Expected (all optional, null-safe):
      $emailBrand, $subjectText, $preheaderText, $headline, $introText,
      $ctaText, $ctaUrl, $recipientName, $meta, $booking, $payment,
      $passengers, $itinerary, $support, $security
--}}
@php
    $brand        = (isset($emailBrand) && is_array($emailBrand)) ? $emailBrand : [];
    $brandName    = $brand['brand_name']       ?? 'JetPakistan';
    $legalName    = $brand['legal_name']       ?? $brandName;
    $primary      = $brand['primary_color']    ?? '#00843D';
    $accent       = $brand['accent_color']     ?? '#F58220';
    $textColor    = $brand['text_color']       ?? '#0f2435';
    $mutedColor   = $brand['muted_color']      ?? '#64748b';
    $bgColor      = $brand['background_color'] ?? '#eef6f9';
    $cardColor    = $brand['card_color']       ?? '#ffffff';
    $borderColor  = $brand['border_color']     ?? '#d9e6ee';
    $homeUrl      = $brand['home_url']          ?? '#';
    $footerText   = $brand['footer_text']      ?? 'You are receiving this email because you used '.$brandName.' services.';
    $preheader    = $preheaderText ?? ($subjectText ?? '');
    $currentYear  = date('Y');
@endphp
<!DOCTYPE html>
<html lang="en" xmlns="http://www.w3.org/1999/xhtml" xmlns:v="urn:schemas-microsoft-com:vml" xmlns:o="urn:schemas-microsoft-com:office:office">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta http-equiv="X-UA-Compatible" content="IE=edge">
    <meta name="x-apple-disable-message-reformatting">
    <meta name="color-scheme" content="light only">
    <meta name="supported-color-schemes" content="light">
    <title>{{ $subjectText ?? $brandName }}</title>
    <!--[if mso]>
    <noscript><xml><o:OfficeDocumentSettings><o:PixelsPerInch>96</o:PixelsPerInch></o:OfficeDocumentSettings></xml></noscript>
    <![endif]-->
    <style>
        /* Client resets */
        html, body { margin: 0 !important; padding: 0 !important; height: 100% !important; width: 100% !important; }
        * { -ms-text-size-adjust: 100%; -webkit-text-size-adjust: 100%; }
        table, td { mso-table-lspace: 0pt !important; mso-table-rspace: 0pt !important; border-collapse: collapse !important; }
        img { -ms-interpolation-mode: bicubic; border: 0; height: auto; line-height: 100%; outline: none; text-decoration: none; }
        a { text-decoration: none; }
        body, table, td, a { font-family: Arial, Helvetica, sans-serif; }

        /* Mobile */
        @media only screen and (max-width: 640px) {
            .jetpk-container { width: 100% !important; max-width: 100% !important; border-radius: 0 !important; }
            .jetpk-pad { padding-left: 20px !important; padding-right: 20px !important; }
            .jetpk-btn a { display: block !important; width: 100% !important; box-sizing: border-box !important; text-align: center !important; }
            .jetpk-h1 { font-size: 24px !important; line-height: 30px !important; }
            .jetpk-stack { display: block !important; width: 100% !important; }
            .jetpk-otp { font-size: 30px !important; letter-spacing: 8px !important; }
        }
    </style>
</head>
<body style="margin:0; padding:0; width:100%; background-color:{{ $bgColor }}; color:{{ $textColor }};">
    {{-- Hidden preheader --}}
    <div style="display:none; font-size:1px; color:{{ $bgColor }}; line-height:1px; max-height:0; max-width:0; opacity:0; overflow:hidden; mso-hide:all;">
        {{ $preheader }}&nbsp;&zwnj;&nbsp;&zwnj;&nbsp;&zwnj;&nbsp;&zwnj;&nbsp;&zwnj;&nbsp;&zwnj;&nbsp;&zwnj;&nbsp;&zwnj;&nbsp;&zwnj;&nbsp;&zwnj;&nbsp;&zwnj;
    </div>

    {{-- Outer wrapper --}}
    <table role="presentation" cellpadding="0" cellspacing="0" border="0" width="100%" style="background-color:{{ $bgColor }};">
        <tr>
            <td align="center" style="padding:24px 12px;">

                {{-- Main container / card --}}
                <table role="presentation" class="jetpk-container" cellpadding="0" cellspacing="0" border="0" width="640" style="width:640px; max-width:640px; background-color:{{ $cardColor }}; border:1px solid {{ $borderColor }}; border-radius:14px; overflow:hidden;">

                    @include('emails.themes.jetpakistan.partials.header', ['emailBrand' => $brand])

                    {{-- Content --}}
                    <tr>
                        <td class="jetpk-pad" style="padding:28px 36px 8px 36px;">
                            @if(!empty($headline))
                                <h1 class="jetpk-h1" style="margin:0 0 12px 0; font-family:Arial,Helvetica,sans-serif; font-size:26px; line-height:32px; font-weight:bold; color:{{ $textColor }};">{{ $headline }}</h1>
                            @endif

                            @if(!empty($recipientName))
                                <p style="margin:0 0 12px 0; font-family:Arial,Helvetica,sans-serif; font-size:16px; line-height:24px; color:{{ $textColor }};">Hi {{ $recipientName }},</p>
                            @endif

                            @if(!empty($introText))
                                <p style="margin:0 0 8px 0; font-family:Arial,Helvetica,sans-serif; font-size:16px; line-height:24px; color:{{ $textColor }};">{{ $introText }}</p>
                            @endif
                        </td>
                    </tr>

                    {{-- Template-specific body --}}
                    <tr>
                        <td class="jetpk-pad" style="padding:8px 36px 8px 36px;">
                            @yield('content')
                        </td>
                    </tr>

                    {{-- Primary CTA (only if a URL is present) --}}
                    @if(!empty($ctaUrl) && !empty($ctaText))
                        <tr>
                            <td class="jetpk-pad" style="padding:12px 36px 24px 36px;">
                                @include('emails.themes.jetpakistan.partials.button', ['text' => $ctaText, 'url' => $ctaUrl, 'variant' => 'primary', 'emailBrand' => $brand])
                            </td>
                        </tr>
                    @endif

                    @include('emails.themes.jetpakistan.partials.footer', ['emailBrand' => $brand])

                </table>

                {{-- Sub-footer note under the card --}}
                <table role="presentation" cellpadding="0" cellspacing="0" border="0" width="640" style="width:640px; max-width:640px;" class="jetpk-container">
                    <tr>
                        <td class="jetpk-pad" style="padding:16px 36px 8px 36px; text-align:center; font-family:Arial,Helvetica,sans-serif; font-size:12px; line-height:18px; color:{{ $mutedColor }};">
                            {{ $footerText }}
                        </td>
                    </tr>
                </table>

            </td>
        </tr>
    </table>
</body>
</html>
