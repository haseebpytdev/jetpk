<?php

namespace App\Console\Commands;

use App\Models\Booking;
use App\Support\Bookings\BookingAuthoritativeCurrencyResolver;
use Illuminate\Console\Command;
use Illuminate\Database\Eloquent\Builder;

/**
 * Read-only scan for booking.currency vs authoritative fare currency conflicts.
 */
class JetpkDash03HistoricalCurrencyConflictsCommand extends Command
{
    protected $signature = 'jetpk:dash03-historical-currency-conflicts';

    protected $description = 'Sanitized read-only count of booking/fare currency persistence conflicts';

    public function handle(): int
    {
        $query = Booking::query()
            ->with('fareBreakdown')
            ->whereHas('fareBreakdown', function (Builder $inner): void {
                $inner->whereNotNull('currency')->where('currency', '<>', '');
            });

        $total = (int) (clone $query)->count();
        $conflicts = 0;
        $supplierCounts = [];
        $paymentAffected = 0;
        $minCreated = null;
        $maxCreated = null;

        $query->orderBy('id')->chunkById(200, function ($bookings) use (&$conflicts, &$supplierCounts, &$paymentAffected, &$minCreated, &$maxCreated): void {
            foreach ($bookings as $booking) {
                $fareCurrency = BookingAuthoritativeCurrencyResolver::normalizeIsoCurrency($booking->fareBreakdown?->currency);
                $bookingCurrency = BookingAuthoritativeCurrencyResolver::normalizeIsoCurrency($booking->currency);
                if ($fareCurrency === '' || $bookingCurrency === '' || $fareCurrency === $bookingCurrency) {
                    continue;
                }

                $conflicts++;
                $supplier = (string) (($booking->meta['supplier_provider'] ?? null) ?: $booking->supplier ?: 'unknown');
                $supplierCounts[$supplier] = ($supplierCounts[$supplier] ?? 0) + 1;

                if ($booking->payments()->exists()) {
                    $paymentAffected++;
                }

                $created = $booking->created_at?->toDateString();
                if ($created !== null) {
                    $minCreated = $minCreated === null ? $created : min($minCreated, $created);
                    $maxCreated = $maxCreated === null ? $created : max($maxCreated, $created);
                }
            }
        });

        $this->line(json_encode([
            'ok' => true,
            'historicalBookingCurrencyConflicts' => $conflicts,
            'bookingsWithFare' => $total,
            'paymentRecordsOnConflictBookings' => $paymentAffected,
            'supplierClassCounts' => $supplierCounts,
            'dateRange' => [
                'min' => $minCreated,
                'max' => $maxCreated,
            ],
        ], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));

        return self::SUCCESS;
    }
}
