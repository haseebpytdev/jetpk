<?php

namespace App\Console\Commands;

use App\Enums\AccountType;
use App\Enums\SupplierProvider;
use App\Models\Booking;
use App\Models\SupplierConnection;
use App\Models\User;
use App\Services\Suppliers\PiaNdc\Exceptions\PiaNdcTicketingException;
use App\Services\Suppliers\PiaNdc\PiaNdcTicketingService;
use Illuminate\Console\Command;

class PiaNdcTestTicketingCommand extends Command
{
    protected $signature = 'pia-ndc:test-ticketing
        {booking : OTA booking ID}
        {--connection= : Supplier connection ID}
        {--dry-run : Build request only — no supplier HTTP (default)}
        {--execute-ticketing : Call supplier DoOrderChange ticketing}
        {--confirm= : Required phrase ISSUE_PIA_NDC_TICKET with --execute-ticketing}
        {--actor-id= : Operator user ID (defaults to first platform admin)}
        {--fresh-retrieve : Run OrderRetrieve before live ticketing (default when executing)}
        {--no-fresh-retrieve : Skip fresh retrieve (dry-run/debug only)}
        {--force-preview : Re-run ticket preview before ticketing}';

    protected $description = 'PIA NDC DoOrderChange ticketing — dry-run default; live requires --execute-ticketing + --confirm';

    public function handle(PiaNdcTicketingService $ticketingService): int
    {
        $booking = Booking::query()->with('latestSupplierBooking.supplierConnection')->find((int) $this->argument('booking'));
        if ($booking === null) {
            $this->error('Booking not found.');

            return self::FAILURE;
        }

        $connection = $this->resolveConnection($booking);
        if ($connection === null) {
            $this->error('PIA NDC connection not found.');

            return self::FAILURE;
        }

        $executeTicketing = (bool) $this->option('execute-ticketing');
        $dryRun = ! $executeTicketing || (bool) $this->option('dry-run');
        if ($executeTicketing && (bool) $this->option('dry-run')) {
            $this->error('Use either default dry-run or --execute-ticketing, not both with --dry-run.');

            return self::FAILURE;
        }

        $noFreshRetrieve = (bool) $this->option('no-fresh-retrieve');
        if ($executeTicketing && $noFreshRetrieve) {
            $this->error('--no-fresh-retrieve is only allowed for dry-run.');

            return self::FAILURE;
        }

        $confirmPhrase = trim((string) $this->option('confirm'));
        if ($executeTicketing && $confirmPhrase !== 'ISSUE_PIA_NDC_TICKET') {
            $this->error('Execute ticketing requires --confirm="ISSUE_PIA_NDC_TICKET".');

            return self::FAILURE;
        }

        $actor = $this->resolveActor();
        if ($actor === null) {
            $this->error('actor_required');

            return self::FAILURE;
        }

        $options = [
            'dry_run' => $dryRun,
            'persist' => $executeTicketing,
            'require_fresh_retrieve' => $executeTicketing && ! $noFreshRetrieve,
            'force_preview' => (bool) $this->option('force-preview'),
            'record_ticketing_attempt' => $executeTicketing,
        ];

        try {
            if ($dryRun) {
                $summary = $ticketingService->issueTicketsDryRun($booking, $connection, $actor, $options);
                $this->printSummary($summary);
                $this->line('actor_id='.$actor->id);

                return ($summary['success'] ?? false) ? self::SUCCESS : self::FAILURE;
            }

            $result = $ticketingService->issueTickets($booking, $connection, $actor, $options);
            $summary = is_array($result->safe_summary) ? $result->safe_summary : [];
            $this->printSummary($summary);
            $this->line('actor_id='.$actor->id);
            $this->line('success='.($result->success ? 'true' : 'false'));
            $this->line('status='.$result->status);

            return $result->success ? self::SUCCESS : self::FAILURE;
        } catch (PiaNdcTicketingException $exception) {
            $this->error('ticketing_failed='.$exception->safeMessage);

            return self::FAILURE;
        }
    }

    /**
     * @param  array<string, mixed>  $summary
     */
    private function printSummary(array $summary): void
    {
        foreach ([
            'dry_run',
            'supplier_called',
            'operation',
            'booking_id',
            'order_id',
            'owner_code',
            'duplicate_ticket_guard',
            'payment_type',
            'mco_invoice_configured',
            'request_built',
            'preflight_retrieve_called',
            'order_status',
            'payment_time_limit',
            'error_code',
            'error_message',
            'diagnostic_path',
        ] as $key) {
            if (! array_key_exists($key, $summary)) {
                continue;
            }
            $value = $summary[$key];
            if (is_bool($value)) {
                $this->line($key.'='.($value ? 'true' : 'false'));
            } else {
                $this->line($key.'='.$value);
            }
        }
    }

    private function resolveActor(): ?User
    {
        $actorId = trim((string) $this->option('actor-id'));
        if ($actorId !== '') {
            return User::query()->find((int) $actorId);
        }

        return User::query()
            ->where('account_type', AccountType::PlatformAdmin)
            ->orderBy('id')
            ->first();
    }

    protected function resolveConnection(Booking $booking): ?SupplierConnection
    {
        $id = $this->option('connection');
        if ($id) {
            return SupplierConnection::query()->where('id', (int) $id)->where('provider', SupplierProvider::PiaNdc)->first();
        }

        $meta = is_array($booking->meta) ? $booking->meta : [];
        $supplierBooking = $booking->latestSupplierBooking;
        if ($supplierBooking?->supplier_connection_id) {
            return SupplierConnection::query()
                ->where('id', $supplierBooking->supplier_connection_id)
                ->where('provider', SupplierProvider::PiaNdc)
                ->first();
        }

        return SupplierConnection::query()->find((int) ($meta['supplier_connection_id'] ?? 0))
            ?? SupplierConnection::query()->where('provider', SupplierProvider::PiaNdc)->orderByDesc('is_active')->first();
    }
}
