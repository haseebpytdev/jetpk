<?php

namespace App\Console\Commands;

use App\Enums\SupplierProvider;
use App\Models\Booking;
use App\Models\SupplierConnection;
use App\Services\Suppliers\PiaNdc\Exceptions\PiaNdcProviderException;
use App\Services\Suppliers\PiaNdc\PiaNdcReissueService;
use Illuminate\Console\Command;

class PiaNdcReissuePreviewCommand extends Command
{
    protected $signature = 'pia-ndc:reissue-preview
        {booking : OTA booking ID}
        {--connection= : Supplier connection ID}';

    protected $description = 'Run PIA NDC DoReissuePreview for an OTA booking';

    public function handle(PiaNdcReissueService $reissueService): int
    {
        $booking = Booking::query()->find((int) $this->argument('booking'));
        if ($booking === null) {
            $this->error('Booking not found.');

            return self::FAILURE;
        }

        $connection = $this->resolveConnection($booking);
        if ($connection === null) {
            $this->error('PIA NDC connection not found.');

            return self::FAILURE;
        }

        try {
            $preview = $reissueService->preview($booking, $connection);
            $this->line(json_encode($preview, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));

            return self::SUCCESS;
        } catch (PiaNdcProviderException $exception) {
            $this->error('reissue_preview_failed='.$exception->safeMessage);

            return self::FAILURE;
        }
    }

    protected function resolveConnection(Booking $booking): ?SupplierConnection
    {
        $id = $this->option('connection');
        if ($id) {
            return SupplierConnection::query()->where('id', (int) $id)->where('provider', SupplierProvider::PiaNdc)->first();
        }

        $meta = is_array($booking->meta) ? $booking->meta : [];

        return SupplierConnection::query()->find((int) ($meta['supplier_connection_id'] ?? 0))
            ?? SupplierConnection::query()->where('provider', SupplierProvider::PiaNdc)->orderByDesc('is_active')->first();
    }
}
