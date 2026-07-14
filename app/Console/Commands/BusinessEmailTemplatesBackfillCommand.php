<?php

namespace App\Console\Commands;

use App\Models\Agency;
use App\Models\AgencyMessageTemplate;
use App\Services\Communication\AgencyMessageTemplateSeeder;
use App\Support\Emails\OperationalEmailDefaults;
use Illuminate\Console\Command;
use Illuminate\Database\Eloquent\Builder;

class BusinessEmailTemplatesBackfillCommand extends Command
{
    protected $signature = 'ota:backfill-business-email-templates
                            {--dry-run : Preview rows to create or update without writing}
                            {--force : Overwrite existing template subject/body with defaults (use with caution)}
                            {--agency= : Limit to one agency ID}';

    protected $description = 'Backfill business operational email templates into agency_message_templates (K2D-B3)';

    public function handle(AgencyMessageTemplateSeeder $seeder): int
    {
        $dryRun = (bool) $this->option('dry-run');
        $force = (bool) $this->option('force');
        $agencyFilter = $this->option('agency') !== null ? (int) $this->option('agency') : null;

        if ($dryRun) {
            $this->info('Dry run — no database changes will be made.');
        } elseif ($force) {
            $this->warn('Force mode — existing business operational template copy will be overwritten.');
        } else {
            $this->info('Creating missing business operational template rows only (existing rows are preserved).');
        }

        $stats = [
            'agencies' => 0,
            'created' => 0,
            'updated' => 0,
            'skipped_existing' => 0,
            'skipped_no_defaults' => 0,
        ];

        $query = Agency::query()
            ->when($agencyFilter !== null, fn (Builder $q): Builder => $q->whereKey($agencyFilter))
            ->orderBy('id');

        $query->chunkById(50, function ($agencies) use ($dryRun, $force, $seeder, &$stats): void {
            foreach ($agencies as $agency) {
                $stats['agencies']++;

                foreach (OperationalEmailDefaults::BUSINESS_OPERATIONAL_EVENT_KEYS as $eventKey) {
                    $defaults = OperationalEmailDefaults::forEvent($eventKey);
                    if ($defaults === null) {
                        continue;
                    }

                    $existing = AgencyMessageTemplate::query()
                        ->where('agency_id', $agency->id)
                        ->where('event', $eventKey)
                        ->where('channel', 'email')
                        ->first();

                    $wouldSkip = $existing !== null && ! $force;
                    if (! $wouldSkip) {
                        $action = $existing === null ? 'create' : 'update';
                        $this->line(sprintf(
                            '[%s] agency #%d (%s) event=%s → %s',
                            $dryRun ? 'dry-run' : $action,
                            $agency->id,
                            $agency->slug ?? $agency->name,
                            $eventKey,
                            $action,
                        ));
                    }
                }

                $agencyStats = $seeder->seedBusinessDefaultsForAgency($agency, $force, $dryRun);
                $stats['created'] += $agencyStats['created'];
                $stats['updated'] += $agencyStats['updated'];
                $stats['skipped_existing'] += $agencyStats['skipped_existing'];
                $stats['skipped_no_defaults'] += $agencyStats['skipped_no_defaults'];
            }
        });

        $this->newLine();
        $this->table(
            ['Metric', 'Count'],
            collect($stats)->map(fn (int $count, string $metric): array => [
                str_replace('_', ' ', ucfirst($metric)),
                (string) $count,
            ])->values()->all(),
        );

        if ($dryRun && ($stats['created'] + $stats['updated']) > 0) {
            $this->comment('Re-run without --dry-run to apply these changes.');
        }

        return self::SUCCESS;
    }
}
