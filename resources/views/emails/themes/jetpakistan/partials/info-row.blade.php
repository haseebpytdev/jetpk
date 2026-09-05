{{-- Key/value row. Inputs: $label, $value. Hidden when value is null/empty. Outputs one <tr>. --}}
@php
    $brand      = (isset($emailBrand) && is_array($emailBrand)) ? $emailBrand : [];
    $textColor  = $brand['text_color']   ?? '#0f2435';
    $mutedColor = $brand['muted_color']  ?? '#64748b';
    $val        = $value ?? null;
    $show       = !(is_null($val) || (is_string($val) && trim($val) === '') || (is_array($val) && count($val) === 0));
@endphp
@if($show)
    <tr>
        <td valign="top" style="padding:8px 0; font-family:Arial,Helvetica,sans-serif;">
            <div style="font-size:12px; line-height:18px; color:{{ $mutedColor }}; margin:0 0 2px 0;">{{ $label ?? '' }}</div>
            <div class="jetpk-long" style="font-size:15px; line-height:22px; color:{{ $textColor }}; font-weight:bold; word-break:break-word; overflow-wrap:break-word;">{{ $val }}</div>
        </td>
    </tr>
@endif
