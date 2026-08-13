<?php

namespace App\Services\Dashboard\Api;

use App\Http\Resources\Dashboard\DashboardReportResource;
use App\Models\User;
use App\Services\Reports\BookingReportService;
use App\Support\Staff\StaffPermission;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Http\Request;

class DashboardReportsReadService
{
    public function __construct(
        protected BookingReportService $bookingReports,
    ) {}

    /**
     * @return array<string, mixed>
     */
    public function section(User $user, Request $request, string $section): array
    {
        $this->assertReportsPermission($user);

        $payload = $this->bookingReports->build($user, $request);
        $summary = is_array($payload['summary'] ?? null) ? $payload['summary'] : [];
        $fareCount = (int) ($summary['fare_currency_count'] ?? 1);
        $fareIso = strtoupper(trim((string) ($summary['fare_currency_iso'] ?? '')));
        $requested = strtoupper((string) $request->query('currency', ''));

        $currency = $fareCount > 1
            ? 'PKR'
            : ($fareIso !== '' ? $fareIso : ($requested !== '' && $requested !== 'ALL' ? $requested : 'PKR'));

        if ($currency === 'ALL') {
            $currency = $fareIso !== '' ? $fareIso : 'PKR';
        }
        $mappedSection = match ($section) {
            'summary', 'overview', 'sales' => 'summary',
            'bookings' => 'bookings',
            'payments' => 'payments',
            'suppliers' => 'suppliers',
            'agents' => 'agents',
            'operations' => 'operations',
            default => 'summary',
        };

        return DashboardReportResource::fromBookingReport($mappedSection, $payload, $currency);
    }

    protected function assertReportsPermission(User $user): void
    {
        if ($user->isPlatformAdmin()) {
            return;
        }

        if ($user->isStaff() && $user->hasStaffPermission(StaffPermission::ReportsView)) {
            return;
        }

        if ($user->isAgentPortalUser() && $user->hasAgentPermission(\App\Support\Agents\AgentPermission::ReportsView)) {
            return;
        }

        throw new AuthorizationException('You do not have permission to view reports.');
    }
}
