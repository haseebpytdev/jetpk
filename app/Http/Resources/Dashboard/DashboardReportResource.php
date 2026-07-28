<?php

namespace App\Http\Resources\Dashboard;

use Illuminate\Support\Carbon;

final class DashboardReportResource
{
    /**
     * @param  array<string, mixed>  $reportPayload
     * @return array<string, mixed>
     */
    public static function fromBookingReport(string $section, array $reportPayload, string $currency = 'PKR'): array
    {
        $summary = is_array($reportPayload['summary'] ?? null) ? $reportPayload['summary'] : [];
        $financial = is_array($reportPayload['financialKpis'] ?? null) ? $reportPayload['financialKpis'] : [];

        return [
            'section' => $section,
            'currency' => $currency,
            'referenceTime' => Carbon::now()->toIso8601String(),
            'hasLiveData' => (bool) ($reportPayload['hasLiveData'] ?? false),
            'metrics' => self::metricsForSection($section, $summary, $financial, $currency),
            'tableRows' => self::tableRowsForSection($section, $reportPayload),
            'warnings' => self::warnings($currency),
            'supplierPerformance' => $reportPayload['supplierPerformance'] ?? [],
            'agentPerformance' => $reportPayload['agentPerformance'] ?? $reportPayload['topAgents'] ?? [],
            'monthlySales' => $reportPayload['monthlySales'] ?? [],
            'paymentBreakdown' => $reportPayload['paymentBreakdown'] ?? [],
            'topRoutes' => $reportPayload['topRoutes'] ?? [],
        ];
    }

    /**
     * @param  array<string, mixed>  $summary
     * @param  array<string, mixed>  $financial
     * @return list<array<string, mixed>>
     */
    protected static function metricsForSection(string $section, array $summary, array $financial, string $currency): array
    {
        $base = [
            [
                'key' => 'booking_count',
                'label' => 'Bookings',
                'value' => (int) ($summary['total_bookings'] ?? 0),
                'formattedValue' => number_format((int) ($summary['total_bookings'] ?? 0)),
                'currency' => null,
                'trend' => 'neutral',
            ],
            [
                'key' => 'gross_booking_value',
                'label' => 'Gross booking value',
                'value' => (int) round((float) ($financial['gross_sales'] ?? $summary['gross_sales'] ?? 0)),
                'formattedValue' => number_format((int) round((float) ($financial['gross_sales'] ?? $summary['gross_sales'] ?? 0))).' '.$currency,
                'currency' => $currency,
                'trend' => 'neutral',
            ],
        ];

        if ($section === 'payments') {
            $base[] = [
                'key' => 'outstanding_balance',
                'label' => 'Outstanding balance',
                'value' => (int) round((float) ($summary['outstanding_balance'] ?? 0)),
                'formattedValue' => number_format((int) round((float) ($summary['outstanding_balance'] ?? 0))).' '.$currency,
                'currency' => $currency,
                'trend' => 'warning',
            ];
        }

        if ($section === 'operations') {
            $base[] = [
                'key' => 'ticketing_pending',
                'label' => 'Ticketing pending',
                'value' => (int) ($summary['ticketing_pending'] ?? 0),
                'formattedValue' => number_format((int) ($summary['ticketing_pending'] ?? 0)),
                'currency' => null,
                'trend' => 'warning',
            ];
        }

        return $base;
    }

    /**
     * @param  array<string, mixed>  $reportPayload
     * @return list<array<string, mixed>>
     */
    protected static function tableRowsForSection(string $section, array $reportPayload): array
    {
        return match ($section) {
            'suppliers' => collect($reportPayload['supplierPerformance'] ?? [])->map(static fn ($row): array => [
                'id' => (string) ($row['provider'] ?? $row['supplier'] ?? 'supplier'),
                'label' => (string) ($row['provider_label'] ?? $row['provider'] ?? 'Supplier'),
                'bookings' => (int) ($row['bookings'] ?? $row['booking_count'] ?? 0),
                'sales' => (float) ($row['sales'] ?? 0),
            ])->values()->all(),
            'agents' => collect($reportPayload['topAgents'] ?? $reportPayload['agentPerformance'] ?? [])->map(static fn ($row): array => [
                'id' => (string) ($row['agent_code'] ?? $row['agent_id'] ?? 'agent'),
                'label' => (string) ($row['agent_name'] ?? 'Agent'),
                'bookings' => (int) ($row['bookings'] ?? 0),
                'sales' => (float) ($row['sales'] ?? 0),
            ])->values()->all(),
            'bookings' => collect($reportPayload['bookingPipelineRows'] ?? [])->take(50)->values()->all(),
            'payments' => collect($reportPayload['paymentRows'] ?? [])->take(50)->values()->all(),
            default => collect($reportPayload['topRoutes'] ?? [])->map(static fn ($row): array => [
                'id' => (string) ($row['route'] ?? 'route'),
                'label' => (string) ($row['route'] ?? 'Route'),
                'bookings' => (int) ($row['bookings'] ?? 0),
                'sales' => (float) ($row['sales'] ?? 0),
            ])->values()->all(),
        };
    }

    /**
     * @return list<array{code: string, message: string}>
     */
    protected static function warnings(string $currency): array
    {
        return [
            [
                'code' => 'currency_explicit',
                'message' => 'Monetary values are reported in '.$currency.' unless otherwise noted. Cross-currency totals are not merged.',
            ],
        ];
    }
}
