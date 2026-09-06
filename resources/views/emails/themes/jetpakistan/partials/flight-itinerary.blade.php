{{--
    Flight itinerary. Input: $itinerary, $emailBrand.
    Always stacked (origin → connector → destination) so Gmail clients that
    strip media queries never keep a cramped 3-column row.
--}}
@php
    $brand       = (isset($emailBrand) && is_array($emailBrand)) ? $emailBrand : [];
    $borderColor = $brand['border_color']  ?? '#d9e6ee';
    $mutedColor  = $brand['muted_color']   ?? '#64748b';
    $textColor   = $brand['text_color']    ?? '#0f2435';
    $primary     = $brand['primary_color'] ?? '#00843D';

    $raw = $itinerary ?? null;
    $segments = [];
    if (is_array($raw)) {
        $isList = array_keys($raw) === range(0, count($raw) - 1);
        $segments = $isList ? $raw : [$raw];
    }
@endphp
@if(!empty($segments))
    <table role="presentation" cellpadding="0" cellspacing="0" border="0" width="100%" style="margin:6px 0 14px 0;">
        <tr>
            <td style="padding:0 0 8px 0; font-family:Arial,Helvetica,sans-serif; font-size:12px; letter-spacing:0.6px; text-transform:uppercase; color:{{ $primary }}; font-weight:bold;">Flight itinerary</td>
        </tr>
        @foreach($segments as $seg)
            @php
                $seg      = is_array($seg) ? $seg : [];
                $label    = $seg['label']    ?? null;
                $from     = $seg['from']     ?? null;
                $to       = $seg['to']       ?? null;
                $fromName = $seg['from_name'] ?? null;
                $toName   = $seg['to_name']  ?? null;
                $depart   = $seg['depart']   ?? null;
                $arrive   = $seg['arrive']   ?? null;
                $airline  = $seg['airline']  ?? null;
                $flightNo = $seg['flight_no'] ?? null;
                $stops    = $seg['stops']    ?? null;
                $baggage  = $seg['baggage']  ?? null;
                $cabin    = $seg['cabin']    ?? ($seg['fare_brand'] ?? null);
                $terminal = $seg['terminal'] ?? null;
                $carrier  = trim(($airline ?? '').(($airline && $flightNo) ? ' · ' : '').($flightNo ?? ''));
            @endphp
            <tr>
                <td style="padding:0 0 10px 0;">
                    <table role="presentation" cellpadding="0" cellspacing="0" border="0" width="100%" style="width:100%;">
                        <tr>
                            <td style="border:1px solid {{ $borderColor }}; border-radius:12px; background-color:#ffffff; padding:16px 20px;">
                                <table role="presentation" cellpadding="0" cellspacing="0" border="0" width="100%">
                                    @if(!empty($label))
                                        <tr>
                                            <td style="padding:16px 20px 0 20px; font-family:Arial,Helvetica,sans-serif; font-size:13px; font-weight:bold; color:{{ $primary }};">{{ $label }}</td>
                                        </tr>
                                    @endif
                                    <tr>
                                        <td style="padding:12px 20px 8px 20px; font-family:Arial,Helvetica,sans-serif;">
                                            <div style="font-size:20px; line-height:26px; font-weight:bold; color:{{ $textColor }};">{{ $from ?? '—' }}</div>
                                            @if(!empty($fromName))<div class="jetpk-long" style="font-size:12px; line-height:18px; color:{{ $mutedColor }}; word-break:break-word;">{{ $fromName }}</div>@endif
                                            @if(!empty($depart))<div style="font-size:13px; line-height:19px; color:{{ $textColor }}; margin-top:3px;">{{ $depart }}</div>@endif
                                        </td>
                                    </tr>
                                    <tr>
                                        <td style="padding:0 20px 8px 20px; font-family:Arial,Helvetica,sans-serif; color:{{ $mutedColor }};">
                                            <div style="font-size:12px; line-height:18px;">&#8595;@if(!empty($stops)) {{ $stops }}@endif</div>
                                        </td>
                                    </tr>
                                    <tr>
                                        <td style="padding:0 20px 12px 20px; font-family:Arial,Helvetica,sans-serif;">
                                            <div style="font-size:20px; line-height:26px; font-weight:bold; color:{{ $textColor }};">{{ $to ?? '—' }}</div>
                                            @if(!empty($toName))<div class="jetpk-long" style="font-size:12px; line-height:18px; color:{{ $mutedColor }}; word-break:break-word;">{{ $toName }}</div>@endif
                                            @if(!empty($arrive))<div style="font-size:13px; line-height:19px; color:{{ $textColor }}; margin-top:3px;">{{ $arrive }}</div>@endif
                                        </td>
                                    </tr>
                                    @if(!empty($carrier) || !empty($baggage) || !empty($cabin) || !empty($terminal))
                                        <tr>
                                            <td style="padding:10px 20px 16px 20px; font-family:Arial,Helvetica,sans-serif; font-size:12px; line-height:18px; color:{{ $mutedColor }}; border-top:1px solid {{ $borderColor }};">
                                                @if(!empty($carrier))<div class="jetpk-long" style="word-break:break-word;">{{ $carrier }}</div>@endif
                                                @if(!empty($cabin))<div>Cabin: {{ $cabin }}</div>@endif
                                                @if(!empty($terminal))<div>Terminal: {{ $terminal }}</div>@endif
                                                @if(!empty($baggage))<div>Baggage: {{ $baggage }}</div>@endif
                                            </td>
                                        </tr>
                                    @endif
                                </table>
                            </td>
                        </tr>
                    </table>
                </td>
            </tr>
        @endforeach
    </table>
@endif
