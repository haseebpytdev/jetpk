<?php

namespace App\Console\Commands;

use App\Models\SupplierConnection;
use App\Support\Suppliers\EmptySupplierConnectionPlaceholderInspector;
use Illuminate\Console\Command;

class SuppliersCleanupEmptyPlaceholdersCommand extends Command
{
    protected $signature = 'suppliers:cleanup-empty-placeholders
                            {--dry-run : List removable placeholder rows without deleting}
                            {--execute : Delete rows matching strict empty-placeholder criteria}';

    protected $description = 'List or remove empty foundation-seeder supplier connection placeholders (never auto-runs).';

    public function handle(EmptySupplierConnectionPlaceholderInspector $inspector): int
    {
        $dryRun = (bool) $this->option('dry-run');
        $execute = (bool) $this->option('execute');

        if (! $dryRun && ! $execute) {
            $this->error('Specify --dry-run or --execute.');

            return self::FAILURE;
        }

        if ($dryRun && $execute) {
            $this->error('Use only one of --dry-run or --execute.');

            return self::FAILURE;
        }

        $candidates = SupplierConnection::query()
            ->orderBy('id')
            ->get()
            ->filter(fn (SupplierConnection $connection): bool => $inspector->isRemovablePlaceholder($connection));

        if ($candidates->isEmpty()) {
            $this->info('No empty supplier connection placeholders matched strict criteria.');

            return self::SUCCESS;
        }

        $this->line('Matched '.$candidates->count().' empty placeholder row(s):');

        foreach ($candidates as $connection) {
            $this->line(sprintf(
                '  id=%d agency_id=%s provider=%s name=%s environment=%s',
                $connection->id,
                (string) $connection->agency_id,
                $connection->provider?->value ?? '',
                (string) $connection->name,
                $connection->environment?->value ?? '',
            ));
        }

        if ($dryRun) {
            $this->info('Dry run only — no rows deleted.');

            return self::SUCCESS;
        }

        $deleted = 0;
        foreach ($candidates as $connection) {
            $connection->delete();
            $deleted++;
        }

        $this->info('Deleted '.$deleted.' empty placeholder supplier connection row(s).');

        return self::SUCCESS;
    }
}
