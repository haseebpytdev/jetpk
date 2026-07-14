<?php

namespace App\Console\Commands;

use App\Models\Booking;
use App\Support\Bookings\SabreControlledPnrManualReviewApproval;
use Illuminate\Console\Command;

/**
 * F9C: Record explicit operator approval for controlled Sabre PNR burn-in (meta only; no supplier HTTP).
 */
class SabreApproveControlledPnrCommand extends Command
{
    protected $signature = 'sabre:approve-controlled-pnr
                            {--booking= : Booking ID}
                            {--reference= : Booking reference code}
                            {--reason= : Short approval reason (safe text)}
                            {--approved-by= : Operator label (safe text)}
                            {--dry-run : Evaluate eligibility only — no DB mutation}
                            {--confirm= : Exact phrase APPROVE-CONTROLLED-PNR-FOR-BOOKING-BOOKING_ID to write meta}
                            {--json : Emit machine-readable lines only}';

    protected $description = 'Approve controlled Sabre PNR manual review gate (meta only; exact --confirm required to mutate)';

    public function handle(SabreControlledPnrManualReviewApproval $approval): int
    {
        $booking = $this->resolveBooking();
        if ($booking === null) {
            $this->emitPayload([
                'error' => ($this->option('booking') === null && $this->option('reference') === null)
                    ? 'missing_booking_option'
                    : 'booking_not_found',
                'db_mutation_attempted' => false,
                'approval_written' => false,
                'live_supplier_call_attempted' => false,
                'pnr_create_attempted' => false,
                'ticketing_attempted' => false,
                'cancellation_attempted' => false,
            ]);

            return self::FAILURE;
        }

        $dryRun = $this->option('dry-run') === true;
        $confirmProvided = $this->confirmPhraseMatches($booking);
        $eligibility = $approval->evaluateApprovalEligibility($booking);
        $reason = $approval->sanitizeReason((string) $this->option('reason'));
        $approvedBy = $approval->sanitizeOperatorLabel((string) $this->option('approved-by'));

        $classification = 'approval_evaluation';
        if ($dryRun) {
            $classification = 'approval_dry_run_only';
        } elseif (! $confirmProvided) {
            $classification = 'approval_blocked_missing_confirmation';
        } elseif (($eligibility['eligible'] ?? false) === true) {
            $classification = 'approval_ready_for_confirm';
        } else {
            $classification = 'approval_blocked_ineligible';
        }

        $payload = [
            'classification' => $classification,
            'booking_id' => $booking->id,
            'booking_reference' => (string) ($booking->reference_code ?? ''),
            'approval_eligible' => (bool) ($eligibility['eligible'] ?? false),
            'approval_blockers' => is_array($eligibility['blockers'] ?? null) ? array_values($eligibility['blockers']) : [],
            'has_usable_controlled_pnr_context' => (bool) ($eligibility['has_usable_controlled_pnr_context'] ?? false),
            'safe_refresh_context_complete' => (bool) ($eligibility['safe_refresh_context_complete'] ?? false),
            'pricing_snapshot_present' => (bool) ($eligibility['pricing_snapshot_present'] ?? false),
            'certified_route_selection_present' => (bool) ($eligibility['certified_route_selection_present'] ?? false),
            'approval_reason' => $reason,
            'approved_by' => $approvedBy,
            'db_mutation_attempted' => false,
            'approval_written' => false,
            'live_supplier_call_attempted' => false,
            'pnr_create_attempted' => false,
            'ticketing_attempted' => false,
            'cancellation_attempted' => false,
        ];

        if ($dryRun || ! $confirmProvided || ! ($eligibility['eligible'] ?? false)) {
            if (! $dryRun && ! $confirmProvided) {
                $payload['blocked_message'] = 'Missing or invalid --confirm phrase. No booking meta mutation attempted.';
            } elseif (! $dryRun && ! ($eligibility['eligible'] ?? false)) {
                $payload['blocked_message'] = 'Approval eligibility gates blocked meta write.';
            }

            $this->emitPayload($payload);

            return $dryRun ? self::SUCCESS : self::FAILURE;
        }

        $record = $approval->buildApprovalRecord($booking, $reason, $approvedBy);
        $meta = is_array($booking->meta) ? $booking->meta : [];
        $meta[SabreControlledPnrManualReviewApproval::META_KEY] = $record;
        $booking->forceFill(['meta' => $meta]);
        $booking->save();

        $payload['classification'] = 'approval_written';
        $payload['db_mutation_attempted'] = true;
        $payload['approval_written'] = true;
        $payload['approved_at'] = (string) ($record['approved_at'] ?? '');
        $payload['approved_for'] = (string) ($record['approved_for'] ?? '');

        $this->emitPayload($payload);

        return self::SUCCESS;
    }

    protected function resolveBooking(): ?Booking
    {
        $bookingId = $this->option('booking');
        if ($bookingId !== null && $bookingId !== '' && is_numeric($bookingId)) {
            return Booking::query()->find((int) $bookingId);
        }

        $reference = trim((string) $this->option('reference'));
        if ($reference !== '') {
            return Booking::query()->where('reference_code', $reference)->first();
        }

        return null;
    }

    protected function confirmPhraseMatches(Booking $booking): bool
    {
        $expected = 'APPROVE-CONTROLLED-PNR-FOR-BOOKING-'.$booking->id;

        return trim((string) $this->option('confirm')) === $expected;
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    protected function emitPayload(array $payload): void
    {
        if ((bool) $this->option('json')) {
            $this->line(json_encode($payload, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE));

            return;
        }

        foreach ($payload as $key => $value) {
            if (is_bool($value)) {
                $this->line($key.'='.($value ? 'true' : 'false'));
            } elseif (is_array($value)) {
                $this->line($key.'='.json_encode($value, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE));
            } elseif ($value === null || $value === '') {
                $this->line($key.'=');
            } else {
                $this->line($key.'='.$value);
            }
        }
    }
}
