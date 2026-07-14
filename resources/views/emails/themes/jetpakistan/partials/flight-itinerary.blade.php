{{--
    Flight itinerary. Input: $itinerary, $emailBrand.
    Accepts either:
      $itinerary = [ ['label'=>'Outbound','from'=>'KHI','to'=>'DXB', ...], ... ]
    or a single segment array. Fully null-safe.
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
        // If associative single segment, wrap it; else assume list of segments.
        $isList = array_keys($raw) === range(0, count($raw) - 1);
        $segments = $isList ? $raw : [$raw];
    }
@endphp
@if(!empty($segments))
    <table role="presentation" cellpadding="0" cellspacing="0" border="0" width="100%" style="margin:6px 0 14px 0;">
        <tr>
            <td style="padding:0;">
                <div style="font-family:Arial,Helvetica,sans-serif; font-size:12px; letter-spacing:0.6px; text-transform:uppercase; color:{{ $primary }}; font-weight:bold; margin:0 0 8px 0;">Flight itinerary</div>
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
                        $carrier  = trim(($airline ?? '').(($airline && $flightNo) ? ' · ' : '').($flightNo ?? ''));
                    @endphp
                    <table role="presentation" cellpadding="0" cellspacing="0" border="0" width="100%" style="border:1px solid {{ $borderColor }}; border-radius:12px; background-color:#ffffff; margin:0 0 10px 0;">
                        @if(!empty($label))
                            <tr><td colspan="3" style="padding:12px 16px 0 16px; font-family:Arial,Helvetica,sans-serif; font-size:13px; font-weight:bold; color:{{ $primary }};">{{ $label }}</td></tr>
                        @endif
                        <tr>
                            <td valign="top" style="padding:10px 8px 10px 16px; font-family:Arial,Helvetica,sans-serif;" class="jetpk-stack">
                                <div style="font-size:20px; font-weight:bold; color:{{ $textColor }};">{{ $from ?? '—' }}</div>
                                @if(!empty($fromName))<div style="font-size:12px; color:{{ $mutedColor }};">{{ $fromName }}</div>@endif
                                @if(!empty($depart))<div style="font-size:13px; color:{{ $textColor }}; margin-top:3px;">{{ $depart }}</div>@endif
                            </td>
                            <td valign="middle" align="center" style="padding:10px 4px; font-family:Arial,Helvetica,sans-serif; color:{{ $mutedColor }};" class="jetpk-stack">
                                <div style="font-size:12px;">&#9992;</div>
                                @if(!empty($stops))<div style="font-size:11px;">{{ $stops }}</div>@endif
                            </td>
                            <td valign="top" align="right" style="padding:10px 16px 10px 8px; font-family:Arial,Helvetica,sans-serif;" class="jetpk-stack">
                                <div style="font-size:20px; font-weight:bold; color:{{ $textColor }};">{{ $to ?? '—' }}</div>
                                @if(!empty($toName))<div style="font-size:12px; color:{{ $mutedColor }};">{{ $toName }}</div>@endif
                                @if(!empty($arrive))<div style="font-size:13px; color:{{ $textColor }}; margin-top:3px;">{{ $arrive }}</div>@endif
                            </td>
                        </tr>
                        @if(!empty($carrier) || !empty($baggage))
                            <tr>
                                <td colspan="3" style="padding:0 16px 12px 16px; font-family:Arial,Helvetica,sans-serif; font-size:12px; color:{{ $mutedColor }}; border-top:1px solid {{ $borderColor }}; padding-top:10px;">
                                    @if(!empty($carrier)){{ $carrier }}@endif
                                    @if(!empty($carrier) && !empty($baggage)) &nbsp;·&nbsp; @endif
                                    @if(!empty($baggage))Baggage: {{ $baggage }}@endif
                                </td>
                            </tr>
                        @endif
                    </table>
                @endforeach
            </td>
        </tr>
    </table>
@endif
