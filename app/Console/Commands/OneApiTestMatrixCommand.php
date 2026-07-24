<?php

namespace App\Console\Commands;

use App\Models\SupplierConnection;
use App\Support\OneApi\OneApiMatrixCaseRegistry;
use App\Support\OneApi\OneApiTestMatrixRunner;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\File;

class OneApiTestMatrixCommand extends Command
{
    protected $signature = 'ota:one-api-test-matrix
        {--connection= : Supplier connection ID}
        {--mode=fixture : fixture or live}
        {--case= : Optional case key}
        {--output= : Output directory}
        {--confirm-live-search : Required for live search}
        {--confirm-live-booking : Required for live booking}
        {--confirm-live-payment : Required for live payment}
        {--dry-run : Render requests only}';

    protected $description = 'Run ISA 24-case One API matrix (fixture mode executes fixture-backed services).';

    /** @var list<array{flow: string, id: string, test_case: string, key: string}> */
    private array $cases = [];

    public function __construct()
    {
        parent::__construct();
        $this->cases = OneApiMatrixCaseRegistry::cases();
    }

    public function handle(OneApiTestMatrixRunner $runner): int
    {
        $mode = (string) $this->option('mode');
        if ($mode === 'fixture') {
            \App\Support\OneApi\OneApiFixtureTransportScope::enable('matrix_command');
        }
        if ($mode === 'live') {
            $this->error('Live matrix requires explicit confirm flags and connection live capabilities.');

            return self::FAILURE;
        }

        $connectionId = (int) $this->option('connection');
        if ($connectionId <= 0) {
            $this->error('--connection is required for fixture matrix execution.');

            return self::FAILURE;
        }

        $connection = SupplierConnection::query()->find($connectionId);
        if ($connection === null) {
            $this->error('Connection not found.');

            return self::FAILURE;
        }

        $outputDir = (string) ($this->option('output') ?: storage_path('app/one-api-matrix'));
        File::ensureDirectoryExists($outputDir);

        $filter = (string) $this->option('case');
        $dryRun = (bool) $this->option('dry-run');
        $rows = [];
        $failures = 0;

        foreach ($this->cases as $case) {
            if ($filter !== '' && $case['key'] !== $filter) {
                continue;
            }
            $row = $runner->runCase($connection, $case, $dryRun);
            if (($row['result'] ?? '') === 'fail') {
                $failures++;
            }
            $rows[] = $row;
        }

        if ($rows === []) {
            $this->error('No matrix cases matched.');

            return self::FAILURE;
        }

        $csvPath = rtrim($outputDir, DIRECTORY_SEPARATOR).DIRECTORY_SEPARATOR.'one-api-matrix-'.now()->format('Ymd_His').'.csv';
        $handle = fopen($csvPath, 'w');
        if ($handle === false) {
            $this->error('Unable to write CSV.');

            return self::FAILURE;
        }
        fputcsv($handle, array_keys($rows[0]));
        foreach ($rows as $row) {
            fputcsv($handle, array_map(static function ($value): string {
                if (is_array($value)) {
                    return json_encode($value, JSON_THROW_ON_ERROR);
                }
                if (is_bool($value)) {
                    return $value ? 'true' : 'false';
                }

                return (string) $value;
            }, $row));
        }
        fclose($handle);

        $this->info('Matrix cases: '.count($rows));
        $this->info('Failures: '.$failures);
        $this->info('CSV: '.$csvPath);

        return $failures > 0 ? self::FAILURE : self::SUCCESS;
    }
}
