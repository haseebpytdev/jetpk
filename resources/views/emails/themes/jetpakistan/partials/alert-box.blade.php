{{-- Alert box. Inputs: $type (info|success|warning|error), $title, $message. --}}
@php
    $type = $type ?? 'info';
    $palette = [
        'info'    => ['bg' => '#eef6f9', 'border' => '#bcd9e6', 'title' => '#0b5b73', 'text' => '#334155'],
        'success' => ['bg' => '#e9f7ef', 'border' => '#a7e0bf', 'title' => '#0f7a3d', 'text' => '#25543a'],
        'warning' => ['bg' => '#fff5e6', 'border' => '#ffd699', 'title' => '#a15c00', 'text' => '#5c4527'],
        'error'   => ['bg' => '#fdecec', 'border' => '#f5b5b5', 'title' => '#b02121', 'text' => '#5c2727'],
    ];
    $c = $palette[$type] ?? $palette['info'];
@endphp
@if(!empty($title) || !empty($message))
    <table role="presentation" cellpadding="0" cellspacing="0" border="0" width="100%" style="margin:6px 0 14px 0;">
        <tr>
            <td style="background-color:{{ $c['bg'] }}; border:1px solid {{ $c['border'] }}; border-radius:10px; padding:14px 16px;">
                @if(!empty($title))
                    <div style="font-family:Arial,Helvetica,sans-serif; font-size:15px; line-height:22px; font-weight:bold; color:{{ $c['title'] }}; margin:0 0 4px 0;">{{ $title }}</div>
                @endif
                @if(!empty($message))
                    <div style="font-family:Arial,Helvetica,sans-serif; font-size:14px; line-height:21px; color:{{ $c['text'] }};">{{ $message }}</div>
                @endif
            </td>
        </tr>
    </table>
@endif
