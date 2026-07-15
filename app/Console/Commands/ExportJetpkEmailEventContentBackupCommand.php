<?php

namespace App\Console\Commands;

use App\Models\AgencyMessageTemplate;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\File;

/**
 * Export agency_message_templates rows before event-content migration (rollback aid).
 *
 *   php artisan jetpk:export-email-event-content-backup
 */
class ExportJetpkEmailEventContentBackupCommand extends Command
{
    protected $signature = 'jetpk:export-email-event-content-backup {--agency=} {--path=}';

    protected $description = 'Export email template rows to JSON for rollback before event-content migration';

    public function handle(): int
    {
        $agencyId = $this->option('agency');
        $path = $this->option('path')
            ?: storage_path('app/audits/jetpk-universal-email/email-event-content-backup-'.now()->format('Ymd-His').'.json');

        $query = AgencyMessageTemplate::query()->where('channel', 'email');
        if ($agencyId !== null && $agencyId !== '') {
            $query->where('agency_id', $agencyId);
        }

        $rows = $query->get()->map(fn (AgencyMessageTemplate $t) => [
            'id' => $t->id,
            'agency_id' => $t->agency_id,
            'event' => $t->event,
            'channel' => $t->channel,
            'subject' => $t->subject,
            'body' => $t->body,
            'is_enabled' => $t->is_enabled,
            'variables' => $t->variables,
            'meta' => $t->meta,
            'created_at' => $t->created_at?->toIso8601String(),
            'updated_at' => $t->updated_at?->toIso8601String(),
        ])->values()->all();

        File::ensureDirectoryExists(dirname($path));
        File::put($path, json_encode([
            'exported_at' => now()->toIso8601String(),
            'agency_filter' => $agencyId,
            'row_count' => count($rows),
            'rows' => $rows,
        ], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));

        $this->info('path='.$path.' rows='.count($rows));

        return self::SUCCESS;
    }
}
