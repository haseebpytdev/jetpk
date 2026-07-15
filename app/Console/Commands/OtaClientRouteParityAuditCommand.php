<?php

namespace App\Console\Commands;

use App\Support\Audits\BookingFlowSmokeSafetyOutput;
use App\Support\Audits\ClientRouteParityAuditService;
use Illuminate\Console\Command;

class OtaClientRouteParityAuditCommand extends Command
{
    protected $signature = 'ota:client-route-parity-audit
                            {--client=haseeb-master : Default deployment client slug}
                            {--target=jetpk : Example non-default client slug for suggested prefixed URIs}
                            {--fail-on-high-risk : Exit 1 when high-risk routes are marked prefixable}
                            {--export-dir= : Export directory (default storage/app/audits)}';

    protected $description = 'MC-7A read-only client route/page parity audit — scan all routes and export parity matrix';

    public function handle(ClientRouteParityAuditService $auditService): int
    {
        $clientSlug = trim((string) $this->option('client'));
        $targetSlug = trim((string) $this->option('target'));

        if ($clientSlug === '') {
            $this->error('Option --client must not be empty.');

            return self::FAILURE;
        }

        if ($targetSlug === '') {
            $this->error('Option --target must not be empty.');

            return self::FAILURE;
        }

        foreach (BookingFlowSmokeSafetyOutput::readOnlyBanner() as $line) {
            $this->line($line);
        }
        $this->line('Classification: READ-ONLY client route parity audit (MC-7A).');
        $this->line('db_write_attempted=false');
        $this->newLine();

        $exportDir = $this->option('export-dir');
        if (! is_string($exportDir) || $exportDir === '') {
            $exportDir = storage_path('app/audits');
        } elseif (! str_starts_with($exportDir, DIRECTORY_SEPARATOR) && ! preg_match('/^[A-Za-z]:[\\\\\\/]/', $exportDir)) {
            $exportDir = base_path($exportDir);
        }

        $result = $auditService->run($clientSlug, $targetSlug, $exportDir);
        $summary = $result['summary'];
        $rows = $result['rows'];

        $prefixableRows = array_values(array_filter(
            $rows,
            static fn (array $row): bool => $row['should_have_client_prefix'] === 'yes',
        ));

        $this->info(sprintf(
            'Audit summary: client=%s target=%s total=%d prefixable=%d high_risk=%d conflicts=%d',
            $clientSlug,
            $targetSlug,
            $summary['total_rows'],
            $summary['prefixable_yes'],
            $summary['high_risk'],
            $summary['high_risk_prefixable_conflicts'],
        ));

        $this->newLine();
        $this->comment('Top classifications:');
        $topClassifications = array_slice($summary['by_classification'], 0, 8, true);
        foreach ($topClassifications as $classification => $count) {
            $this->line(sprintf('  %s: %d', $classification, $count));
        }

        $this->newLine();
        $this->info('Sample prefixable routes (first 25):');
        $this->table(
            ['route name', 'method', 'URI', 'classification', 'suggested URI', 'risk'],
            array_map(static fn (array $row): array => [
                $row['route_name'],
                $row['method'],
                $row['uri'],
                $row['classification'],
                $row['suggested_prefixed_uri'],
                $row['risk_level'],
            ], array_slice($prefixableRows, 0, 25)),
        );

        $this->newLine();
        $this->info('JSON export: '.$result['json_path']);
        $this->info('Markdown export: '.$result['md_path']);

        if ($this->option('fail-on-high-risk') && $summary['high_risk_prefixable_conflicts'] > 0) {
            $this->error(sprintf(
                'High-risk prefixable conflicts detected: %d — review export before MC-7B.',
                $summary['high_risk_prefixable_conflicts'],
            ));

            return self::FAILURE;
        }

        $this->info('Client route parity audit completed.');

        return self::SUCCESS;
    }
}
