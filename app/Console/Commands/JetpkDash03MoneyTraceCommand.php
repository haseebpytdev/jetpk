<?php

namespace App\Console\Commands;

use App\Models\Booking;
use App\Support\Dashboard\DashboardMoneyPresenter;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

/**
 * Read-only JP-DASH-03 money provenance trace (sanitized output).
 */
class JetpkDash03MoneyTraceCommand extends Command
{
    protected $signature = 'jetpk:dash03-money-trace {--limit=8 : Maximum bookings to sample}';

    protected $description = 'Sanitized read-only booking money/currency trace for JP-DASH-03 reconciliation';

    public function handle(): int
    {
        $limit = max(1, min(20, (int) $this->option('limit')));
        $bookings = Booking::query()
            ->with(['fareBreakdown', 'verifiedPayments'])
            ->orderByDesc('id')
            ->limit($limit)
            ->get();

        $rows = [];
        foreach ($bookings as $booking) {
            $fare = $booking->fareBreakdown;
            $payment = $booking->verifiedPayments->first();
            $resolved = DashboardMoneyPresenter::resolveBookingCurrencyWithSource($booking);
            $totalMoney = DashboardMoneyPresenter::presentBookingTotal(
                $booking,
                (int) round((float) ($fare?->total ?? 0)),
            );

            $rows[] = [
                'bookingReference' => (string) ($booking->booking_reference ?? $booking->id),
                'supplierClass' => (string) (($booking->meta['supplier_provider'] ?? null) ?: $booking->supplier ?? ''),
                'storedBookingCurrency' => (string) ($booking->currency ?? ''),
                'storedFareCurrency' => (string) ($fare?->currency ?? ''),
                'storedFareTotal' => (int) round((float) ($fare?->total ?? 0)),
                'resolvedCurrency' => $resolved['currency'],
                'resolvedSource' => $resolved['source'],
                'dashboardCurrencyStatus' => $totalMoney['currencyStatus'],
                'dashboardDisplayLabel' => $totalMoney['displayLabel'],
                'paymentAmount' => $payment ? (int) round((float) $payment->amount) : null,
                'paymentCurrency' => $payment ? (string) ($payment->currency ?? '') : null,
                'metaCurrency' => (string) (data_get($booking->meta, 'currency') ?? ''),
                'metaOfferCurrency' => (string) (data_get($booking->meta, 'offer_currency') ?? ''),
            ];
        }

        $this->line(json_encode([
            'ok' => true,
            'count' => count($rows),
            'rows' => $rows,
        ], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));

        return self::SUCCESS;
    }
}
