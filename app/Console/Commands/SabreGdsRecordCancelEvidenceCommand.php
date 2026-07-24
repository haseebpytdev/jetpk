<?php

namespace App\Console\Commands;

use App\Models\Booking;
use App\Services\Suppliers\Sabre\Cancel\SabreGdsControlledCancelEvidenceService;
use Illuminate\Console\Command;

/**
 * Record confirmed Sabre GDS cancellation evidence for legacy inspect runs (no supplier HTTP mutations).
 */
class SabreGdsRecordCancelEvidenceCommand extends Command
{
    protected $signature = 'sabre:gds-record-cancel-evidence
                            {--booking= : Booking ID}
                            {--classification= : Confirmed cancel_outcome_classification}
                            {--confirm= : Must be RECORD-CONFIRMED-SABRE-GDS-CANCEL-EVIDENCE}
                            {--verify : Optional read-only getBooking verification before recording}';

    protected $description = 'Record controlled Sabre GDS confirmed-cancellation evidence (no live cancel mutations)';

    public function handle(SabreGdsControlledCancelEvidenceService $service): int
    {
        $bookingId = $this->option('booking');
        if ($bookingId === null || ! is_numeric($bookingId)) {
            $this->error('--booking is required.');

            return self::FAILURE;
        }

        $classification = trim((string) $this->option('classification'));
        if ($classification === '') {
            $this->error('--classification is required.');

            return self::FAILURE;
        }

        if (trim((string) $this->option('confirm')) !== SabreGdsControlledCancelEvidenceService::CONFIRM_PHRASE) {
            $this->error('--confirm must be '.SabreGdsControlledCancelEvidenceService::CONFIRM_PHRASE);

            return self::FAILURE;
        }

        $booking = Booking::query()->with('tickets')->find((int) $bookingId);
        if ($booking === null) {
            $this->error('Booking not found.');

            return self::FAILURE;
        }

        $result = $service->recordEvidence(
            $booking,
            $classification,
            $this->option('verify') === true,
            null,
            ['source' => 'sabre_gds_record_cancel_evidence_command'],
        );

        $this->line(json_encode($result, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));

        return ($result['success'] ?? false) === true ? self::SUCCESS : self::FAILURE;
    }
}
