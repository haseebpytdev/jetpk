<?php

namespace App\Console\Commands;

use App\Enums\SupplierProvider;
use App\Models\Booking;
use App\Services\Suppliers\Sabre\SabreCancelBookingInspectProbe;
use App\Services\Suppliers\Sabre\SabrePccCapabilityMatrix;
use Illuminate\Console\Command;

class SabreInspectCancelBookingCommand extends Command
{
    protected $signature = 'sabre:inspect-cancel-booking
                            {--booking= : Booking ID with an existing Sabre PNR}
                            {--pnr= : Sabre record locator (requires --connection; no Booking row)}
                            {--connection= : Sabre supplier connection ID (required with --pnr)}
                            {--dry-run : Inspect only; no Sabre HTTP (default when --send is omitted)}
                            {--send : Perform live cancelBooking probe on cert or production host (destructive; gated)}
                            {--confirm= : Required with --send; CANCEL-CERT-PNR (cert host) or CANCEL-LIVE-PROD-PNR (api.platform.sabre.com)}
                            {--pre-get-booking : With --send, POST getBooking first (read-only pre-check)}
                            {--style= : Explicit cancel payload style (single candidate; no auto-cycle on live --send)}
                            {--payload-style= : Alias for --style}
                            {--list-styles : List candidate styles, recommendations, and failed reasons (no Sabre HTTP)}
                            {--support-packet : Sanitized cancel probe support summary (attempt history, getBooking schema inventory, escalation template; no cancelBooking HTTP)}
                            {--with-pnr-snapshot : Include stored meta.pnr_itinerary_snapshot diagnostics in cancel_diagnostics}
                            {--refresh-trip-order-context : Read-only POST getBooking for trip-order cancel identifiers (dry-run default; gated live-send with explicit bookingId --style)}';

    protected $description = 'Sprint 0 / Phase 3G-Cancel: Inspect Sabre cancelBooking (dry-run default; --send heavily gated; --pnr direct CERT cleanup)';

    public function handle(SabreCancelBookingInspectProbe $probe): int
    {
        $bookingOpt = $this->option('booking');
        $pnrOpt = $this->option('pnr');
        $hasBooking = $bookingOpt !== null && $bookingOpt !== '';
        $hasPnr = is_string($pnrOpt) && trim($pnrOpt) !== '';

        if ($hasBooking && $hasPnr) {
            $this->components->error('Pass either --booking={id} or --pnr={locator}, not both.');

            return self::FAILURE;
        }

        if (! $hasBooking && ! $hasPnr) {
            $this->components->error('Pass --booking={id} or --pnr={locator}.');

            return self::FAILURE;
        }

        if ($hasPnr) {
            return $this->handleDirectPnr($probe);
        }

        return $this->handleBooking($probe, $bookingOpt);
    }

    protected function handleDirectPnr(SabreCancelBookingInspectProbe $probe): int
    {
        $pnrOpt = $this->option('pnr');
        $pnr = strtoupper(trim((string) $pnrOpt));
        if (! preg_match('/^[A-Z0-9]{5,8}$/', $pnr)) {
            $this->components->error('Invalid --pnr; use 5-8 alphanumeric characters.');

            return self::FAILURE;
        }

        $connectionIdOpt = $this->option('connection');
        if ($connectionIdOpt === null || $connectionIdOpt === '' || ! is_numeric($connectionIdOpt)) {
            $this->components->error('Pass --connection={id} with --pnr.');

            return self::FAILURE;
        }

        $connection = SabrePccCapabilityMatrix::resolveConnection((int) $connectionIdOpt, null);
        if ($connection === null || $connection->provider !== SupplierProvider::Sabre) {
            $this->components->error('Sabre supplier connection not found for --connection='.$connectionIdOpt.'.');

            return self::FAILURE;
        }

        if ((bool) $this->option('list-styles') || (bool) $this->option('support-packet') || (bool) $this->option('with-pnr-snapshot')) {
            $this->components->error('Direct --pnr mode does not support --list-styles, --support-packet, or --with-pnr-snapshot.');

            return self::FAILURE;
        }

        if ((bool) $this->option('pre-get-booking')) {
            $this->components->error('--pre-get-booking is only supported with --booking.');

            return self::FAILURE;
        }

        $send = (bool) $this->option('send');
        $dryRun = (bool) $this->option('dry-run');
        if ($dryRun && $send) {
            $this->components->error('Use either --dry-run or --send, not both.');

            return self::FAILURE;
        }

        if ($send) {
            $this->line('Live direct PNR cancel probe: POST cancelBooking once when all gates pass (no Booking row, no booking.status update).');
        } else {
            $this->line('Dry-run direct PNR: build cancel payload candidates (getBooking when --refresh-trip-order-context; no cancelBooking HTTP).');
        }
        $this->newLine();

        $confirm = $this->option('confirm');
        $confirmPhrase = is_string($confirm) ? $confirm : null;
        $payloadStyle = $this->resolvePayloadStyleOption();
        $refreshTripOrderContext = (bool) $this->option('refresh-trip-order-context');

        $payload = $probe->inspectDirectPnr(
            $connection,
            $pnr,
            $send,
            $confirmPhrase,
            $payloadStyle,
            $refreshTripOrderContext,
        );

        return $this->emitPayload($payload);
    }

    protected function handleBooking(SabreCancelBookingInspectProbe $probe, mixed $bookingOpt): int
    {
        if ($bookingOpt === null || $bookingOpt === '' || ! is_numeric($bookingOpt)) {
            $this->components->error('Pass --booking={id} with a numeric booking id.');

            return self::FAILURE;
        }

        $booking = Booking::query()->find((int) $bookingOpt);
        if ($booking === null) {
            $this->components->error('Booking not found.');

            return self::FAILURE;
        }

        $withPnrSnapshot = (bool) $this->option('with-pnr-snapshot');
        $refreshTripOrderContext = (bool) $this->option('refresh-trip-order-context');

        if ((bool) $this->option('list-styles')) {
            $payload = $probe->listStyles($booking, $withPnrSnapshot, $refreshTripOrderContext);

            return $this->emitPayload($payload);
        }

        if ((bool) $this->option('support-packet')) {
            $payload = $probe->supportPacket($booking, $withPnrSnapshot, $refreshTripOrderContext);

            return $this->emitPayload($payload);
        }

        $send = (bool) $this->option('send');
        $dryRun = (bool) $this->option('dry-run');
        if ($dryRun && $send) {
            $this->components->error('Use either --dry-run or --send, not both.');

            return self::FAILURE;
        }

        if ($send) {
            $this->line('Live probe: POST cancelBooking once with the selected style when all gates pass (no booking.status update, no auto-retry).');
        } else {
            $this->line('Dry-run: build cancel payload candidates and show endpoint config (no Sabre HTTP).');
        }
        $this->newLine();

        $confirm = $this->option('confirm');
        $confirmPhrase = is_string($confirm) ? $confirm : null;
        $preGet = (bool) $this->option('pre-get-booking');
        if ($preGet && ! $send) {
            $this->components->error('--pre-get-booking requires --send.');

            return self::FAILURE;
        }

        $payloadStyle = $this->resolvePayloadStyleOption();

        $payload = $probe->inspect(
            $booking,
            $send,
            $confirmPhrase,
            $preGet,
            $payloadStyle,
            $withPnrSnapshot,
            $refreshTripOrderContext,
        );

        return $this->emitPayload($payload);
    }

    protected function resolvePayloadStyleOption(): ?string
    {
        $styleOpt = $this->option('style');
        if (! is_string($styleOpt) || trim($styleOpt) === '') {
            $legacy = $this->option('payload-style');
            $styleOpt = is_string($legacy) ? $legacy : null;
        }

        return is_string($styleOpt) && trim($styleOpt) !== '' ? trim($styleOpt) : null;
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    protected function emitPayload(array $payload): int
    {
        if (isset($payload['error'])) {
            $this->line('cancel_inspect_json='.json_encode($payload, JSON_UNESCAPED_SLASHES));
            $this->components->error((string) $payload['error']);

            return self::FAILURE;
        }

        $this->line('cancel_inspect_json='.json_encode($payload, JSON_UNESCAPED_SLASHES));

        return self::SUCCESS;
    }
}
