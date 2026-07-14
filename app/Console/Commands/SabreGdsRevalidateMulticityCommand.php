<?php

namespace App\Console\Commands;

use App\Models\SupplierConnection;
use App\Services\Suppliers\Sabre\Gds\SabreGdsRevalidationService;
use App\Services\Suppliers\Sabre\Gds\SabreRevalidationPayloadBuilder;
use App\Support\Sabre\SabreMutationCommandGate;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\File;

class SabreGdsRevalidateMulticityCommand extends Command
{
    protected $signature = 'sabre:gds-revalidate-multicity
                            {--fixture= : Fixture name under tests/Fixtures/sabre/multicity/}
                            {--connection= : Supplier connection ID}
                            {--dry-run : Preview only (default)}
                            {--send : Attempt live revalidation HTTP}
                            {--confirm= : REVALIDATE-MULTICITY-TEST}';

    protected $description = 'Sabre GDS multi-O&D revalidation probe — uses fixture or booking context';

    public function handle(SabreGdsRevalidationService $revalidationService): int
    {
        $connection = $this->resolveConnection();
        if ($connection === null) {
            $this->error('Sabre connection not found.');

            return self::FAILURE;
        }

        $fixture = trim((string) ($this->option('fixture') ?? 'two_od_lhe_dxb_ist'));
        $draftPath = base_path('tests/Fixtures/sabre/multicity/'.$fixture.'.json');
        if (! File::exists($draftPath)) {
            $this->error('Fixture not found: '.$draftPath);

            return self::FAILURE;
        }

        $draft = json_decode(File::get($draftPath), true);
        if (! is_array($draft)) {
            $this->error('Invalid fixture JSON.');

            return self::FAILURE;
        }

        $gate = SabreMutationCommandGate::evaluate(
            (bool) $this->option('dry-run'),
            $this->option('send') !== null ? '1' : null,
            $this->option('confirm'),
            'REVALIDATE-MULTICITY-TEST',
            ['suppliers.sabre.booking_live_call_enabled', 'suppliers.sabre.revalidate_before_booking'],
        );

        if (! $gate['live_allowed']) {
            $builder = app(SabreRevalidationPayloadBuilder::class);
            $payload = $builder->buildPayload($draft, 'iati_like_bfm_revalidate_v1');
            $this->line(json_encode([
                'mode' => 'dry_run',
                'fixture' => $fixture,
                'odi_count' => count(data_get($payload, 'OTA_AirLowFareSearchRQ.OriginDestinationInformation', [])),
                'gate' => $gate,
                'payload_safe_summary' => $builder->safePayloadSummary($payload),
                'live_supplier_call_attempted' => false,
            ], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));

            return self::SUCCESS;
        }

        $outcome = $revalidationService->revalidateMulticityDraft($draft, $connection);
        $this->line(json_encode([
            'mode' => 'live',
            'fixture' => $fixture,
            'success' => ($outcome['success'] ?? false) === true,
            'reason_code' => (string) ($outcome['reason_code'] ?? ''),
            'fare_comparison' => $outcome['fare_comparison'] ?? [],
            'live_supplier_call_attempted' => true,
        ], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));

        return ($outcome['success'] ?? false) ? self::SUCCESS : self::FAILURE;
    }

    private function resolveConnection(): ?SupplierConnection
    {
        $connectionId = (int) $this->option('connection');
        if ($connectionId > 0) {
            return SupplierConnection::query()->find($connectionId);
        }

        return SupplierConnection::query()->where('provider', 'sabre')->where('is_active', true)->orderBy('id')->first();
    }
}
