<?php

namespace App\Support\AgentPortal;

/**
 * Agency-scoped agent reports JSON — excludes platform/internal margin fields.
 */
class AgentPortalReportsPresenter
{
    /**
     * @param  array<string, mixed>  $report
     * @return array<string, mixed>
     */
    public function present(array $report, string $activeTab): array
    {
        $summary = is_array($report['summary'] ?? null) ? $report['summary'] : [];

        return [
            'ok' => true,
            'active_tab' => $activeTab,
            'filters' => $report['filters'] ?? [],
            'has_live_data' => (bool) ($report['hasLiveData'] ?? false),
            'summary' => [
                'gross_sales' => (float) ($summary['gross_sales'] ?? 0),
                'total_bookings' => (int) ($summary['total_bookings'] ?? 0),
                'ticketed_bookings' => (int) ($summary['ticketed_bookings'] ?? 0),
                'pending_bookings' => (int) ($summary['pending_bookings'] ?? 0),
                'cancelled_bookings' => (int) ($summary['cancelled_bookings'] ?? 0),
                'unpaid_partial_bookings' => (int) ($summary['unpaid_partial_bookings'] ?? 0),
                'ticketing_pending' => (int) ($summary['ticketing_pending'] ?? 0),
                'refund_paid_amount' => (float) ($summary['refund_paid_amount'] ?? 0),
                'pending_refund_count' => (int) ($summary['pending_refund_count'] ?? 0),
            ],
            'monthly_sales' => collect($report['monthlySales'] ?? [])
                ->map(fn ($row): array => [
                    'month' => (string) ($row->month ?? $row['month'] ?? ''),
                    'bookings' => (int) ($row->bookings ?? $row['bookings'] ?? 0),
                    'gross_sales' => (float) ($row->gross_sales ?? $row['gross_sales'] ?? 0),
                ])
                ->values()
                ->all(),
            'export_url' => '/laravel/agent/finance/statement/export',
            'allowed_tabs' => ['overview', 'sales', 'payments', 'bookings', 'routes', 'refunds'],
        ];
    }
}
