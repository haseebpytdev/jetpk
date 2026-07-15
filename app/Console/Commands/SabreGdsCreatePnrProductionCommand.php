<?php

namespace App\Console\Commands;

use App\Models\Booking;
use App\Models\SupplierConnection;
use App\Models\User;
use App\Services\Suppliers\Sabre\Booking\SabreBookingService;
use App\Services\Suppliers\Sabre\Gds\SabreGdsRevalidationService;
use App\Support\Bookings\SabreControlledPnrReadiness;
use App\Support\Sabre\SabreMutationCommandGate;
use Illuminate\Console\Command;

/**
 * Production-capable GDS PNR create with mandatory revalidation unless admin override recorded.
 */
class SabreGdsCreatePnrProductionCommand extends Command
{
    protected $signature = 'sabre:gds-create-pnr-production
                            {--booking= : Booking ID}
                            {--dry-run : Readiness/payload preview (default)}
                            {--send : Attempt live PNR create}
                            {--confirm= : CREATE-PNR-FOR-BOOKING-{id}}
                            {--skip-revalidation : Admin override — requires meta override audit}';

    protected $description = 'Sabre GDS production PNR create — dry-run default; live requires --send + env + --confirm';

    public function handle(
        SabreControlledPnrReadiness $readiness,
        SabreBookingService $sabreBookingService,
        SabreGdsRevalidationService $revalidationService,
    ): int {
        $booking = $this->resolveBooking();
        if ($booking === null) {
            $this->error('Booking not found.');

            return self::FAILURE;
        }

        $expectedConfirm = 'CREATE-PNR-FOR-BOOKING-'.$booking->id;
        $gate = SabreMutationCommandGate::evaluate(
            (bool) $this->option('dry-run'),
            $this->option('send') !== null ? '1' : null,
            $this->option('confirm'),
            $expectedConfirm,
            ['suppliers.sabre.booking_enabled', 'suppliers.sabre.booking_live_call_enabled'],
        );

        $evaluation = $readiness->evaluate($booking, [
            'context' => 'create_command',
            'require_admin_confirmation' => $gate['live_allowed'],
            'admin_confirmation_provided' => $gate['confirm_matches'],
        ]);

        $payload = [
            'booking_id' => $booking->id,
            'gate' => $gate,
            'readiness' => [
                'eligible' => (bool) ($evaluation['eligible'] ?? false),
                'can_attempt_supplier_pnr' => (bool) ($evaluation['can_attempt_supplier_pnr'] ?? false),
                'blockers' => $evaluation['blockers'] ?? [],
            ],
            'live_supplier_call_attempted' => false,
            'pnr_create_attempted' => false,
        ];

        if (! $gate['live_allowed']) {
            $this->line(json_encode($payload, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));

            return self::SUCCESS;
        }

        if (($evaluation['can_attempt_supplier_pnr'] ?? false) !== true) {
            $payload['reason'] = 'readiness_blocked';
            $this->line(json_encode($payload, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));

            return self::FAILURE;
        }

        $connection = $this->resolveConnection($booking);
        if ($connection === null) {
            $this->error('Sabre connection not resolved.');

            return self::FAILURE;
        }

        $skipRevalidation = (bool) $this->option('skip-revalidation');
        $meta = is_array($booking->meta) ? $booking->meta : [];
        $hasOverride = (bool) ($meta['sabre_revalidation_admin_override']['approved'] ?? false);

        if (! $skipRevalidation || ! $hasOverride) {
            $reval = $revalidationService->revalidateForBooking($booking, $connection, true);
            $payload['revalidation'] = [
                'success' => ($reval['success'] ?? false) === true,
                'reason_code' => (string) ($reval['reason_code'] ?? ''),
            ];
            if (($reval['success'] ?? false) !== true && ! $hasOverride) {
                $payload['reason'] = 'revalidation_required';
                $this->line(json_encode($payload, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));

                return self::FAILURE;
            }
        }

        $actor = User::query()->where('account_type', 'admin')->orderBy('id')->first()
            ?? User::query()->orderBy('id')->first();

        if ($actor === null) {
            $this->error('No actor user for PNR create.');

            return self::FAILURE;
        }

        $result = $sabreBookingService->createSupplierBooking(
            $booking,
            $actor,
            adminOverride: true,
            allowControlledStaffPnr: true,
            explicitRetry: false,
            attemptSource: 'controlled_pnr_command',
        );

        $payload['live_supplier_call_attempted'] = true;
        $payload['pnr_create_attempted'] = true;
        $payload['result'] = [
            'success' => $result->success,
            'pnr' => $result->pnr,
            'reason_code' => $result->error_code ?? $result->status,
        ];

        $this->line(json_encode($payload, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));

        return $result->success ? self::SUCCESS : self::FAILURE;
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

    private function resolveBooking(): ?Booking
    {
        $id = $this->option('booking');
        if ($id === null || ! is_numeric($id)) {
            return null;
        }

        return Booking::query()->with(['passengers', 'latestSupplierBooking'])->find((int) $id);
    }
}
