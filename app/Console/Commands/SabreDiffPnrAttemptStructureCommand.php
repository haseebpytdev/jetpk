<?php

namespace App\Console\Commands;

use App\Services\Suppliers\Sabre\SabreInspectGate;
use App\Support\Sabre\SabrePnrAttemptStructureDiff;
use Illuminate\Console\Command;

/**
 * Read-only safe structural diff between failed and successful Sabre create_pnr attempts.
 */
class SabreDiffPnrAttemptStructureCommand extends Command
{
    public const CONFIRM_PHRASE = 'READONLY-SABRE-PNR-STRUCTURE-DIFF';

    protected $signature = 'sabre:diff-pnr-attempt-structure
                            {--failed= : Failed supplier_booking_attempt ID}
                            {--success= : Comma-separated successful attempt IDs}
                            {--confirm= : Production: READONLY-SABRE-PNR-STRUCTURE-DIFF}';

    protected $description = '[read-only] Compare safe Passenger Records structural snapshots (no raw payload / PII)';

    public function handle(SabrePnrAttemptStructureDiff $diff): int
    {
        if (! SabreInspectGate::allowed() && (string) config('app.env', 'production') === 'production') {
            $confirm = trim((string) $this->option('confirm'));
            if ($confirm !== self::CONFIRM_PHRASE) {
                $this->components->error('--confirm='.self::CONFIRM_PHRASE.' required on production.');

                return self::FAILURE;
            }
        }

        $failed = $this->option('failed');
        if ($failed === null || ! is_numeric($failed)) {
            $this->components->error('Pass --failed={supplier_booking_attempt_id}.');

            return self::FAILURE;
        }

        $successRaw = trim((string) ($this->option('success') ?? ''));
        if ($successRaw === '') {
            $this->components->error('Pass --success=159,138,128 (comma-separated successful attempt IDs).');

            return self::FAILURE;
        }

        $successIds = array_values(array_filter(array_map(
            static fn (string $part): int => (int) trim($part),
            explode(',', $successRaw),
        ), static fn (int $id): bool => $id > 0));

        if ($successIds === []) {
            $this->components->error('No valid success attempt IDs provided.');

            return self::FAILURE;
        }

        $this->line('Classification: READ-ONLY');
        $this->line('live_supplier_call_attempted=false');
        $this->line('ticketing_attempted=false');
        $this->line('airticket_attempted=false');

        $report = $diff->compare((int) $failed, $successIds);
        $this->line('failed_attempt_id='.(int) ($report['failed_attempt_id'] ?? 0));
        $this->line('success_attempt_ids='.json_encode($report['success_attempt_ids'] ?? [], JSON_UNESCAPED_SLASHES));

        $failedRow = is_array($report['failed'] ?? null) ? $report['failed'] : [];
        $this->line('failed_structure_snapshot_source='.(string) ($failedRow['structure_snapshot_source'] ?? 'unknown'));
        $this->line('failed_endpoint_path='.(string) data_get($failedRow, 'safe_request_structure.endpoint_path'));
        $this->line('failed_payload_schema='.(string) data_get($failedRow, 'safe_request_structure.payload_schema'));

        $highlights = is_array($report['highlighted_differences'] ?? null) ? $report['highlighted_differences'] : [];
        if ($highlights !== []) {
            $this->line('highlighted_differences='.json_encode($highlights, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE));
        }

        $fieldDiff = is_array($report['field_diff'] ?? null) ? $report['field_diff'] : [];
        $this->line('field_diff_comparable='.(($fieldDiff['comparable'] ?? false) ? 'true' : 'false'));
        $this->line('field_diff_count='.(int) ($fieldDiff['diff_count'] ?? 0));
        if (is_array($fieldDiff['diffs'] ?? null) && $fieldDiff['diffs'] !== []) {
            $this->line('field_diffs='.json_encode($fieldDiff['diffs'], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE));
        }

        return self::SUCCESS;
    }
}
