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

        $currency = strtoupper((string) $request->query('currency', 'PKR'));
        if ($currency === 'ALL') {
            $currency = 'PKR';
        }

        $payload = $this->bookingReports->build($user, $request);
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
