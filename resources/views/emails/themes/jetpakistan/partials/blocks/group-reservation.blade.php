@php
    $brand = (isset($emailBrand) && is_array($emailBrand)) ? $emailBrand : [];
    $reservation = (isset($reservation) && is_array($reservation)) ? $reservation : [];
@endphp

@if($reservation !== [])
    <table role="presentation" cellpadding="0" cellspacing="0" border="0" width="100%" style="margin:6px 0 14px 0; border:1px solid {{ $brand['border_color'] ?? '#d9e6ee' }}; border-radius:12px;">
        <tr>
            <td style="padding:16px 18px;">
                @include('emails.themes.jetpakistan.partials.info-row', ['label' => 'Reference', 'value' => $reservation['reference'] ?? null, 'emailBrand' => $brand])
                @include('emails.themes.jetpakistan.partials.info-row', ['label' => 'Route', 'value' => $reservation['route'] ?? null, 'emailBrand' => $brand])
                @include('emails.themes.jetpakistan.partials.info-row', ['label' => 'Seats', 'value' => $reservation['seats'] ?? null, 'emailBrand' => $brand])
                @include('emails.themes.jetpakistan.partials.info-row', ['label' => 'Deadline', 'value' => $reservation['deadline'] ?? ($reservation['expires_at'] ?? null), 'emailBrand' => $brand])
            </td>
        </tr>
    </table>
@endif
