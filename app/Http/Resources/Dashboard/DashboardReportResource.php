<?php

namespace App\Http\Resources\Dashboard;

use App\Support\Dashboard\DashboardMoneyPresenter;
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
        $currencyCount = (int) ($summary['fare_currency_count'] ?? 1);

        return [
            'section' => $section,
            'currency' => $currency,
            'referenceTime' => Carbon::now()->toIso8601String(),
            'hasLiveData' => (bool) ($reportPayload['hasLiveData'] ?? false),
            'metrics' => self::metricsForSection($section, $summary, $financial, $currency, $currencyCount),
            'tableRows' => self::tableRowsForSection($section, $reportPayload),
            'warnings' => self::warnings($currency, $currencyCount),
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
    protected static function metricsForSection(string $section, array $summary, array $financial, string $currency, int $currencyCount = 1): array
    {
        $reportingCurrency = 'PKR';
        $grossLabel = 'Gross booking value';
        $grossAmount = (int) round((float) ($financial['gross_sales'] ?? $summary['gross_sales'] ?? 0));
        $excludedCount = (int) ($summary['pkr_snapshot_excluded_count'] ?? 0);
        $grossFormatted = ($grossAmount <= 0 && $excludedCount > 0)
            ? 'Amount unavailable'
            : DashboardMoneyPresenter::formatDisplayLabel($grossAmount, $reportingCurrency);

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
                'label' => $grossLabel,
                'value' => $grossAmount,
                'formattedValue' => $grossFormatted,
                'currency' => $reportingCurrency,
                'trend' => 'neutral',
            ],
        ];

        if ($section === 'payments') {
            $outstanding = (int) round((float) ($summary['outstanding_balance'] ?? 0));
            $base[] = [
                'key' => 'outstanding_balance',
                'label' => 'Outstanding balance',
                'value' => $outstanding,
                'formattedValue' => DashboardMoneyPresenter::formatDisplayLabel($outstanding, $reportingCurrency),
                'currency' => $reportingCurrency,
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
    protected static function warnings(string $currency, int $currencyCount = 1): array
    {
        return [
            [
                'code' => 'currency_explicit',
                'message' => 'Monetary values are normalized to PKR using authoritative booking-time snapshots.',
            ],
        ];
    }
}
