<?php

namespace App\Http\Resources\Dashboard;

use App\Models\Booking;
use App\Models\User;
use App\Support\Bookings\BookingListPresenter;
use App\Support\Dashboard\DashboardMoneyPresenter;
use App\Support\Dashboard\DashboardPermissionResolver;
use App\Support\Staff\StaffPermission;
use Illuminate\Support\Collection;

final class DashboardOverviewResource
{
    /**
     * @param  array<string, mixed>  $dashboard
     * @param  list<array<string, mixed>>  $supportAlerts
     * @param  list<array<string, string>>  $supplierStatus
     * @return array<string, mixed>
     */
    public static function fromAgencyDashboard(
        array $dashboard,
        User $user,
        string $portal = 'admin',
        array $supportAlerts = [],
        array $supplierStatus = [],
    ): array {
        $stats = is_array($dashboard['stats'] ?? null) ? $dashboard['stats'] : [];
        $needsAttention = is_array($dashboard['needsAttention'] ?? null) ? $dashboard['needsAttention'] : [];
        $recent = $dashboard['recentBookings'] ?? collect();
        $operationalKpis = is_array($dashboard['operationalKpis'] ?? null) ? $dashboard['operationalKpis'] : [];
        $commandSummary = is_array($dashboard['commandSummary'] ?? null) ? $dashboard['commandSummary'] : [];

        return [
            'hasLiveData' => (bool) ($dashboard['hasLiveData'] ?? false),
            'referenceTime' => now()->toIso8601String(),
            'summaryStats' => self::summaryStats($stats, $commandSummary),
            'operationalQueues' => self::operationalQueues($needsAttention, $user),
            'bookingPipeline' => self::bookingPipeline($stats, $operationalKpis),
            'recentBookings' => self::recentBookings($recent),
            'paymentOperations' => self::paymentOperations($commandSummary, $portal),
            'supportOperations' => self::supportOperations($supportAlerts),
            'supplierStatus' => $supplierStatus,
            'systemHealth' => self::systemHealth((bool) ($dashboard['hasLiveData'] ?? false)),
            'operationalCounts' => $commandSummary,
            'failedNotifications' => (int) ($commandSummary['failed_notifications'] ?? 0),
            'failedNotificationsQaHistorical' => (int) ($commandSummary['failed_notifications_qa'] ?? 0),
            'supplierFailures' => self::supplierFailureCount($dashboard),
            'accountType' => $user->account_type->value,
        ];
    }

    /**
     * @param  array<string, mixed>  $stats
     * @param  array<string, mixed>  $commandSummary
     * @return list<array<string, mixed>>
     */
    protected static function summaryStats(array $stats, array $commandSummary): array
    {
        $cards = [];

        if (isset($stats['total_bookings'])) {
            $cards[] = ['key' => 'total_bookings', 'label' => 'Bookings', 'value' => (string) ((int) $stats['total_bookings']), 'delta' => '', 'tone' => 'up'];
        }
        if (isset($commandSummary['needs_action'])) {
            $cards[] = ['key' => 'needs_action', 'label' => 'Needs action', 'value' => (string) ((int) $commandSummary['needs_action']), 'delta' => '', 'tone' => 'warn'];
        }
        if (isset($stats['pending_bookings']) && (int) $stats['pending_bookings'] > 0) {
            $cards[] = ['key' => 'pending_bookings', 'label' => 'Pending', 'value' => (string) ((int) $stats['pending_bookings']), 'delta' => '', 'tone' => 'warn'];
        }
        if (isset($stats['ticketed_bookings'])) {
            $cards[] = ['key' => 'ticketed_bookings', 'label' => 'Ticketed', 'value' => (string) ((int) $stats['ticketed_bookings']), 'delta' => '', 'tone' => 'up'];
        }
        if (isset($stats['unpaid_partial_bookings']) && (int) $stats['unpaid_partial_bookings'] > 0) {
            $cards[] = ['key' => 'unpaid_partial', 'label' => 'Unpaid / partial', 'value' => (string) ((int) $stats['unpaid_partial_bookings']), 'delta' => '', 'tone' => 'warn'];
        }
        if (isset($commandSummary['gross_sales'])) {
            $excluded = (int) ($commandSummary['gross_sales_excluded_count'] ?? 0);
            $grossAmount = (float) $commandSummary['gross_sales'];
            $qaFailures = (int) ($commandSummary['failed_notifications_qa'] ?? 0);
            $cards[] = [
                'key' => 'gross_sales',
                'label' => 'Gross booking value',
                'value' => DashboardMoneyPresenter::formatDisplayLabel($grossAmount, 'PKR'),
                'delta' => $excluded > 0
                    ? $excluded.' legacy Non-PKR booking'.($excluded === 1 ? '' : 's').' excluded from this KPI (original currency kept on rows; no current-FX reconstruction)'
                    : 'Authoritative booking-time PKR snapshots only',
                'tone' => 'up',
            ];
            unset($qaFailures);
        }

        return $cards;
    }

    /**
     * @param  array<string, mixed>  $stats
     * @param  list<array<string, mixed>>  $operationalKpis
     * @return list<array<string, mixed>>
     */
    protected static function bookingPipeline(array $stats, array $operationalKpis): array
    {
        $pipeline = [];

        if (isset($stats['total_bookings'])) {
            $pipeline[] = [
                'key' => 'bookings',
                'label' => 'Bookings',
                'count' => (int) $stats['total_bookings'],
                'laravelRoute' => 'admin.bookings',
            ];
        }

        foreach ($operationalKpis as $kpi) {
            $key = (string) ($kpi['key'] ?? '');
            if (! in_array($key, ['supplier_pnr_pending', 'payment_review', 'ticketing_pending'], true)) {
                continue;
            }

            $pipeline[] = [
                'key' => $key,
                'label' => (string) ($kpi['label'] ?? $key),
                'count' => (int) ($kpi['count'] ?? 0),
                'laravelRoute' => (string) ($kpi['route'] ?? 'admin.bookings'),
                'queue' => isset($kpi['route_params']['queue']) ? (string) $kpi['route_params']['queue'] : null,
            ];
        }

        if (isset($stats['ticketed_bookings'])) {
            $pipeline[] = [
                'key' => 'ticketed',
                'label' => 'Ticketed',
                'count' => (int) $stats['ticketed_bookings'],
                'laravelRoute' => 'admin.bookings',
                'queue' => 'ticketed',
            ];
        }

        return $pipeline;
    }

    /**
     * @param  array<string, mixed>  $commandSummary
     * @return list<array<string, mixed>>
     */
    protected static function paymentOperations(array $commandSummary, string $portal): array
    {
        $routePrefix = $portal === 'staff' ? 'staff' : 'admin';

        return array_values(array_filter([
            isset($commandSummary['payment_review']) ? [
                'key' => 'payment_review',
                'label' => 'Payment review',
                'count' => (int) $commandSummary['payment_review'],
                'laravelRoute' => $routePrefix.'.bookings',
                'queue' => 'payment_review',
            ] : null,
            isset($commandSummary['pending_deposits']) && (int) $commandSummary['pending_deposits'] > 0 ? [
                'key' => 'pending_deposits',
                'label' => 'Pending deposits',
                'count' => (int) $commandSummary['pending_deposits'],
                'laravelRoute' => 'admin.agent-deposits.index',
            ] : null,
        ]));
    }

    /**
     * @param  list<array<string, mixed>>  $supportAlerts
     * @return list<array<string, mixed>>
     */
    protected static function supportOperations(array $supportAlerts): array
    {
        return array_values(array_map(static function (array $alert): array {
            return [
                'key' => (string) ($alert['key'] ?? ''),
                'label' => (string) ($alert['label'] ?? ''),
                'count' => (int) ($alert['count'] ?? 0),
                'helper' => (string) ($alert['helper'] ?? ''),
                'laravelRoute' => (string) ($alert['route'] ?? 'admin.support.tickets.index'),
                'queue' => isset($alert['route_params']['queue']) ? (string) $alert['route_params']['queue'] : null,
            ];
        }, $supportAlerts));
    }

    /**
     * @return list<array<string, string>>
     */
    protected static function systemHealth(bool $hasLiveData): array
    {
        return [
            ['name' => 'Dashboard Next', 'status' => 'operational'],
            ['name' => 'Laravel API', 'status' => 'operational'],
            ['name' => 'Booking data', 'status' => $hasLiveData ? 'operational' : 'degraded'],
        ];
    }

    /**
     * @param  array<int, array<string, mixed>>  $needsAttention
     * @return list<array<string, mixed>>
     */
    protected static function operationalQueues(array $needsAttention, User $user): array
    {
        $filtered = array_values(array_filter(
            $needsAttention,
            static fn (array $item): bool => self::canViewOperationalQueue($user, (string) ($item['key'] ?? '')),
        ));

        return array_values(array_map(static function (array $item): array {
            return [
                'key' => (string) ($item['key'] ?? ''),
                'label' => (string) ($item['label'] ?? ''),
                'count' => (int) ($item['count'] ?? 0),
                'helper' => (string) ($item['helper'] ?? ''),
                'laravelRoute' => (string) ($item['route'] ?? 'admin.bookings'),
                'queue' => isset($item['route_params']['queue']) ? (string) $item['route_params']['queue'] : null,
                'tone' => self::toneForKey((string) ($item['key'] ?? '')),
                'cta' => 'Review',
            ];
        }, $filtered));
    }

    protected static function canViewOperationalQueue(User $user, string $key): bool
    {
        if ($user->isPlatformAdmin()) {
            return true;
        }

        if (! $user->isStaff()) {
            return false;
        }

        return match ($key) {
            'pending_deposits' => false,
            'payment_review' => $user->hasStaffPermission(StaffPermission::PaymentsVerify)
                || $user->hasStaffPermission(StaffPermission::PaymentsRecord),
            'supplier_pnr_pending' => false,
            'refunds_pending' => $user->hasStaffPermission(StaffPermission::RefundsApprove),
            'cancellations_pending' => $user->hasStaffPermission(StaffPermission::CancellationsApprove),
            'ticketing_pending' => $user->hasStaffPermission(StaffPermission::TicketingIssue),
            'failed_notifications' => DashboardPermissionResolver::canViewSettings($user),
            default => $user->hasStaffPermission(StaffPermission::BookingsView),
        };
    }

    /**
     * @param  Collection<int, Booking>|array<int, mixed>  $recent
     * @return list<array<string, mixed>>
     */
    protected static function recentBookings(Collection|array $recent): array
    {
        $collection = $recent instanceof Collection ? $recent : collect($recent);

        return $collection
            ->take(8)
            ->map(static function (mixed $booking): array {
                if ($booking instanceof Booking) {
                    $row = BookingListPresenter::toListRow($booking);

                    $totalMoney = DashboardMoneyPresenter::presentBookingTotal($booking, (int) ($row['total_fare'] ?? 0));

                    return [
                        'id' => DashboardBookingResource::publicId($booking),
                        'pnr' => (string) ($row['pnr'] ?? ''),
                        'customer' => (string) ($row['customer_name'] ?? 'Guest'),
                        'phone' => DashboardBookingResource::fromModel($booking)['customerPhone'],
                        'route' => (string) ($row['route'] ?? ''),
                        'date' => (string) ($row['travel_date'] ?? ''),
                        'status' => (string) ($row['status_display'] ?? ''),
                        'amount' => $totalMoney['displayLabel'],
                        'currency' => $totalMoney['currency'],
                        'currencyStatus' => $totalMoney['currencyStatus'],
                        'payment' => (string) ($row['payment_status_display'] ?? ''),
                    ];
                }

                if (is_array($booking)) {
                    $amountLabel = trim((string) ($booking['amount_display'] ?? ''));
                    if ($amountLabel === '') {
                        $amountLabel = DashboardMoneyPresenter::formatAmountLabel(
                            (float) ($booking['amount_pkr'] ?? 0),
                            DashboardMoneyPresenter::normalizeIsoCurrency($booking['currency'] ?? ''),
                        );
                    }

                    return [
                        'id' => (string) ($booking['id'] ?? ''),
                        'pnr' => (string) ($booking['ref'] ?? $booking['pnr'] ?? ''),
                        'customer' => (string) ($booking['customer'] ?? 'Guest'),
                        'phone' => '—',
                        'route' => (string) ($booking['route'] ?? ''),
                        'date' => (string) ($booking['created_at'] ?? ''),
                        'status' => (string) ($booking['status'] ?? ''),
                        'amount' => $amountLabel,
                        'currency' => DashboardMoneyPresenter::normalizeIsoCurrency($booking['currency'] ?? '') ?: null,
                        'payment' => (string) ($booking['payment_status'] ?? ''),
                    ];
                }

                return [];
            })
            ->filter(static fn (array $row): bool => $row !== [])
            ->values()
            ->all();
    }

    /**
     * @param  array<string, mixed>  $dashboard
     */
    protected static function supplierFailureCount(array $dashboard): int
    {
        $failures = $dashboard['recentSupplierFailures'] ?? collect();
        if ($failures instanceof Collection) {
            return $failures->count();
        }

        return is_countable($failures) ? count($failures) : 0;
    }

    protected static function toneForKey(string $key): string
    {
        return match ($key) {
            'payment_review', 'pending_deposits' => 'amber',
            'supplier_pnr_pending', 'manual_review' => 'blue',
            'ticketing_pending' => 'emerald',
            'cancellations_pending', 'refunds_pending', 'failed_notifications' => 'red',
            default => 'violet',
        };
    }
}
