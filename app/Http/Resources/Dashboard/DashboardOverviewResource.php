<?php

namespace App\Http\Resources\Dashboard;

use App\Models\Booking;
use App\Models\BookingPayment;
use App\Models\User;
use App\Support\Bookings\BookingListPresenter;
use Illuminate\Support\Collection;

final class DashboardOverviewResource
{
    /**
     * @param  array<string, mixed>  $dashboard
     * @return array<string, mixed>
     */
    public static function fromAgencyDashboard(array $dashboard, User $user): array
    {
        $stats = is_array($dashboard['stats'] ?? null) ? $dashboard['stats'] : [];
        $needsAttention = is_array($dashboard['needsAttention'] ?? null) ? $dashboard['needsAttention'] : [];
        $recent = $dashboard['recentBookings'] ?? collect();

        return [
            'hasLiveData' => (bool) ($dashboard['hasLiveData'] ?? false),
            'referenceTime' => now()->toIso8601String(),
            'summaryStats' => self::summaryStats($stats),
            'operationalQueues' => self::operationalQueues($needsAttention),
            'recentBookings' => self::recentBookings($recent),
            'operationalCounts' => is_array($dashboard['commandSummary'] ?? null) ? $dashboard['commandSummary'] : [],
            'failedNotifications' => (int) (($dashboard['commandSummary']['failed_notifications'] ?? 0)),
            'supplierFailures' => self::supplierFailureCount($dashboard),
            'accountType' => $user->account_type->value,
        ];
    }

    /**
     * @param  array<string, mixed>  $stats
     * @return list<array<string, mixed>>
     */
    protected static function summaryStats(array $stats): array
    {
        return [
            ['key' => 'total_bookings', 'label' => 'Total Bookings', 'value' => (string) ((int) ($stats['total_bookings'] ?? 0)), 'delta' => '', 'tone' => 'up'],
            ['key' => 'pending_bookings', 'label' => 'Pending Bookings', 'value' => (string) ((int) ($stats['pending_bookings'] ?? 0)), 'delta' => '', 'tone' => 'warn'],
            ['key' => 'unpaid_partial', 'label' => 'Unpaid / Partial', 'value' => (string) ((int) ($stats['unpaid_partial_bookings'] ?? 0)), 'delta' => '', 'tone' => 'warn'],
            ['key' => 'ticketed_bookings', 'label' => 'Ticketed', 'value' => (string) ((int) ($stats['ticketed_bookings'] ?? 0)), 'delta' => '', 'tone' => 'up'],
            ['key' => 'pending_refunds', 'label' => 'Pending Refunds', 'value' => (string) ((int) ($stats['pending_refund_count'] ?? 0)), 'delta' => '', 'tone' => 'warn'],
            ['key' => 'cancellations', 'label' => 'Cancellations', 'value' => (string) ((int) ($stats['cancellation_count'] ?? 0)), 'delta' => '', 'tone' => 'down'],
        ];
    }

    /**
     * @param  array<int, array<string, mixed>>  $needsAttention
     * @return list<array<string, mixed>>
     */
    protected static function operationalQueues(array $needsAttention): array
    {
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
        }, $needsAttention));
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

                    return [
                        'id' => DashboardBookingResource::publicId($booking),
                        'pnr' => (string) ($row['pnr'] ?? ''),
                        'customer' => (string) ($row['customer_name'] ?? 'Guest'),
                        'phone' => DashboardBookingResource::fromModel($booking)['customerPhone'],
                        'route' => (string) ($row['route'] ?? ''),
                        'date' => (string) ($row['travel_date'] ?? ''),
                        'status' => (string) ($row['status_display'] ?? ''),
                        'amount' => number_format((int) ($row['total_fare'] ?? 0)).' PKR',
                        'payment' => (string) ($row['payment_status_display'] ?? ''),
                    ];
                }

                return is_array($booking) ? $booking : [];
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
