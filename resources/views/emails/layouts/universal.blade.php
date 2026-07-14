@php
    $company = is_array($company ?? null) ? $company : [];
    $primary = \App\Support\Emails\EmailTemplatePreviewRenderer::sanitizeColor((string) ($company['primary_color'] ?? '#0f766e'));
    $secondary = \App\Support\Emails\EmailTemplatePreviewRenderer::sanitizeColor((string) ($company['secondary_color'] ?? '#0369a1'));
    $companyName = (string) ($company['name'] ?? config('app.name', 'OTA'));
    $logoUrl = $company['logo_url'] ?? null;
    $supportEmail = $company['support_email'] ?? null;
    $supportPhone = $company['support_phone'] ?? null;
    $websiteUrl = $company['website_url'] ?? null;
    $address = $company['address'] ?? null;
    $footerText = $company['footer_text'] ?? null;
    $mailFromName = (string) ($company['mail_from_name'] ?? $companyName);
    $mailFromEmail = (string) ($company['mail_from_email'] ?? config('mail.from.address'));
@endphp
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta http-equiv="X-UA-Compatible" content="IE=edge">
    <title>{{ $title ?? $companyName }}</title>
    <style>
        body { margin: 0; padding: 0; width: 100% !important; -webkit-text-size-adjust: 100%; -ms-text-size-adjust: 100%; background-color: #f1f5f9; }
        table { border-collapse: collapse; mso-table-lspace: 0pt; mso-table-rspace: 0pt; }
        img { border: 0; height: auto; line-height: 100%; outline: none; text-decoration: none; max-width: 100%; }
        a { color: {{ $primary }}; }
        @media only screen and (max-width: 600px) {
            .email-container { width: 100% !important; }
            .email-pad { padding-left: 10px !important; padding-right: 10px !important; }
            .email-card { padding: 18px 14px !important; }
            .email-header { padding: 18px 16px !important; }
            .email-footer { padding-left: 8px !important; padding-right: 8px !important; }
            .stack-column { display: block !important; width: 100% !important; }
        }
    </style>
</head>
<body style="margin:0;padding:0;background-color:#eef2f7;font-family:-apple-system,BlinkMacSystemFont,'Segoe UI',Roboto,Helvetica,Arial,sans-serif;font-size:14px;line-height:1.45;color:#1e293b;">
<table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="background-color:#eef2f7;">
    <tr>
        <td align="center" class="email-pad" style="padding:18px 10px;">
            <table role="presentation" class="email-container" width="600" cellpadding="0" cellspacing="0" style="max-width:600px;width:100%;">
                <tr>
                    <td class="email-header" style="background:linear-gradient(135deg, {{ $primary }} 0%, {{ $secondary }} 100%);border-radius:12px 12px 0 0;padding:20px 26px;text-align:left;">
                        @if($logoUrl)
                            <img src="{{ $logoUrl }}" alt="{{ $companyName }}" width="140" style="display:block;margin:0 0 8px;max-height:42px;width:auto;">
                        @else
                            <div style="font-size:19px;font-weight:700;color:#ffffff;letter-spacing:0.01em;">{{ $companyName }}</div>
                        @endif
                        <div style="font-size:11px;color:rgba(255,255,255,0.86);margin-top:3px;">Online travel assistance</div>
                    </td>
                </tr>
                <tr>
                    <td class="email-card" style="background-color:#ffffff;padding:24px 26px;border-radius:0 0 12px 12px;box-shadow:0 1px 3px rgba(15,23,42,0.08);">
                        @yield('content')
                        <table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="margin-top:22px;padding-top:14px;border-top:1px solid #e2e8f0;">
                            <tr>
                                <td style="font-size:12px;color:#64748b;line-height:1.45;">
                                    <strong style="color:#334155;">Need help?</strong><br>
                                    @if($supportEmail)
                                        <a href="mailto:{{ $supportEmail }}" style="color:{{ $primary }};">{{ $supportEmail }}</a>
                                    @endif
                                    @if($supportPhone)
                                        @if($supportEmail)<span style="color:#94a3b8;"> · </span>@endif
                                        {{ $supportPhone }}
                                    @endif
                                    @if($websiteUrl)
                                        <br><a href="{{ $websiteUrl }}" style="color:{{ $primary }};">{{ parse_url((string) $websiteUrl, PHP_URL_HOST) ?: $websiteUrl }}</a>
                                    @endif
                                    @if($address)
                                        <br><span style="color:#94a3b8;">{{ $address }}</span>
                                    @endif
                                </td>
                            </tr>
                        </table>
                    </td>
                </tr>
                <tr>
                    <td class="email-footer" style="padding:14px 8px 4px;text-align:center;font-size:11px;color:#94a3b8;line-height:1.45;">
                        @if($footerText)
                            <div style="margin-bottom:6px;">{{ $footerText }}</div>
                        @endif
                        <div style="margin-bottom:5px;color:#64748b;">Automated transactional email from {{ $companyName }}. Do not reply with payment card, passport, or password details.</div>
                        <div>{{ $mailFromName }} · {{ $mailFromEmail }}</div>
                    </td>
                </tr>
            </table>
        </td>
    </tr>
</table>
</body>
</html>
