@php
    $brand = (isset($emailBrand) && is_array($emailBrand)) ? $emailBrand : [];
    $application = (isset($application) && is_array($application)) ? $application : [];
@endphp

@if($application !== [])
    <table role="presentation" cellpadding="0" cellspacing="0" border="0" width="100%" style="margin:6px 0 14px 0; border:1px solid {{ $brand['border_color'] ?? '#d9e6ee' }}; border-radius:12px;">
        <tr>
            <td style="padding:16px 18px;">
                @include('emails.themes.jetpakistan.partials.info-row', ['label' => 'Agency name', 'value' => $application['agency_name'] ?? null, 'emailBrand' => $brand])
                @include('emails.themes.jetpakistan.partials.info-row', ['label' => 'Reference', 'value' => $application['reference'] ?? null, 'emailBrand' => $brand])
            </td>
        </tr>
    </table>
@endif
