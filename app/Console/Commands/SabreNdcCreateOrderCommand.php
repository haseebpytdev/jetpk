<?php

namespace App\Console\Commands;

use App\Models\Booking;
use App\Models\SupplierConnection;
use App\Services\Suppliers\Sabre\Ndc\SabreNdcOrderCreateService;
use Illuminate\Console\Command;

class SabreNdcCreateOrderCommand extends Command
{
    protected $signature = 'sabre:ndc-create-order
                            {--booking= : Booking ID}
                            {--dry-run : Preview only (default)}
                            {--confirm= : CREATE-NDC-ORDER-FOR-BOOKING-{id}}';

    protected $description = 'Sabre NDC order create readiness (live disabled by default)';

    public function handle(SabreNdcOrderCreateService $service): int
    {
        $booking = $this->resolveBooking();
        if ($booking === null) {
            $this->error('Booking not found.');

            return self::FAILURE;
        }

        $dryRun = (bool) $this->option('dry-run') || $this->option('confirm') === null;
        $result = $service->preview($booking, $this->resolveConnection($booking), $dryRun);
        $result['confirm_provided'] = $this->option('confirm') === 'CREATE-NDC-ORDER-FOR-BOOKING-'.$booking->id;

        $this->line(json_encode($result, JSON_UNESCAPED_SLASHES));

        return self::SUCCESS;
    }

    private function resolveBooking(): ?Booking
    {
        $id = $this->option('booking');
        if ($id === null || ! is_numeric($id)) {
            return null;
        }

        return Booking::query()->find((int) $id);
    }

    private function resolveConnection(Booking $booking): ?SupplierConnection
    {
        $meta = is_array($booking->meta) ? $booking->meta : [];
        $connectionId = (int) ($meta['supplier_connection_id'] ?? 0);
        if ($connectionId > 0) {
            return SupplierConnection::query()->find($connectionId);
        }

        return SupplierConnection::query()->where('provider', 'sabre')->orderByDesc('is_active')->first();
    }
}
