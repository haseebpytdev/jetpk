<?php

namespace App\Console\Commands;

use App\Models\AgencyMessageTemplate;
use App\Support\Emails\JetpkEmailEventContentRegistry;
use Illuminate\Console\Command;

/**
 * Migrates legacy agency_message_templates rows into jetpk_event_content meta overrides.
 */
class MigrateJetpkEmailEventContentCommand extends Command
{
    protected $signature = 'jetpk:migrate-email-event-content {--dry-run : Report without writing} {--agency=}';

    protected $description = 'Migrate saved email templates into JetPK event-content overrides';

    public function handle(): int
    {
        $dryRun = (bool) $this->option('dry-run');
        $agencyId = $this->option('agency');

        $query = AgencyMessageTemplate::query()->where('channel', 'email');
        if ($agencyId !== null && $agencyId !== '') {
            $query->where('agency_id', $agencyId);
        }

        $discovered = 0;
        $convertible = 0;
        $migrated = 0;
        $skipped = 0;
        $alreadyMigrated = 0;
        $noChanges = 0;
        $unknownEvent = 0;

        foreach ($query->cursor() as $template) {
            $discovered++;

            if (JetpkEmailEventContentRegistry::find($template->event) === null) {
                $unknownEvent++;
                $skipped++;
                $this->line("skip unknown-event event={$template->event} agency={$template->agency_id}");
                continue;
            }

            $meta = is_array($template->meta) ? $template->meta : [];
            if (isset($meta['jetpk_event_content']) && is_array($meta['jetpk_event_content'])) {
                $alreadyMigrated++;
                $skipped++;
                continue;
            }

            $payload = JetpkEmailEventContentRegistry::migrateLegacyTemplate($template);
            if (count($payload) <= 1) {
                $noChanges++;
                $skipped++;
                continue;
            }

            $convertible++;
            $meta['jetpk_event_content'] = $payload;
            $meta['full_html_override_enabled'] = false;

            if (! $dryRun) {
                $template->meta = $meta;
                $template->save();
            }

            $migrated++;
            $this->line("migrated event={$template->event} agency={$template->agency_id}");
        }

        $this->table(['metric', 'count'], [
            ['discovered', $discovered],
            ['convertible', $convertible],
            ['migrated', $migrated],
            ['already_migrated', $alreadyMigrated],
            ['no_changes', $noChanges],
            ['unknown_event', $unknownEvent],
            ['skipped_total', $skipped],
        ]);
        $this->info('idempotent=yes (skips rows with existing meta.jetpk_event_content)');
        $this->info('rollback=php artisan jetpk:restore-email-event-content-backup --path=...');
        $this->info('dry_run='.($dryRun ? '1' : '0'));

        return self::SUCCESS;
    }
}
