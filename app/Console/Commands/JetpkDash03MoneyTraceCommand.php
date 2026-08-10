<?php

namespace App\Console\Commands;

use App\Models\Booking;
use App\Support\Dashboard\DashboardMoneyPresenter;
use Illuminate\Console\Command;
use Illuminate\Database\Eloquent\Builder;

/**
 * Read-only JP-DASH-03 money provenance trace (sanitized output).
 */
class JetpkDash03MoneyTraceCommand extends Command
{
    protected $signature = 'jetpk:dash03-money-trace
        {--limit=8 : Maximum bookings to sample}
        {--supplier= : Filter supplier class (sabre, pia_ndc, pia)}
        {--agent= : Filter agent-owned bookings (yes|no)}
        {--customer= : Filter direct customer bookings (yes|no)}
        {--matrix : Sample representative Sabre/PIA/agent/customer rows}';

    protected $description = 'Sanitized read-only booking money/currency trace for JP-DASH-03 reconciliation';

    public function handle(): int
    {
        if ($this->option('matrix')) {
            return $this->outputMatrix();
        }

        $limit = max(1, min(20, (int) $this->option('limit')));
        $bookings = $this->baseQuery()
            ->orderByDesc('id')
            ->limit($limit)
            ->get();

        $this->outputRows($bookings->map(fn (Booking $booking) => $this->traceRow($booking))->all());

        return self::SUCCESS;
    }

    private function outputMatrix(): int
    {
        $matrix = [
            'sabre' => $this->sampleForSupplier('sabre'),
            'pia_ndc' => $this->sampleForSupplier('pia_ndc') ?? $this->sampleForSupplier('pia'),
            'agent_owned' => $this->sampleForChannel(agent: true, customer: false),
            'customer_owned' => $this->sampleForChannel(agent: false, customer: true),
        ];

        $rows = [];
        foreach ($matrix as $label => $booking) {
            if ($booking === null) {
                $rows[] = [
                    'matrixLabel' => $label,
                    'status' => 'NO_REPRESENTATIVE_PRODUCTION_RECORD',
                ];
                continue;
            }

            $row = $this->traceRow($booking);
            $row['matrixLabel'] = $label;
            $rows[] = $row;
        }

        $this->outputRows($rows, true);

        return self::SUCCESS;
    }

    private function sampleForSupplier(string $supplier): ?Booking
    {
        return $this->baseQuery()
            ->where(function (Builder $query) use ($supplier): void {
                $query->where('supplier', $supplier)
                    ->orWhere('meta->supplier_provider', $supplier);
            })
            ->orderByDesc('id')
            ->first();
    }

    private function sampleForChannel(bool $agent, bool $customer): ?Booking
    {
        $query = $this->baseQuery()->orderByDesc('id');

        if ($agent) {
            $query->whereNotNull('agent_id');
        }

        if ($customer) {
            $query->whereNotNull('customer_id')->whereNull('agent_id');
        }

        return $query->first();
    }

    /**
     * @return Builder<Booking>
     */
    private function baseQuery(): Builder
    {
        $query = Booking::query()->with(['fareBreakdown', 'verifiedPayments']);

        $supplier = strtolower(trim((string) $this->option('supplier')));
        if ($supplier !== '') {
            $query->where(function (Builder $inner) use ($supplier): void {
                $inner->where('supplier', $supplier)
                    ->orWhere('meta->supplier_provider', $supplier);
            });
        }

        $agent = strtolower(trim((string) $this->option('agent')));
        if ($agent === 'yes') {
            $query->whereNotNull('agent_id');
        } elseif ($agent === 'no') {
            $query->whereNull('agent_id');
        }

        $customer = strtolower(trim((string) $this->option('customer')));
        if ($customer === 'yes') {
            $query->whereNotNull('customer_id');
        } elseif ($customer === 'no') {
            $query->whereNull('customer_id');
        }

        return $query;
    }

    /**
     * @return array<string, mixed>
     */
    private function traceRow(Booking $booking): array
    {
        $fare = $booking->fareBreakdown;
        $payment = $booking->verifiedPayments->first();
        $resolved = DashboardMoneyPresenter::resolveBookingCurrencyWithSource($booking);
        $totalMoney = DashboardMoneyPresenter::presentBookingTotal(
            $booking,
            (int) round((float) ($fare?->total ?? 0)),
        );

        $storedBookingCurrency = DashboardMoneyPresenter::normalizeIsoCurrency($booking->currency);
        $storedFareCurrency = DashboardMoneyPresenter::normalizeIsoCurrency($fare?->currency);
        $currencyPersistenceDefect = $storedBookingCurrency !== ''
            && $storedFareCurrency !== ''
            && $storedBookingCurrency !== $storedFareCurrency;

        return [
            'bookingReference' => (string) ($booking->booking_reference ?? $booking->id),
            'supplierClass' => (string) (($booking->meta['supplier_provider'] ?? null) ?: $booking->supplier ?? ''),
            'channel' => $booking->agent_id ? 'agent' : ($booking->customer_id ? 'customer' : 'guest'),
            'storedBookingCurrency' => (string) ($booking->currency ?? ''),
            'storedFareCurrency' => (string) ($fare?->currency ?? ''),
            'storedFareTotal' => (int) round((float) ($fare?->total ?? 0)),
            'resolvedCurrency' => $resolved['currency'],
            'resolvedSource' => $resolved['source'],
            'dashboardCurrencyStatus' => $totalMoney['currencyStatus'],
            'dashboardDisplayLabel' => $totalMoney['displayLabel'],
            'dashboardNeedsReview' => $totalMoney['needsReview'],
            'paymentAmount' => $payment ? (int) round((float) $payment->amount) : null,
            'paymentCurrency' => $payment ? (string) ($payment->currency ?? '') : null,
            'bookingCurrencyPersistenceDefect' => $currencyPersistenceDefect ? 'yes' : 'no',
            'metaCurrency' => (string) (data_get($booking->meta, 'currency') ?? ''),
            'metaOfferCurrency' => (string) (data_get($booking->meta, 'offer_currency') ?? ''),
        ];
    }

    /**
     * @param  list<array<string, mixed>>  $rows
     */
    private function outputRows(array $rows, bool $matrix = false): void
    {
        $this->line(json_encode([
            'ok' => true,
            'matrix' => $matrix,
            'count' => count($rows),
            'rows' => $rows,
        ], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));
    }
}
