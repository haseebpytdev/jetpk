<?php

namespace App\Console\Commands;

use App\Enums\SupplierProvider;
use App\Models\Booking;
use App\Models\SupplierConnection;
use App\Services\Suppliers\PiaNdc\Exceptions\PiaNdcCancellationException;
use App\Services\Suppliers\PiaNdc\PiaNdcVoidTicketService;
use Illuminate\Console\Command;

class PiaNdcVoidTicketCommand extends Command
{
    protected $signature = 'pia-ndc:void-ticket
        {booking : OTA booking ID}
        {--connection= : Supplier connection ID}
        {--dry-run : Build request only — no supplier HTTP (default)}
        {--execute-void : Call supplier DoVoidTicket}
        {--confirm= : Required phrase VOID_PIA_NDC_TICKET with --execute-void}
        {--fresh-retrieve : Run OrderRetrieve before live void (default when executing)}
        {--no-fresh-retrieve : Skip fresh retrieve (dry-run/debug only)}';

    protected $description = 'PIA NDC DoVoidTicket — dry-run default; live requires --execute-void + --confirm';

    public function handle(PiaNdcVoidTicketService $voidTicketService): int
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

        $executeVoid = (bool) $this->option('execute-void');
        $dryRun = ! $executeVoid || (bool) $this->option('dry-run');
        if ($executeVoid && (bool) $this->option('dry-run')) {
            $this->error('Use either default dry-run or --execute-void, not both with --dry-run.');

            return self::FAILURE;
        }

        $noFreshRetrieve = (bool) $this->option('no-fresh-retrieve');
        if ($executeVoid && $noFreshRetrieve) {
            $this->error('--no-fresh-retrieve is only allowed for dry-run.');

            return self::FAILURE;
        }

        $confirmPhrase = trim((string) $this->option('confirm'));
        if ($executeVoid && $confirmPhrase !== 'VOID_PIA_NDC_TICKET') {
            $this->error('Execute void requires --confirm="VOID_PIA_NDC_TICKET".');

            return self::FAILURE;
        }

        try {
            $result = $voidTicketService->runVoid($booking, $connection, [
                'dry_run' => $dryRun,
                'persist' => $executeVoid,
                'require_fresh_retrieve' => $executeVoid && ! $noFreshRetrieve,
            ]);
        } catch (PiaNdcCancellationException $exception) {
            $this->error('void_ticket_failed='.$exception->safeMessage);

            return self::FAILURE;
        }

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
            'real_ticket_numbers_present',
            'request_built',
            'void_status',
            'order_status',
            'error_code',
            'error_message',
            'diagnostic_path',
        ] as $key) {
            if (! array_key_exists($key, $result)) {
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
