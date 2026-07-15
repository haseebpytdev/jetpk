<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>{{ $subjectLine }}</title>
</head>
<body style="margin:0;padding:0;background:#f4f6f8;font-family:Arial,Helvetica,sans-serif;color:#1a1a1a;">
<table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="background:#f4f6f8;padding:24px 12px;">
    <tr>
        <td align="center">
            <table role="presentation" width="600" cellpadding="0" cellspacing="0" style="max-width:600px;width:100%;background:#ffffff;border-radius:8px;overflow:hidden;">
                <tr>
                    <td style="padding:24px 28px 8px;">
                        <p style="margin:0 0 8px;font-size:13px;color:#6b7280;">{{ $brandName }}</p>
                        <h1 style="margin:0 0 12px;font-size:22px;line-height:1.3;color:#111827;">Top flight offers from your recent search</h1>
                        <p style="margin:0 0 16px;font-size:15px;line-height:1.5;color:#374151;">
                            You searched for flights on <strong>{{ $routeLabel }}</strong> ({{ $tripTypeLabel }}).
                        </p>
                    </td>
                </tr>
                <tr>
                    <td style="padding:0 28px 16px;">
                        <table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="background:#f9fafb;border-radius:6px;">
                            <tr>
                                <td style="padding:14px 16px;font-size:14px;line-height:1.6;">
                                    <strong>Search summary</strong><br>
                                    Route: {{ $routeLabel }}<br>
                                    Trip: {{ $tripTypeLabel }}<br>
                                    Depart: {{ $departDate ?: '—' }}@if ($returnDate)<br>Return: {{ $returnDate }}@endif<br>
                                    Passengers: {{ $passengerSummary }}
                                </td>
                            </tr>
                        </table>
                    </td>
                </tr>
                <tr>
                    <td style="padding:8px 28px 8px;">
                        <p style="margin:0 0 12px;font-size:15px;font-weight:bold;color:#111827;">Top fares (when you searched)</p>
                    </td>
                </tr>
                @foreach ($offers as $offer)
                    <tr>
                        <td style="padding:0 28px 12px;">
                            <table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="border:1px solid #e5e7eb;border-radius:6px;">
                                <tr>
                                    <td style="padding:14px 16px;">
                                        <p style="margin:0 0 6px;font-size:15px;font-weight:bold;color:#111827;">
                                            {{ $offer['airline_name'] ?: $offer['airline_code'] }}
                                            @if ($offer['airline_code'])
                                                <span style="font-weight:normal;color:#6b7280;">({{ $offer['airline_code'] }})</span>
                                            @endif
                                        </p>
                                        <p style="margin:0 0 4px;font-size:14px;color:#374151;">
                                            {{ $offer['origin'] }} → {{ $offer['destination'] }}
                                        </p>
                                        <p style="margin:0 0 4px;font-size:13px;color:#6b7280;">
                                            Depart {{ $offer['departure_at'] }} · Arrive {{ $offer['arrival_at'] }}
                                        </p>
                                        <p style="margin:0 0 8px;font-size:13px;color:#6b7280;">
                                            {{ $offer['stops_label'] }}@if ($offer['duration']) · {{ $offer['duration'] }}@endif
                                        </p>
                                        <p style="margin:0;font-size:16px;font-weight:bold;color:#0f766e;">{{ $offer['price_label'] }}</p>
                                    </td>
                                </tr>
                            </table>
                        </td>
                    </tr>
                @endforeach
                <tr>
                    <td style="padding:8px 28px 16px;">
                        <p style="margin:0;font-size:13px;line-height:1.5;color:#6b7280;">
                            Fares were available when you searched and may change. Please search again to confirm live availability.
                        </p>
                    </td>
                </tr>
                <tr>
                    <td style="padding:0 28px 24px;" align="center">
                        <a href="{{ $ctaUrl }}"
                           style="display:inline-block;background:#0f766e;color:#ffffff;text-decoration:none;font-size:15px;font-weight:bold;padding:12px 22px;border-radius:6px;">
                            Search again / View latest fares
                        </a>
                    </td>
                </tr>
                @if ($supportEmail || $supportPhone)
                    <tr>
                        <td style="padding:0 28px 24px;font-size:13px;color:#6b7280;line-height:1.5;">
                            Need help? Contact {{ $brandName }}:
                            @if ($supportEmail)<a href="mailto:{{ $supportEmail }}" style="color:#0f766e;">{{ $supportEmail }}</a>@endif
                            @if ($supportEmail && $supportPhone) · @endif
                            @if ($supportPhone){{ $supportPhone }}@endif
                        </td>
                    </tr>
                @endif
            </table>
        </td>
    </tr>
</table>
</body>
</html>
