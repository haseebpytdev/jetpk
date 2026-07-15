<?php

namespace App\Console\Commands;

use App\Models\Booking;
use App\Models\SupplierConnection;
use App\Models\User;
use App\Services\Suppliers\Sabre\Ticketing\SabreGdsTicketingService;
use Illuminate\Console\Command;

class SabreGdsIssueTicketCommand extends Command
{
    protected $signature = 'sabre:gds-issue-ticket
                            {--booking= : Booking ID}
                            {--dry-run : Readiness/preview only — no supplier HTTP}
                            {--send : Live issue ticket HTTP}
                            {--confirm= : Exact phrase ISSUE-TICKET-FOR-BOOKING-{id}}';

    protected $description = 'Controlled Sabre GDS issue ticket — dry-run default; live requires env gates + --confirm';

    public function handle(SabreGdsTicketingService $ticketingService): int
    {
        $booking = $this->resolveBooking();
        if ($booking === null) {
            $this->error('Booking not found.');

            return self::FAILURE;
        }

        $connection = $this->resolveConnection($booking);
        if ($connection === null) {
            $this->error('Sabre supplier connection not found.');

            return self::FAILURE;
        }

        // Default is dry-run; only --send (without --dry-run) may perform live supplier HTTP.
        $dryRun = (bool) $this->option('dry-run') || ! $this->option('send');
        $actor = User::query()->where('account_type', 'admin')->orderBy('id')->first()
            ?? User::query()->orderBy('id')->first();

        if ($actor === null) {
            $this->error('No user available for command context.');

            return self::FAILURE;
        }

        $result = $ticketingService->issueTickets($booking, $connection, $actor, [
            'dry_run' => $dryRun,
            'confirm' => $this->option('confirm'),
        ]);

        $summary = is_array($result->safe_summary) ? $result->safe_summary : [];
        $this->line('success='.($result->success ? 'true' : 'false'));
        $this->line('status='.$result->status);
        $this->line('live_supplier_call_attempted='.(($summary['live_supplier_call_attempted'] ?? false) ? 'true' : 'false'));
        $this->line('error_code='.($result->error_code ?? ''));
        $this->line('error_message='.($result->error_message ?? ''));
        if (isset($summary['blockers'])) {
            $this->line('blockers='.json_encode($summary['blockers'], JSON_UNESCAPED_SLASHES));
        }

        return $result->success ? self::SUCCESS : self::FAILURE;
    }

    private function resolveBooking(): ?Booking
    {
        $id = $this->option('booking');
        if ($id === null || ! is_numeric($id)) {
            return null;
        }

        return Booking::query()->with(['tickets', 'latestTicketingAttempt', 'passengers', 'fareBreakdown', 'latestSupplierBooking'])->find((int) $id);
    }

    private function resolveConnection(Booking $booking): ?SupplierConnection
    {
        $meta = is_array($booking->meta) ? $booking->meta : [];
        $connectionId = (int) ($meta['supplier_connection_id'] ?? $booking->latestSupplierBooking?->supplier_connection_id ?? 0);

        if ($connectionId > 0) {
            return SupplierConnection::query()->find($connectionId);
        }

        return SupplierConnection::query()->where('provider', 'sabre')->where('is_active', true)->orderBy('id')->first();
    }
}
