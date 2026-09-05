@php
    $brand = (isset($emailBrand) && is_array($emailBrand)) ? $emailBrand : [];
    $application = (isset($application) && is_array($application))
        ? $application
        : ((isset($agent_application) && is_array($agent_application)) ? $agent_application : []);
@endphp

@if($application !== [])
    <table role="presentation" cellpadding="0" cellspacing="0" border="0" width="100%" style="margin:6px 0 14px 0; border:1px solid {{ $brand['border_color'] ?? '#d9e6ee' }}; border-radius:12px;">
        <tr>
            <td style="padding:16px 18px;">
                @include('emails.themes.jetpakistan.partials.info-row', ['label' => 'Application ID', 'value' => $application['reference'] ?? ($application['application_reference'] ?? null), 'emailBrand' => $brand])
                @include('emails.themes.jetpakistan.partials.info-row', ['label' => 'Applicant', 'value' => $application['applicant_name'] ?? null, 'emailBrand' => $brand])
                @include('emails.themes.jetpakistan.partials.info-row', ['label' => 'Agency / company', 'value' => $application['agency_name'] ?? null, 'emailBrand' => $brand])
                @include('emails.themes.jetpakistan.partials.info-row', ['label' => 'Email', 'value' => $application['email'] ?? ($application['applicant_email'] ?? null), 'emailBrand' => $brand])
                @include('emails.themes.jetpakistan.partials.info-row', ['label' => 'Phone', 'value' => $application['phone'] ?? ($application['applicant_phone'] ?? null), 'emailBrand' => $brand])
                @include('emails.themes.jetpakistan.partials.info-row', ['label' => 'City', 'value' => $application['city'] ?? null, 'emailBrand' => $brand])
                @include('emails.themes.jetpakistan.partials.info-row', ['label' => 'Country', 'value' => $application['country'] ?? null, 'emailBrand' => $brand])
                @include('emails.themes.jetpakistan.partials.info-row', ['label' => 'Submitted', 'value' => $application['submitted_at'] ?? null, 'emailBrand' => $brand])
                @include('emails.themes.jetpakistan.partials.info-row', ['label' => 'Status', 'value' => $application['status'] ?? ($application['application_status'] ?? null), 'emailBrand' => $brand])
            </td>
        </tr>
    </table>
@endif
