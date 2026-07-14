<?php

namespace App\Console\Commands;

use App\Models\AgencyMessageTemplate;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

/**
 * Restore agency_message_templates rows from a jetpk:export-email-event-content-backup JSON export.
 */
class RestoreJetpkEmailEventContentBackupCommand extends Command
{
    protected $signature = 'jetpk:restore-email-event-content-backup
                            {--path= : Backup JSON path}
                            {--dry-run : Report without writing}';

    protected $description = 'Restore email template rows from a backup JSON export (idempotent)';

    public function handle(): int
    {
        $path = (string) ($this->option('path') ?: '');
        $dryRun = (bool) $this->option('dry-run');

        if ($path === '' || ! is_file($path)) {
            $this->error('Backup file not found. Use --path=storage/app/audits/jetpk-universal-email/email-event-content-backup-pre-migration.json');

            return self::FAILURE;
        }

        $payload = json_decode((string) file_get_contents($path), true);
        if (! is_array($payload) || ! isset($payload['rows']) || ! is_array($payload['rows'])) {
            $this->error('Invalid backup JSON structure.');

            return self::FAILURE;
        }

        $inserted = 0;
        $updated = 0;
        $skipped = 0;
        $conflicts = 0;

        foreach ($payload['rows'] as $row) {
            if (! is_array($row)) {
                $conflicts++;
                continue;
            }

            $id = $row['id'] ?? null;
            $agencyId = $row['agency_id'] ?? null;
            $event = $row['event'] ?? null;
            $channel = $row['channel'] ?? 'email';

            if (! is_numeric($id) || ! is_numeric($agencyId) || ! is_string($event) || $event === '') {
                $conflicts++;
                $this->line("conflict invalid-row id={$id} agency={$agencyId} event={$event}");
                continue;
            }

            $template = AgencyMessageTemplate::query()->find((int) $id);
            if ($template === null) {
                $rowData = [
                    'agency_id' => (int) $agencyId,
                    'event' => $event,
                    'channel' => $channel,
                    'subject' => $row['subject'] ?? null,
                    'body' => $row['body'] ?? null,
                    'is_enabled' => (bool) ($row['is_enabled'] ?? true),
                    'variables' => isset($row['variables']) ? json_encode($row['variables']) : null,
                    'meta' => isset($row['meta']) ? json_encode($row['meta']) : null,
                    'created_at' => $row['created_at'] ?? now()->toIso8601String(),
                    'updated_at' => $row['updated_at'] ?? now()->toIso8601String(),
                ];

                if ($dryRun) {
                    $inserted++;
                    $this->line("would-insert id={$id} agency={$agencyId} event={$event}");
                    continue;
                }

                DB::table('agency_message_templates')->updateOrInsert(['id' => (int) $id], $rowData);
                $inserted++;
                $this->line("inserted id={$id} agency={$agencyId} event={$event}");
                continue;
            }

            if ((int) $template->agency_id !== (int) $agencyId || $template->event !== $event || $template->channel !== $channel) {
                $conflicts++;
                $this->line("conflict id-mismatch id={$id} live_agency={$template->agency_id} backup_agency={$agencyId} live_event={$template->event} backup_event={$event}");
                continue;
            }

            $restore = [
                'subject' => $row['subject'] ?? null,
                'body' => $row['body'] ?? null,
                'is_enabled' => (bool) ($row['is_enabled'] ?? true),
                'variables' => $row['variables'] ?? null,
                'meta' => $row['meta'] ?? null,
            ];

            $unchanged = $template->subject === $restore['subject']
                && $template->body === $restore['body']
                && (bool) $template->is_enabled === $restore['is_enabled']
                && $template->variables === $restore['variables']
                && $template->meta === $restore['meta'];

            if ($unchanged) {
                $skipped++;
                continue;
            }

            if ($dryRun) {
                $updated++;
                $this->line("would-update id={$id} agency={$agencyId} event={$event}");
                continue;
            }

            $template->fill($restore);
            $template->save();
            $updated++;
            $this->line("updated id={$id} agency={$agencyId} event={$event}");
        }

        $this->table(['metric', 'count'], [
            ['rows_in_backup', count($payload['rows'])],
            ['inserted', $inserted],
            ['updated', $updated],
            ['skipped', $skipped],
            ['conflicts', $conflicts],
        ]);
        $this->info('idempotent=yes (skips unchanged rows; validates agency/event/channel on existing IDs)');
        $this->info('dry_run='.($dryRun ? '1' : '0'));

        return $conflicts > 0 ? self::FAILURE : self::SUCCESS;
    }
}
