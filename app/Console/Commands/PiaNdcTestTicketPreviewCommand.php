<?php

namespace App\Console\Commands;

use App\Enums\SupplierProvider;
use App\Models\Booking;
use App\Models\SupplierConnection;
use App\Services\Suppliers\PiaNdc\PiaNdcTicketPreviewService;
use Illuminate\Console\Command;

class PiaNdcTestTicketPreviewCommand extends Command
{
    protected $signature = 'pia-ndc:test-ticket-preview
        {booking : OTA booking ID}
        {--connection= : Supplier connection ID}
        {--dry-run : Build request only — no supplier HTTP (default)}
        {--execute-preview : Call supplier DoTicketPreview}
        {--confirm= : Required phrase PREVIEW_PIA_NDC_TICKET with --execute-preview}
        {--fresh-retrieve : Run OrderRetrieve before live preview (default when executing)}
        {--no-fresh-retrieve : Skip fresh retrieve (dry-run/debug only)}';

    protected $description = 'PIA NDC DoTicketPreview — dry-run default; live requires --execute-preview + --confirm';

    public function handle(PiaNdcTicketPreviewService $ticketPreviewService): int
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

        $executePreview = (bool) $this->option('execute-preview');
        $dryRun = ! $executePreview || (bool) $this->option('dry-run');
        if ($executePreview && (bool) $this->option('dry-run')) {
            $this->error('Use either default dry-run or --execute-preview, not both with --dry-run.');

            return self::FAILURE;
        }

        $noFreshRetrieve = (bool) $this->option('no-fresh-retrieve');
        if ($executePreview && $noFreshRetrieve) {
            $this->error('--no-fresh-retrieve is only allowed for dry-run.');

            return self::FAILURE;
        }

        $confirmPhrase = trim((string) $this->option('confirm'));
        if ($executePreview && $confirmPhrase !== 'PREVIEW_PIA_NDC_TICKET') {
            $this->error('Execute preview requires --confirm="PREVIEW_PIA_NDC_TICKET".');

            return self::FAILURE;
        }

        $result = $ticketPreviewService->runPreview($booking, $connection, [
            'dry_run' => $dryRun,
            'persist' => $executePreview,
            'require_fresh_retrieve' => $executePreview && ! $noFreshRetrieve,
        ]);

        $this->printResult($result);

        return ($result['success'] ?? false) ? self::SUCCESS : self::FAILURE;
    }

    /**
     * @param  array<string, mixed>  $result
     */
    private function printResult(array $result): void
    {
        foreach ([
            'dry_run',
            'supplier_called',
            'operation',
            'booking_id',
            'order_id',
            'owner_code',
            'request_built',
            'order_status',
            'payment_time_limit',
            'amount',
            'currency',
            'error_code',
            'error_message',
            'diagnostic_path',
        ] as $key) {
            if (! array_key_exists($key, $result)) {
                if ($key === 'amount' && isset($result['preview']['amount'])) {
                    $this->line('amount='.$result['preview']['amount']);
                }
                if ($key === 'currency' && isset($result['preview']['currency'])) {
                    $this->line('currency='.$result['preview']['currency']);
                }

                continue;
            }
            $value = $result[$key];
            if (is_bool($value)) {
                $this->line($key.'='.($value ? 'true' : 'false'));
            } else {
                $this->line($key.'='.$value);
            }
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
