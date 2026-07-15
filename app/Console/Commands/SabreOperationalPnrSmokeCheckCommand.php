<?php

namespace App\Console\Commands;

use App\Models\Booking;
use App\Models\SupplierBookingAttempt;
use App\Services\Suppliers\Sabre\PnrRetrieve\SabreTripOrdersGetBookingItineraryMapper;
use App\Support\Bookings\SabreOperationalPnrReadiness;
use Illuminate\Console\Command;

/**
 * BF8-A: Read-only post-booking operational smoke check (no Sabre HTTP, no DB writes).
 */
class SabreOperationalPnrSmokeCheckCommand extends Command
{
    public const PRODUCTION_READONLY_CONFIRM_PHRASE = 'READONLY-OPERATIONAL-PNR-SMOKE';

    protected $signature = 'sabre:operational-pnr-smoke-check
                            {--booking= : Booking ID}
                            {--confirm= : Production only: READONLY-OPERATIONAL-PNR-SMOKE}
                            {--json : Emit diagnostic JSON only}';

    protected $description = 'BF8-A operational Sabre PNR smoke check (read-only; production requires --confirm)';

    public function handle(SabreOperationalPnrReadiness $readiness): int
    {
        if ($this->resolveGate() === null) {
            return self::FAILURE;
        }

        $raw = $this->option('booking');
        if ($raw === null || $raw === '' || ! is_numeric($raw)) {
            $this->components->error('Pass --booking={id} with a numeric booking id.');

            return self::FAILURE;
        }

        $booking = Booking::query()
            ->with(['supplierBookingAttempts', 'tickets'])
            ->find((int) $raw);
        if ($booking === null) {
            $this->components->error('Booking not found.');

            return self::FAILURE;
        }

        $payload = $this->buildPayload($booking, $readiness);

        if ((bool) $this->option('json')) {
            $this->line(json_encode($payload, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE));

            return self::SUCCESS;
        }

        $this->line('live_supplier_call_attempted=false');
        $this->newLine();

        foreach ($payload['flags'] as $key => $value) {
            $this->printKeyValue($key, $value);
        }
        $this->newLine();

        foreach ($payload['booking'] as $key => $value) {
            $this->printKeyValue('booking_'.$key, $value);
        }
        $this->newLine();

        foreach ($payload['attempts'] as $action => $row) {
            if (! is_array($row)) {
                continue;
            }
            foreach ($row as $key => $value) {
                $this->printKeyValue('attempt_'.$action.'_'.$key, $value);
            }
        }
        $this->newLine();

        foreach ($payload['sync'] as $key => $value) {
            $this->printKeyValue('sync_'.$key, $value);
        }
        $this->newLine();

        $segments = is_array($payload['snapshot_segments'] ?? null) ? $payload['snapshot_segments'] : [];
        $this->line('snapshot_segment_count='.count($segments));
        foreach ($segments as $index => $segment) {
            if (! is_array($segment)) {
                continue;
            }
            $this->line('snapshot_segment_'.$index.'='.json_encode($segment, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE));
        }
        $this->newLine();

        foreach ($payload['readiness'] as $key => $value) {
            $this->printKeyValue('readiness_'.$key, $value);
        }

        $this->newLine();
        $this->printKeyValue('smoke_check_passed', $payload['smoke_check_passed']);

        return self::SUCCESS;
    }

    /**
     * @return array<string, mixed>
     */
    protected function buildPayload(Booking $booking, SabreOperationalPnrReadiness $readiness): array
    {
        $meta = is_array($booking->meta) ? $booking->meta : [];
        $syncSidecar = is_array($meta['pnr_itinerary_sync'] ?? null) ? $meta['pnr_itinerary_sync'] : [];
        $syncStatus = trim((string) ($syncSidecar['status'] ?? ''));
        $snapshot = is_array($meta['pnr_itinerary_snapshot'] ?? null) ? $meta['pnr_itinerary_snapshot'] : [];
        $snapshotSegments = is_array($snapshot['segments'] ?? null) ? array_values($snapshot['segments']) : [];

        $createAttempt = $this->latestAttempt($booking, 'create_pnr');
        $retrieveAttempt = $this->latestAttempt($booking, 'pnr_retrieve');

        $evaluation = $readiness->evaluate($booking);
        $pnrPresent = trim((string) ($booking->pnr ?? '')) !== ''
            || trim((string) ($booking->supplier_reference ?? '')) !== '';
        $ticketingEnabled = (bool) config('suppliers.sabre.ticketing_enabled', false);
        $hasTickets = $booking->tickets->isNotEmpty();
        $isTicketed = ($syncSidecar['is_ticketed'] ?? false) === true || $hasTickets;

        $createSuccess = $createAttempt !== null
            && in_array(strtolower((string) $createAttempt->status), ['success', 'created', 'completed'], true);
        $retrieveSuccess = $retrieveAttempt !== null
            && in_array(strtolower((string) $retrieveAttempt->status), ['success', 'created', 'completed'], true);

        $smokeCheckPassed = $pnrPresent
            && $createSuccess
            && $retrieveSuccess
            && $syncStatus === 'synced'
            && ! $ticketingEnabled
            && ! $isTicketed
            && $snapshotSegments !== [];

        return [
            'flags' => [
                'booking_enabled' => (bool) config('suppliers.sabre.booking_enabled', false),
                'booking_live_call_enabled' => (bool) config('suppliers.sabre.booking_live_call_enabled', false),
                'ticketing_enabled' => $ticketingEnabled,
                'cpnr_connecting_same_carrier_gds_enabled' => (bool) config('suppliers.sabre.cpnr_connecting_same_carrier_gds_enabled', false),
                'cpnr_connecting_same_carrier_public_checkout_enabled' => (bool) config('suppliers.sabre.cpnr_connecting_same_carrier_public_checkout_enabled', false),
                'verified_multiseg_auto_pnr_enabled' => (bool) config('suppliers.sabre.verified_multiseg_auto_pnr_enabled', false),
                'cpnr_allow_nn_halt_on_status_cert_operational' => (bool) config('suppliers.sabre.cpnr_allow_nn_halt_on_status_cert_operational', false),
            ],
            'booking' => [
                'id' => $booking->id,
                'reference' => (string) ($booking->reference_code ?? $booking->booking_reference ?? ''),
                'pnr' => (string) ($booking->pnr ?? ''),
                'supplier_reference' => (string) ($booking->supplier_reference ?? ''),
                'supplier_booking_status' => (string) ($booking->supplier_booking_status ?? ''),
                'payment_status' => (string) ($booking->payment_status ?? ''),
                'ticketing_status' => (string) ($booking->ticketing_status ?? ''),
                'is_ticketed' => $isTicketed,
            ],
            'attempts' => [
                'create_pnr' => $this->attemptRow($createAttempt),
                'pnr_retrieve' => $this->attemptRow($retrieveAttempt),
            ],
            'sync' => [
                'status' => $syncStatus !== '' ? $syncStatus : 'not_attempted',
                'endpoint' => trim((string) ($syncSidecar['endpoint_path'] ?? '')) !== ''
                    ? trim((string) $syncSidecar['endpoint_path'])
                    : SabreTripOrdersGetBookingItineraryMapper::ENDPOINT_PATH,
                'synced_at' => (string) ($syncSidecar['synced_at'] ?? $syncSidecar['attempted_at'] ?? ''),
                'segment_count' => count($snapshotSegments),
                'airline_locator_present' => ($syncSidecar['airline_locator_present'] ?? false) === true,
            ],
            'snapshot_segments' => $this->safeSegmentSummaries($snapshotSegments),
            'readiness' => [
                'would_attempt_pnr' => ($evaluation['would_attempt_pnr'] ?? false) === true,
                'reason_code' => (string) ($evaluation['reason_code'] ?? ''),
                'controlled_pnr_certification_status' => (string) ($evaluation['controlled_pnr_certification_status'] ?? ''),
            ],
            'smoke_check_passed' => $smokeCheckPassed,
        ];
    }

    /**
     * @return array{id: int|null, status: string}|null
     */
    protected function attemptRow(?SupplierBookingAttempt $attempt): ?array
    {
        if ($attempt === null) {
            return null;
        }

        return [
            'id' => $attempt->id,
            'status' => (string) $attempt->status,
        ];
    }

    protected function latestAttempt(Booking $booking, string $action): ?SupplierBookingAttempt
    {
        return $booking->supplierBookingAttempts
            ->filter(fn (SupplierBookingAttempt $item) => (string) $item->action === $action)
            ->sortByDesc('id')
            ->first();
    }

    /**
     * @param  list<array<string, mixed>>  $segments
     * @return list<array<string, string>>
     */
    protected function safeSegmentSummaries(array $segments): array
    {
        $rows = [];
        foreach ($segments as $segment) {
            if (! is_array($segment)) {
                continue;
            }
            $ac = strtoupper(trim((string) ($segment['airline_code'] ?? '')));
            $fn = trim((string) ($segment['flight_number'] ?? ''));
            $flight = $fn !== ''
                ? ($ac !== '' && ! str_starts_with(strtoupper($fn), $ac) ? $ac.$fn : $fn)
                : $ac;
            $rows[] = [
                'flight' => $flight,
                'route' => strtoupper(trim((string) ($segment['origin'] ?? '')))
                    .'→'.strtoupper(trim((string) ($segment['destination'] ?? ''))),
                'class' => strtoupper(trim((string) ($segment['booking_class'] ?? $segment['class'] ?? ''))),
                'status' => strtoupper(trim((string) ($segment['segment_status'] ?? $segment['status'] ?? ''))),
            ];
        }

        return $rows;
    }

    /**
     * @return array{production_readonly_confirmed: bool}|null
     */
    protected function resolveGate(): ?array
    {
        $env = (string) config('app.env', 'production');
        if (in_array($env, ['local', 'testing'], true)) {
            return ['production_readonly_confirmed' => false];
        }

        if ($env !== 'production') {
            $this->components->error('This command only runs when APP_ENV is local, testing, or production.');

            return null;
        }

        $confirm = trim((string) $this->option('confirm'));
        if ($confirm === self::PRODUCTION_READONLY_CONFIRM_PHRASE) {
            return ['production_readonly_confirmed' => true];
        }

        if ($confirm === '') {
            $this->components->error(
                'Production requires --confirm='.self::PRODUCTION_READONLY_CONFIRM_PHRASE.' for read-only diagnostic.'
            );
        } else {
            $this->components->error('Invalid --confirm phrase for production read-only diagnostic.');
        }

        return null;
    }

    protected function printKeyValue(string $key, mixed $value): void
    {
        if (is_bool($value)) {
            $this->line($key.'='.($value ? 'true' : 'false'));
        } elseif ($value === null || $value === '') {
            $this->line($key.'=');
        } else {
            $this->line($key.'='.$value);
        }
    }
}
