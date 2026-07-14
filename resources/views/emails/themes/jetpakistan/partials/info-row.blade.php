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
        <td valign="top" style="padding:7px 12px 7px 0; font-family:Arial,Helvetica,sans-serif; font-size:14px; line-height:20px; color:{{ $mutedColor }}; white-space:nowrap;">{{ $label ?? '' }}</td>
        <td valign="top" align="right" style="padding:7px 0; font-family:Arial,Helvetica,sans-serif; font-size:14px; line-height:20px; color:{{ $textColor }}; font-weight:bold;">{{ $val }}</td>
    </tr>
@endif
