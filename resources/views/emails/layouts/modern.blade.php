@php
    /** @var \App\Support\Branding\CompanyEmailProfile $companyEmailProfile */
    use App\Support\Emails\ModernEmailLayout;

    $primary = \App\Support\Emails\EmailTemplatePreviewRenderer::sanitizeColor($companyEmailProfile->primary_color);
    $secondary = \App\Support\Emails\EmailTemplatePreviewRenderer::sanitizeColor($companyEmailProfile->secondary_color);
    $layout = ModernEmailLayout::viewData([
        'emailMode' => $emailMode ?? ModernEmailLayout::MODE_CUSTOMER,
        'headline' => $headline ?? null,
        'intro' => $intro ?? null,
        'statusLabel' => $statusLabel ?? null,
        'statusBannerLabel' => $statusBannerLabel ?? null,
        'statusBannerTone' => $statusBannerTone ?? 'info',
        'headerLabel' => $headerLabel ?? null,
        'actionCardTitle' => $actionCardTitle ?? null,
        'actionCardBody' => $actionCardBody ?? null,
        'detailsTitle' => $detailsTitle ?? null,
        'details' => $details ?? [],
        'nextSteps' => $nextSteps ?? [],
        'contentHtml' => $contentHtml ?? '',
        'ctaUrl' => $ctaUrl ?? null,
        'ctaLabel' => $ctaLabel ?? null,
        'footerDisclaimer' => $footerDisclaimer ?? null,
    ]);
    $statusStyle = ModernEmailLayout::toneStyle((string) $layout['statusBannerTone']);
    $isOps = ($layout['emailMode'] ?? '') === ModernEmailLayout::MODE_OPS;
@endphp
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta http-equiv="X-UA-Compatible" content="IE=edge">
    <title>{{ $layout['headline'] ?: $companyEmailProfile->name }}</title>
    <style>
        body { margin: 0; padding: 0; width: 100% !important; -webkit-text-size-adjust: 100%; -ms-text-size-adjust: 100%; background-color: #f1f5f9; }
        table { border-collapse: collapse; mso-table-lspace: 0pt; mso-table-rspace: 0pt; }
        img { border: 0; height: auto; line-height: 100%; outline: none; text-decoration: none; max-width: 100%; }
        a { color: {{ $primary }}; }
        @media only screen and (max-width: 620px) {
            .email-container { width: 100% !important; }
            .email-card { padding: 20px 16px !important; }
            .email-header { padding: 20px 16px !important; }
            .stack-column { display: block !important; width: 100% !important; }
        }
    </style>
</head>
<body style="margin:0;padding:0;background-color:#f1f5f9;font-family:-apple-system,BlinkMacSystemFont,'Segoe UI',Roboto,Helvetica,Arial,sans-serif;font-size:15px;line-height:1.55;color:#1e293b;">
<table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="background-color:#f1f5f9;">
    <tr>
        <td align="center" style="padding:24px 12px;">
            <table role="presentation" class="email-container" width="620" cellpadding="0" cellspacing="0" style="max-width:620px;width:100%;">
                <tr>
                    <td class="email-header" style="background:linear-gradient(135deg, {{ $primary }} 0%, {{ $secondary }} 100%);border-radius:10px 10px 0 0;padding:22px 24px;text-align:center;">
                        @if($layout['headerLabel'])
                            <div style="font-size:10px;font-weight:700;color:rgba(255,255,255,0.82);letter-spacing:0.08em;text-transform:uppercase;margin-bottom:8px;">{{ $layout['headerLabel'] }}</div>
                        @endif
                        @if($companyEmailProfile->logo_url)
                            <img src="{{ $companyEmailProfile->logo_url }}" alt="{{ $companyEmailProfile->name }}" width="150" style="display:block;margin:0 auto 8px;max-height:48px;width:auto;">
                        @else
                            <div style="font-size:20px;font-weight:700;color:#ffffff;letter-spacing:0.02em;">{{ $companyEmailProfile->name }}</div>
                        @endif
                        @if($companyEmailProfile->legal_name && $companyEmailProfile->legal_name !== $companyEmailProfile->name)
                            <div style="font-size:11px;color:rgba(255,255,255,0.85);margin-top:3px;">{{ $companyEmailProfile->legal_name }}</div>
                        @endif
                        @if(!$isOps && ($companyEmailProfile->support_email || $companyEmailProfile->support_phone))
                            <div style="font-size:11px;color:rgba(255,255,255,0.88);margin-top:8px;">
                                @if($companyEmailProfile->support_email)
                                    {{ $companyEmailProfile->support_email }}
                                @endif
                                @if($companyEmailProfile->support_email && $companyEmailProfile->support_phone)
                                    <span> · </span>
                                @endif
                                @if($companyEmailProfile->support_phone)
                                    {{ $companyEmailProfile->support_phone }}
                                @endif
                            </div>
                        @endif
                        @if($layout['statusLabel'])
                            <div style="display:inline-block;margin-top:10px;padding:4px 10px;border-radius:999px;background:rgba(255,255,255,0.18);color:#ffffff;font-size:11px;font-weight:700;letter-spacing:0.03em;text-transform:uppercase;">{{ $layout['statusLabel'] }}</div>
                        @endif
                    </td>
                </tr>
                <tr>
                    <td class="email-card" style="background-color:#ffffff;padding:24px;border-radius:0 0 10px 10px;box-shadow:0 1px 3px rgba(15,23,42,0.08);">
                        @if($layout['statusBannerLabel'])
                            <table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="margin:0 0 14px;border-left:4px solid {{ $statusStyle['border'] }};background-color:{{ $statusStyle['bg'] }};border-radius:6px;">
                                <tr>
                                    <td style="padding:8px 12px;color:{{ $statusStyle['text'] }};font-size:12px;font-weight:700;line-height:1.35;">
                                        {{ $layout['statusBannerLabel'] }}
                                    </td>
                                </tr>
                            </table>
                        @endif

                        @if($layout['headline'])
                            <h1 style="margin:0 0 10px;font-size:20px;line-height:1.3;color:#0f172a;font-weight:700;">{{ $layout['headline'] }}</h1>
                        @endif
                        @if($layout['intro'])
                            <p style="margin:0 0 16px;color:#475569;font-size:13px;line-height:1.45;">{{ $layout['intro'] }}</p>
                        @endif

                        @if($isOps && $layout['actionCardBody'])
                            <table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="margin:0 0 16px;border:1px solid #dbeafe;background-color:#f8fafc;border-radius:7px;">
                                <tr>
                                    <td style="padding:12px 14px;">
                                        @if($layout['actionCardTitle'])
                                            <p style="margin:0 0 6px;font-size:12px;font-weight:700;color:#1e40af;text-transform:uppercase;letter-spacing:0.04em;">{{ $layout['actionCardTitle'] }}</p>
                                        @endif
                                        <p style="margin:0;color:#334155;font-size:13px;line-height:1.45;">{{ $layout['actionCardBody'] }}</p>
                                    </td>
                                </tr>
                            </table>
                        @endif

                        @if(!empty($layout['details']))
                            <p style="margin:0 0 6px;font-size:12px;font-weight:700;color:#64748b;text-transform:uppercase;letter-spacing:0.04em;">{{ $layout['detailsTitle'] }}</p>
                            <table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="margin:0 0 16px;border:1px solid #e2e8f0;border-radius:7px;overflow:hidden;">
                                @foreach($layout['details'] as $row)
                                    <tr>
                                        <td class="stack-column" style="padding:8px 12px;background-color:#f8fafc;font-weight:600;font-size:12px;color:#64748b;width:38%;border-bottom:1px solid #e2e8f0;vertical-align:top;">{{ $row['label'] }}</td>
                                        <td class="stack-column" style="padding:8px 12px;font-size:13px;color:#0f172a;border-bottom:1px solid #e2e8f0;vertical-align:top;">{!! $row['value'] !!}</td>
                                    </tr>
                                @endforeach
                            </table>
                        @endif

                        @if(!empty($layout['nextSteps']))
                            <p style="margin:0 0 6px;font-size:12px;font-weight:700;color:#64748b;text-transform:uppercase;letter-spacing:0.04em;">Next steps</p>
                            <ul style="margin:0 0 16px;padding-left:20px;color:#475569;font-size:13px;line-height:1.45;">
                                @foreach($layout['nextSteps'] as $step)
                                    <li style="margin:0 0 4px;">{{ $step }}</li>
                                @endforeach
                            </ul>
                        @endif

                        @if($layout['contentHtml'] !== '')
                            <div style="margin:0 0 16px;color:#334155;font-size:14px;line-height:1.45;">{!! $layout['contentHtml'] !!}</div>
                        @endif

                        @if($layout['ctaUrl'] && $layout['ctaLabel'])
                            <table role="presentation" cellpadding="0" cellspacing="0" style="margin:0 0 8px;">
                                <tr>
                                    <td style="border-radius:7px;background-color:{{ $primary }};">
                                        <a href="{{ $layout['ctaUrl'] }}" style="display:inline-block;padding:10px 18px;font-size:13px;font-weight:600;color:#ffffff;text-decoration:none;border-radius:7px;">{{ $layout['ctaLabel'] }}</a>
                                    </td>
                                </tr>
                            </table>
                        @endif

                        <table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="margin-top:20px;padding-top:14px;border-top:1px solid #e2e8f0;">
                            <tr>
                                <td style="font-size:13px;color:#64748b;">
                                    <strong style="color:#334155;">Need help?</strong><br>
                                    @if($companyEmailProfile->support_email)
                                        <a href="mailto:{{ $companyEmailProfile->support_email }}" style="color:{{ $primary }};">{{ $companyEmailProfile->support_email }}</a>
                                    @endif
                                    @if($companyEmailProfile->support_phone)
                                        @if($companyEmailProfile->support_email)<span style="color:#94a3b8;"> · </span>@endif
                                        {{ $companyEmailProfile->support_phone }}
                                    @endif
                                    @if($companyEmailProfile->website_url)
                                        <br><a href="{{ $companyEmailProfile->website_url }}" style="color:{{ $primary }};">{{ parse_url($companyEmailProfile->website_url, PHP_URL_HOST) ?: $companyEmailProfile->website_url }}</a>
                                    @endif
                                    @if($companyEmailProfile->address)
                                        <br><span style="color:#94a3b8;">{{ $companyEmailProfile->address }}</span>
                                    @endif
                                </td>
                            </tr>
                        </table>
                    </td>
                </tr>
                <tr>
                    <td style="padding:20px 8px 8px;text-align:center;font-size:12px;color:#94a3b8;line-height:1.5;">
                        @if($companyEmailProfile->footer_text)
                            <div style="margin-bottom:8px;">{{ $companyEmailProfile->footer_text }}</div>
                        @endif
                        @if($layout['footerDisclaimer'])
                            <div style="margin-bottom:6px;color:#64748b;">{{ $layout['footerDisclaimer'] }}</div>
                        @endif
                        <div>{{ $companyEmailProfile->mail_from_name }} · {{ $companyEmailProfile->mail_from_email }}</div>
                    </td>
                </tr>
            </table>
        </td>
    </tr>
</table>
</body>
</html>
