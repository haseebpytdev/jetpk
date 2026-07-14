<?php

namespace App\Console\Commands;

use App\Models\Booking;
use App\Models\SupplierConnection;
use App\Models\User;
use App\Services\Suppliers\Sabre\Ticketing\SabreGdsTicketServicingReadiness;
use App\Services\Suppliers\Sabre\Ticketing\SabreGdsVoidTicketService;
use App\Support\Sabre\SabreMutationCommandGate;
use Illuminate\Console\Command;

class SabreGdsVoidTicketCommand extends Command
{
    protected $signature = 'sabre:gds-void-ticket
                            {--booking= : Booking ID}
                            {--ticket= : Ticket number}
                            {--dry-run : Readiness only (default)}
                            {--send : Live void HTTP}
                            {--confirm= : Exact void confirmation phrase}';

    protected $description = 'Sabre GDS void ticket — dry-run default; live requires --send + env + --confirm';

    public function handle(
        SabreGdsTicketServicingReadiness $readiness,
        SabreGdsVoidTicketService $voidService,
    ): int {
        $booking = $this->resolveBooking();
        if ($booking === null) {
            $this->error('Booking not found.');

            return self::FAILURE;
        }

        $ticketStr = is_string($this->option('ticket')) ? $this->option('ticket') : null;
        $evaluation = $readiness->evaluateVoid($booking, $ticketStr);
        $expected = (string) ($evaluation['confirm_phrase'] ?? 'VOID-TICKET-FOR-BOOKING-'.$booking->id);

        $gate = SabreMutationCommandGate::evaluate(
            (bool) $this->option('dry-run'),
            $this->option('send') !== null ? '1' : null,
            $this->option('confirm'),
            $expected,
            ['suppliers.sabre.void_enabled', 'suppliers.sabre.void_live_call_enabled'],
        );

        if (! $gate['live_allowed']) {
            $this->line(json_encode(array_merge($evaluation, [
                'gate' => $gate,
                'live_supplier_call_attempted' => false,
            ]), JSON_UNESCAPED_SLASHES));

            return self::SUCCESS;
        }

        $connection = $this->resolveConnection($booking);
        if ($connection === null) {
            $this->error('Sabre connection not found.');

            return self::FAILURE;
        }

        $actor = User::query()->where('account_type', 'admin')->orderBy('id')->first();
        $result = $voidService->voidTicket($booking, $connection, $actor, $ticketStr, [
            'dry_run' => false,
            'confirm' => $this->option('confirm'),
        ]);

        $this->line(json_encode([
            'success' => $result->success,
            'status' => $result->status,
            'error_code' => $result->error_code,
            'safe_summary' => $result->safe_summary,
        ], JSON_UNESCAPED_SLASHES));

        return $result->success ? self::SUCCESS : self::FAILURE;
    }

    private function resolveBooking(): ?Booking
    {
        $id = $this->option('booking');

        return is_numeric($id) ? Booking::query()->with('tickets')->find((int) $id) : null;
    }

    private function resolveConnection(Booking $booking): ?SupplierConnection
    {
        $meta = is_array($booking->meta) ? $booking->meta : [];
        $id = (int) ($meta['supplier_connection_id'] ?? 0);

        return $id > 0
            ? SupplierConnection::query()->find($id)
            : SupplierConnection::query()->where('provider', 'sabre')->where('is_active', true)->orderBy('id')->first();
    }
}
