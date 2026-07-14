<?php

namespace App\Console\Commands;

use App\Services\TravelData\AirlineCanonicalResolver;
use App\Services\TravelData\AirlineCsvImportService;
use Illuminate\Console\Command;

class JetpkAirlineCodeAmbiguityAuditCommand extends Command
{
    protected $signature = 'jetpk:airline-code-ambiguity-audit {--source= : airlines.csv path}';

    protected $description = 'Read-only audit of duplicate IATA source groups and JetPK canonical airline resolution.';

    public function handle(
        AirlineCsvImportService $importer,
        AirlineCanonicalResolver $canonical,
    ): int {
        $source = (string) ($this->option('source') ?: storage_path('app/imports/kaggle/airports-global/airlines.csv'));
        $analysis = $importer->analyze($source);
        $failCount = 0;

        $this->line('JetPK airline code ambiguity audit');
        $this->line('source='.$source);
        $this->line('duplicate_iata_groups='.($analysis['source_metrics']['duplicate_iata_groups'] ?? 0));

        foreach ($analysis['duplicate_iata_groups'] ?? [] as $iata => $candidates) {
            $override = $canonical->overrideForIata((string) $iata);
            $pick = $canonical->pickSourceRow((string) $iata, array_map(static function (array $c): array {
                return [
                    'Name' => $c['name'] ?? '',
                    'IATA' => $c['iata'] ?? '',
                    'ICAO' => $c['icao'] ?? '',
                    'Country' => $c['country'] ?? '',
                    '_active' => (bool) ($c['active'] ?? false),
                ];
            }, $candidates));
            $chosenName = $override !== null
                ? (string) ($override['name'] ?? '')
                : (string) ($pick['row']['Name'] ?? 'UNRESOLVED');

            $this->line(sprintf(
                'IATA %s candidates=%d chosen=%s reason=%s',
                $iata,
                count($candidates),
                $chosenName,
                $pick['reason'],
            ));
            foreach ($candidates as $candidate) {
                $this->line(sprintf(
                    '  - name=%s icao=%s country=%s active=%s',
                    $candidate['name'] ?? '',
                    $candidate['icao'] ?? '',
                    $candidate['country'] ?? '',
                    ($candidate['active'] ?? false) ? 'Y' : 'N',
                ));
            }
        }

        foreach ($canonical->requiredJetpkCodes() as $code) {
            $expected = (string) ($canonical->overrideForIata($code)['name'] ?? '');
            $resolved = (string) ($canonical->canonicalDisplayName($code) ?? '');
            $db = $canonical->findDatabaseAirline($code);
            $dbName = (string) ($db?->name ?? '');
            $configPass = $resolved === $expected;
            $dbPass = $db === null || $dbName === $expected;
            $pass = $configPass && $dbPass;
            if (! $pass) {
                $failCount++;
            }
            $this->line(sprintf(
                '%s required=%s resolved=%s db=%s %s',
                $code,
                $expected,
                $resolved,
                $dbName !== '' ? $dbName : '(missing)',
                $pass ? 'PASS' : 'FAIL',
            ));
        }

        $this->line('fail_count='.$failCount);

        return $failCount === 0 ? self::SUCCESS : self::FAILURE;
    }
}
