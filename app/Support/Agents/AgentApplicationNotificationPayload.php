<?php

namespace App\Support\Agents;

use App\Models\AgentApplication;
use App\Support\Url\PublicActionUrl;
use Illuminate\Support\Facades\Route;

/**
 * Normalized agent-application notification payload for operational emails.
 */
final class AgentApplicationNotificationPayload
{
    /**
     * @return array<string, mixed>
     */
    public static function fromApplication(AgentApplication $application, ?string $previousStatus = null): array
    {
        $application->loadMissing('reviewer');
        $reference = trim((string) ($application->application_reference ?? ''));
        $applicantName = trim($application->first_name.' '.$application->last_name);
        $status = trim((string) $application->status);
        $reviewUrl = self::reviewUrl($application);

        $nested = [
            'reference' => $reference !== '' ? $reference : null,
            'application_reference' => $reference !== '' ? $reference : null,
            'applicant_name' => $applicantName,
            'agency_name' => (string) $application->company_name,
            'email' => (string) $application->email,
            'phone' => (string) $application->mobile,
            'city' => (string) $application->city,
            'country' => (string) $application->country,
            'submitted_at' => $application->created_at?->format('d M Y, g:i A'),
            'status' => $status !== '' ? ucfirst(str_replace('_', ' ', $status)) : null,
            'application_status' => $status !== '' ? ucfirst(str_replace('_', ' ', $status)) : null,
            'previous_status' => $previousStatus !== null && $previousStatus !== ''
                ? ucfirst(str_replace('_', ' ', $previousStatus))
                : null,
            'review_url' => $reviewUrl,
        ];

        return array_filter([
            'application_reference' => $reference !== '' ? $reference : null,
            'applicant_name' => $applicantName,
            'company_name' => (string) $application->company_name,
            'applicant_agency_name' => (string) $application->company_name,
            'applicant_email' => (string) $application->email,
            'applicant_phone' => (string) $application->mobile,
            'city' => (string) $application->city,
            'country' => (string) $application->country,
            'submitted_at' => $application->created_at?->format('d M Y, g:i A'),
            'application_status' => $nested['application_status'],
            'previous_status' => $nested['previous_status'],
            'current_status' => $nested['application_status'],
            'review_url' => $reviewUrl,
            'application' => array_filter($nested, static fn (mixed $value): bool => $value !== null && $value !== ''),
            'agent_application' => array_filter($nested, static fn (mixed $value): bool => $value !== null && $value !== ''),
        ], static fn (mixed $value): bool => $value !== null && $value !== '');
    }

    private static function reviewUrl(AgentApplication $application): ?string
    {
        if (! Route::has('admin.agent-applications.show')) {
            return PublicActionUrl::route('admin.agent-applications.index', [], absolute: true);
        }

        return PublicActionUrl::route('admin.agent-applications.show', ['application' => $application->id], absolute: true);
    }
}
