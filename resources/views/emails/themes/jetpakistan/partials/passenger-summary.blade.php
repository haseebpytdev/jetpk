{{--
    Passenger summary. Input: $passengers, $emailBrand.
    Customer-safe names ONLY. No passport / document numbers here.
    Accepts a list of strings or a list of arrays with a 'name' (and optional 'type').
--}}
@php
    $brand       = (isset($emailBrand) && is_array($emailBrand)) ? $emailBrand : [];
    $borderColor = $brand['border_color']  ?? '#d9e6ee';
    $mutedColor  = $brand['muted_color']   ?? '#64748b';
    $textColor   = $brand['text_color']    ?? '#0f2435';
    $primary     = $brand['primary_color'] ?? '#00843D';

    $list = (isset($passengers) && is_array($passengers)) ? $passengers : [];
    $rows = [];
    foreach ($list as $p) {
        if (is_string($p)) {
            $name = trim($p);
            $ptype = null;
        } elseif (is_array($p)) {
            $name  = trim($p['name'] ?? '');
            $ptype = $p['type'] ?? ($p['pax_type'] ?? null);
        } else {
            $name = '';
            $ptype = null;
        }
        if ($name !== '') { $rows[] = ['name' => $name, 'type' => $ptype]; }
    }
@endphp
@if(!empty($rows))
    <table role="presentation" cellpadding="0" cellspacing="0" border="0" width="100%" style="margin:6px 0 14px 0; border:1px solid {{ $borderColor }}; border-radius:12px; background-color:#ffffff;">
        <tr>
            <td style="padding:16px 18px;">
                <div style="font-family:Arial,Helvetica,sans-serif; font-size:12px; letter-spacing:0.6px; text-transform:uppercase; color:{{ $primary }}; font-weight:bold; margin:0 0 8px 0;">Passengers</div>
                <table role="presentation" cellpadding="0" cellspacing="0" border="0" width="100%">
                    @foreach($rows as $r)
                        <tr>
                            <td style="padding:5px 0; font-family:Arial,Helvetica,sans-serif; font-size:14px; line-height:20px; color:{{ $textColor }}; font-weight:bold;">{{ $r['name'] }}</td>
                            @if(!empty($r['type']))
                                <td align="right" style="padding:5px 0; font-family:Arial,Helvetica,sans-serif; font-size:13px; line-height:20px; color:{{ $mutedColor }};">{{ $r['type'] }}</td>
                            @endif
                        </tr>
                    @endforeach
                </table>
            </td>
        </tr>
    </table>
@endif
