<?php

namespace App\Services\Communication;

use App\Enums\BookingCommunicationEvent;
use App\Enums\OtaNotificationEvent;
use App\Models\CommunicationLog;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

/**
 * Repairs customer communication log rows left in queued status after synchronous mail delivery.
 *
 * Only marks rows as sent when corroborating delivery evidence exists; otherwise flags manual review.
 */
class StaleSynchronousCommunicationLogRepairService
{
    private const EVIDENCE_WINDOW_MINUTES = 15;

    /**
     * @return array{
     *     outcome: 'repaired'|'needs_manual_review'|'skipped'|'not_eligible',
     *     message: string,
     *     evidence?: array<string, mixed>
     * }
     */
    public function repair(CommunicationLog $log, bool $apply = false): array
    {
        if (! $this->isEligibleStaleLog($log)) {
            return [
                'outcome' => 'not_eligible',
                'message' => 'Log is not an eligible stale synchronous customer email row.',
            ];
        }

        $evidence = $this->assessSentEvidence($log);
        if (($evidence['proven'] ?? false) !== true) {
            if ($apply) {
                $this->markNeedsManualReview($log, (string) ($evidence['reason'] ?? 'Insufficient delivery evidence.'));
            }

            return [
                'outcome' => 'needs_manual_review',
                'message' => (string) ($evidence['reason'] ?? 'Insufficient delivery evidence.'),
                'evidence' => $evidence,
            ];
        }

        if (! $apply) {
            return [
                'outcome' => 'repaired',
                'message' => 'Dry run: would mark log as sent using corroborating evidence.',
                'evidence' => $evidence,
            ];
        }

        $sentAt = $this->resolveSentAt($log, $evidence);
        $meta = is_array($log->meta) ? $log->meta : [];
        $meta['stale_sync_repair'] = 'repaired';
        $meta['stale_sync_repair_evidence'] = $evidence['source'] ?? null;
        $meta['stale_sync_repair_corroborating_log_id'] = $evidence['corroborating_log_id'] ?? null;

        $log->forceFill([
            'status' => 'sent',
            'sent_at' => $sentAt,
            'error_message' => null,
            'meta' => $meta,
        ])->save();

        return [
            'outcome' => 'repaired',
            'message' => 'Log marked sent using corroborating synchronous delivery evidence.',
            'evidence' => $evidence,
        ];
    }

    /**
     * @return array{proven: bool, reason?: string, source?: string, corroborating_log_id?: int}
     */
    public function assessSentEvidence(CommunicationLog $log): array
    {
        if ($log->booking_id === null) {
            return [
                'proven' => false,
                'reason' => 'Booking context is required to corroborate synchronous delivery.',
            ];
        }

        if ($log->event === BookingCommunicationEvent::BookingCancelled->value) {
            return $this->assessCancellationEvidence($log);
        }

        return [
            'proven' => false,
            'reason' => 'No automated corroboration rule exists for event '.$log->event.'.',
        ];
    }

    /**
     * @return array{proven: bool, reason?: string, source?: string, corroborating_log_id?: int}
     */
    protected function assessCancellationEvidence(CommunicationLog $log): array
    {
        $windowStart = $log->created_at?->copy()->subMinutes(self::EVIDENCE_WINDOW_MINUTES);
        $windowEnd = $log->created_at?->copy()->addMinutes(self::EVIDENCE_WINDOW_MINUTES);

        $corroborating = CommunicationLog::query()
            ->where('booking_id', $log->booking_id)
            ->where('channel', 'email')
            ->where('event', OtaNotificationEvent::BookingStatusChanged->value)
            ->where('status', 'sent')
            ->whereNotNull('sent_at')
            ->when($windowStart !== null, fn ($query) => $query->where('created_at', '>=', $windowStart))
            ->when($windowEnd !== null, fn ($query) => $query->where('created_at', '<=', $windowEnd))
            ->orderByDesc('id')
            ->get()
            ->first(function (CommunicationLog $candidate): bool {
                $meta = is_array($candidate->meta) ? $candidate->meta : [];
                $payload = is_array($meta['payload'] ?? null) ? $meta['payload'] : [];
                $statusLabel = strtolower((string) ($payload['status_label'] ?? $meta['status_label'] ?? ''));

                return in_array($statusLabel, ['cancelled', 'canceled'], true);
            });

        if ($corroborating === null) {
            return [
                'proven' => false,
                'reason' => 'No sent booking_status_changed cancellation operational email found within the evidence window.',
            ];
        }

        if ($this->hasPendingQueueJobForLog($log)) {
            return [
                'proven' => false,
                'reason' => 'A queue job still exists for this communication log; not treating as stale synchronous delivery.',
            ];
        }

        return [
            'proven' => true,
            'source' => 'booking_status_changed_operational_sent',
            'corroborating_log_id' => $corroborating->id,
        ];
    }

    protected function isEligibleStaleLog(CommunicationLog $log): bool
    {
        if ($log->channel !== 'email' || $log->status !== 'queued') {
            return false;
        }

        if ((string) config('queue.default') !== 'sync') {
            return false;
        }

        $meta = is_array($log->meta) ? $log->meta : [];

        return ($meta['recipient_type'] ?? null) === 'customer';
    }

    protected function hasPendingQueueJobForLog(CommunicationLog $log): bool
    {
        if (! $this->jobsTableExists()) {
            return false;
        }

        $needle = '"communicationLogId":'.$log->id;
        $legacyNeedle = 's:'.strlen((string) $log->id).':"'.$log->id.'"';

        return DB::table('jobs')
            ->where(function ($query) use ($needle, $legacyNeedle, $log): void {
                $query->where('payload', 'like', '%'.$needle.'%')
                    ->orWhere('payload', 'like', '%communication_log_id%'.$log->id.'%')
                    ->orWhere('payload', 'like', '%'.$legacyNeedle.'%');
            })
            ->exists();
    }

    protected function jobsTableExists(): bool
    {
        try {
            return DB::getSchemaBuilder()->hasTable('jobs');
        } catch (\Throwable) {
            return false;
        }
    }

    /**
     * @param  array<string, mixed>  $evidence
     */
    protected function resolveSentAt(CommunicationLog $log, array $evidence): Carbon
    {
        $corroboratingId = (int) ($evidence['corroborating_log_id'] ?? 0);
        if ($corroboratingId > 0) {
            $corroborating = CommunicationLog::query()->find($corroboratingId);
            if ($corroborating?->sent_at !== null) {
                return $corroborating->sent_at->copy();
            }
        }

        return $log->created_at?->copy() ?? now();
    }

    protected function markNeedsManualReview(CommunicationLog $log, string $reason): void
    {
        $meta = is_array($log->meta) ? $log->meta : [];
        $meta['stale_sync_repair'] = 'needs_manual_review';
        $meta['stale_sync_repair_reason'] = $reason;

        $log->forceFill([
            'error_message' => 'Stale synchronous mail log requires manual review: '.$reason,
            'meta' => $meta,
        ])->save();
    }
}
