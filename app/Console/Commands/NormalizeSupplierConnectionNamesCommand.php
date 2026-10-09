<?php

namespace App\Console\Commands;

use App\Models\SupplierConnection;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

/**
 * Normalize legacy SupplierConnection display names (metadata only; no supplier calls).
 */
class NormalizeSupplierConnectionNamesCommand extends Command
{
    protected $signature = 'supplier:normalize-connection-names
        {--execute : Apply updates (default is dry-run)}';

    protected $description = 'Normalize preserved legacy supplier connection names to canonical labels';

    /** @var array<string, string> */
    public const LEGACY_TO_CANONICAL = [
        'JPak Group' => 'JPAK Group',
        'JEtPK Binham Sabre' => 'JetPK Binham Sabre',
        'sabre-sandbox-qa' => 'Sabre Sandbox QA (CERT)',
    ];

    public function handle(): int
    {
        $execute = (bool) $this->option('execute');
        $candidates = [];

        foreach (SupplierConnection::query()->orderBy('id')->get() as $connection) {
            $current = trim((string) $connection->name);
            $target = self::LEGACY_TO_CANONICAL[$current] ?? null;
            if ($target === null || $target === $current) {
                continue;
            }
            $candidates[] = ['id' => $connection->id, 'from' => $current, 'to' => $target];
        }

        $this->line('NORMALIZE_CANDIDATES='.count($candidates));
        $this->line('MODE='.($execute ? 'execute' : 'dry-run'));

        foreach ($candidates as $row) {
            $this->line(sprintf('RENAME id=%d from=%s to=%s', $row['id'], $row['from'], $row['to']));
        }

        if (! $execute || $candidates === []) {
            $this->info('Dry-run complete. Pass --execute to apply renames.');

            return self::SUCCESS;
        }

        $updated = 0;
        DB::transaction(function () use ($candidates, &$updated): void {
            foreach ($candidates as $row) {
                $connection = SupplierConnection::query()->lockForUpdate()->find($row['id']);
                if ($connection === null) {
                    continue;
                }
                if (trim((string) $connection->name) !== $row['from']) {
                    continue;
                }
                $connection->name = $row['to'];
                $connection->save();
                $updated++;
            }
        });

        $this->line('NORMALIZE_APPLIED='.$updated);

        return self::SUCCESS;
    }
}
