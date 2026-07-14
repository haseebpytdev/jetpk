<?php

namespace App\Console\Commands;

use App\Enums\SupplierProvider;
use App\Models\Booking;
use App\Services\Suppliers\Sabre\SabreInspectGate;
use App\Services\Suppliers\Sabre\SabrePccCapabilityMatrix;
use App\Services\Suppliers\Sabre\SabrePnrRetrieveProbe;
use Illuminate\Console\Command;

class SabreInspectPnrRetrieveCommand extends Command
{
    protected $signature = 'sabre:inspect-pnr-retrieve
                            {--booking= : Booking ID with an existing Sabre PNR}
                            {--pnr= : Sabre record locator (requires --send and --connection)}
                            {--connection= : Sabre supplier connection ID (required with --pnr)}
                            {--path= : Probe a single endpoint path (must start with /)}
                            {--body-style=auto : auto|passenger_records_read|reservation_retrieve|trip_orders_get_booking}
                            {--send : Perform live POST probes (OAuth + retrieve paths; no DB writes)}
                            {--preview-json : Include redacted request_body_redacted per endpoint row}
                            {--shape-tree : Include safe key/type shape_tree per live endpoint row (requires --send)}
                            {--map-preview : Include safe segment map_preview per live endpoint row (requires --send)}';

    protected $description = 'B84A/B84B.0: Probe Sabre PNR retrieve endpoints (local/testing; production read-only with --send + SABRE_PNR_RETRIEVE_INSPECT_ENABLED)';

    public function handle(SabrePnrRetrieveProbe $probe): int
    {
        $send = (bool) $this->option('send');
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

        $pathOpt = $this->option('path');
        $pathOverride = is_string($pathOpt) && trim($pathOpt) !== '' ? trim($pathOpt) : null;
        $styleOpt = $this->option('body-style');
        $bodyStyle = is_string($styleOpt) && trim($styleOpt) !== '' ? trim($styleOpt) : 'auto';
        $previewJson = (bool) $this->option('preview-json');
        $shapeTree = (bool) $this->option('shape-tree');
        $mapPreview = (bool) $this->option('map-preview');

        if (($shapeTree || $mapPreview) && ! $send) {
            $this->components->error('--shape-tree and --map-preview require --send (live response needed; no DB writes).');

            return self::FAILURE;
        }

        if ($hasPnr) {
            return $this->handleDirectPnr(
                $probe,
                trim((string) $pnrOpt),
                $send,
                $pathOverride,
                $bodyStyle,
                $previewJson,
                $shapeTree,
                $mapPreview,
            );
        }

        return $this->handleBooking(
            $probe,
            $bookingOpt,
            $send,
            $pathOverride,
            $bodyStyle,
            $previewJson,
            $shapeTree,
            $mapPreview,
        );
    }

    protected function handleDirectPnr(
        SabrePnrRetrieveProbe $probe,
        string $pnrRaw,
        bool $send,
        ?string $pathOverride,
        string $bodyStyle,
        bool $previewJson,
        bool $shapeTree,
        bool $mapPreview,
    ): int {
        if (! $send) {
            $this->components->error('Pass --send with --pnr for live retrieve (no DB writes).');

            return self::FAILURE;
        }

        $connectionIdOpt = $this->option('connection');
        if ($connectionIdOpt === null || $connectionIdOpt === '' || ! is_numeric($connectionIdOpt)) {
            $this->components->error('Pass --connection={id} with --pnr.');

            return self::FAILURE;
        }

        $pnr = strtoupper(trim($pnrRaw));
        if (! preg_match('/^[A-Z0-9]{5,8}$/', $pnr)) {
            $this->components->error('Invalid --pnr; use 5-8 alphanumeric characters.');

            return self::FAILURE;
        }

        $connection = SabrePccCapabilityMatrix::resolveConnection((int) $connectionIdOpt, null);
        if ($connection === null || $connection->provider !== SupplierProvider::Sabre) {
            $this->components->error('Sabre supplier connection not found for --connection='.$connectionIdOpt.'.');

            return self::FAILURE;
        }

        $blockReason = SabreInspectGate::pnrRetrieveDirectInspectBlockReason($send, $connection);
        if ($blockReason !== null) {
            $this->components->error($blockReason);

            return self::FAILURE;
        }

        $this->line('Live direct PNR probe: POST retrieve endpoints with record locator (no DB writes, no ticketing).');
        $this->newLine();

        $payload = $probe->probeDirectPnr(
            $connection,
            $pnr,
            $send,
            $pathOverride,
            $bodyStyle,
            $previewJson,
            $shapeTree,
            $mapPreview,
        );

        return $this->emitPayload($payload);
    }

    protected function handleBooking(
        SabrePnrRetrieveProbe $probe,
        mixed $bookingOpt,
        bool $send,
        ?string $pathOverride,
        string $bodyStyle,
        bool $previewJson,
        bool $shapeTree,
        bool $mapPreview,
    ): int {
        $blockReason = SabreInspectGate::pnrRetrieveInspectBlockReason($send);
        if ($blockReason !== null) {
            $this->components->error($blockReason);

            return self::FAILURE;
        }

        if ($bookingOpt === null || $bookingOpt === '' || ! is_numeric($bookingOpt)) {
            $this->components->error('Pass --booking={id} with a numeric booking id.');

            return self::FAILURE;
        }

        $booking = Booking::query()->find((int) $bookingOpt);
        if ($booking === null) {
            $this->components->error('Booking not found.');

            return self::FAILURE;
        }

        if (! $send) {
            $this->line('Inspect-only: pass --send to POST retrieve endpoints (no DB writes).');
        } else {
            $this->line('Live probe: POST retrieve endpoints with minimal recordLocator/confirmationId body (no DB writes, no ticketing).');
        }
        $this->newLine();

        $payload = $probe->probe($booking, $send, $pathOverride, $bodyStyle, $previewJson, $shapeTree, $mapPreview);

        return $this->emitPayload($payload);
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    protected function emitPayload(array $payload): int
    {
        if (isset($payload['error'])) {
            $this->line('pnr_retrieve_probe_json='.json_encode($payload, JSON_UNESCAPED_SLASHES));
            $this->components->error((string) $payload['error']);

            return self::FAILURE;
        }

        $this->line('pnr_retrieve_probe_json='.json_encode($payload, JSON_UNESCAPED_SLASHES));

        return self::SUCCESS;
    }
}
