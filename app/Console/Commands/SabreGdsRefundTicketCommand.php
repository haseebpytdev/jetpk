<?php

namespace App\Console\Commands;

use App\Models\Booking;
use App\Models\SupplierConnection;
use App\Models\User;
use App\Services\Suppliers\Sabre\Ticketing\SabreGdsRefundTicketService;
use App\Services\Suppliers\Sabre\Ticketing\SabreGdsTicketServicingReadiness;
use App\Support\Sabre\SabreMutationCommandGate;
use Illuminate\Console\Command;

class SabreGdsRefundTicketCommand extends Command
{
    protected $signature = 'sabre:gds-refund-ticket
                            {--booking= : Booking ID}
                            {--ticket= : Ticket number}
                            {--amount= : Refund amount}
                            {--reason= : Refund reason}
                            {--dry-run : Readiness only (default)}
                            {--send : Live refund or manual record}
                            {--confirm= : Exact refund confirmation phrase}';

    protected $description = 'Sabre GDS refund ticket — live API when configured; otherwise manual workflow record';

    public function handle(
        SabreGdsTicketServicingReadiness $readiness,
        SabreGdsRefundTicketService $refundService,
    ): int {
        $booking = $this->resolveBooking();
        if ($booking === null) {
            $this->error('Booking not found.');

            return self::FAILURE;
        }

        $ticketStr = is_string($this->option('ticket')) ? $this->option('ticket') : null;
        $evaluation = $readiness->evaluateRefund($booking, $ticketStr);
        $expected = (string) ($evaluation['confirm_phrase'] ?? 'REFUND-TICKET-FOR-BOOKING-'.$booking->id);

        $gate = SabreMutationCommandGate::evaluate(
            (bool) $this->option('dry-run'),
            $this->option('send') !== null ? '1' : null,
            $this->option('confirm'),
            $expected,
            ['suppliers.sabre.refund_enabled'],
        );

        if (! $gate['live_allowed'] && ! ($gate['send_requested'] ?? false)) {
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
        $amount = is_numeric($this->option('amount')) ? (float) $this->option('amount') : null;
        $reason = is_string($this->option('reason')) ? $this->option('reason') : null;

        $result = $refundService->refundTicket($booking, $connection, $actor, $ticketStr, [
            'dry_run' => ! ($gate['send_requested'] ?? false) || ! ($gate['confirm_matches'] ?? false),
            'confirm' => $this->option('confirm'),
            'refund_amount' => $amount,
            'refund_reason' => $reason ?? 'Customer requested refund',
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
