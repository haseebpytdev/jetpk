<?php

namespace App\Console\Commands;

use App\Models\Booking;
use App\Support\Bookings\SabreControlledPnrFareChangeAcceptance;
use App\Support\Bookings\SabreOfferRefreshAcceptance;
use Illuminate\Console\Command;

/**
 * F9E: Record explicit operator fare-change acceptance for controlled Sabre PNR retry (meta only; no supplier HTTP).
 */
class SabreAcceptControlledPnrFareChangeCommand extends Command
{
    protected $signature = 'sabre:accept-controlled-pnr-fare-change
                            {--booking= : Booking ID}
                            {--reference= : Booking reference code}
                            {--reason= : Short acceptance reason (safe text)}
                            {--accepted-by= : Operator label (safe text)}
                            {--dry-run : Evaluate eligibility only — no DB mutation}
                            {--confirm= : Exact phrase ACCEPT-CONTROLLED-PNR-FARE-FOR-BOOKING-{id} to write meta}
                            {--json : Emit machine-readable lines only}';

    protected $description = 'Accept controlled Sabre PNR fare change gate (meta only; exact --confirm required to mutate)';

    public function handle(SabreControlledPnrFareChangeAcceptance $acceptance): int
    {
        $booking = $this->resolveBooking();
        if ($booking === null) {
            $this->emitPayload([
                'error' => ($this->option('booking') === null && $this->option('reference') === null)
                    ? 'missing_booking_option'
                    : 'booking_not_found',
                'db_mutation_attempted' => false,
                'acceptance_written' => false,
                'live_supplier_call_attempted' => false,
                'pnr_create_attempted' => false,
                'ticketing_attempted' => false,
                'cancellation_attempted' => false,
            ]);

            return self::FAILURE;
        }

        $dryRun = $this->option('dry-run') === true;
        $confirmProvided = $this->confirmPhraseMatches($booking);
        $eligibility = $acceptance->evaluateAcceptanceEligibility($booking);
        $reason = $acceptance->sanitizeReason((string) $this->option('reason'));
        $acceptedBy = $acceptance->sanitizeOperatorLabel((string) $this->option('accepted-by'));

        $classification = 'acceptance_evaluation';
        if ($dryRun) {
            $classification = 'acceptance_dry_run_only';
        } elseif (! $confirmProvided) {
            $classification = 'acceptance_blocked_missing_confirmation';
        } elseif (($eligibility['eligible'] ?? false) === true) {
            $classification = 'acceptance_ready_for_confirm';
        } else {
            $classification = 'acceptance_blocked_ineligible';
        }

        $payload = [
            'classification' => $classification,
            'booking_id' => $booking->id,
            'booking_reference' => (string) ($booking->reference_code ?? ''),
            'acceptance_eligible' => (bool) ($eligibility['eligible'] ?? false),
            'acceptance_blockers' => is_array($eligibility['blockers'] ?? null) ? array_values($eligibility['blockers']) : [],
            'controlled_pnr_manual_review_approved' => (bool) ($eligibility['controlled_pnr_manual_review_approved'] ?? false),
            'fare_change_gate_active' => (bool) ($eligibility['fare_change_gate_active'] ?? false),
            'safe_refresh_context_complete' => (bool) ($eligibility['safe_refresh_context_complete'] ?? false),
            'pricing_snapshot_present' => (bool) ($eligibility['pricing_snapshot_present'] ?? false),
            'certified_route_selection_present' => (bool) ($eligibility['certified_route_selection_present'] ?? false),
            'acceptance_reason' => $reason,
            'accepted_by' => $acceptedBy,
            'db_mutation_attempted' => false,
            'acceptance_written' => false,
            'live_supplier_call_attempted' => false,
            'pnr_create_attempted' => false,
            'ticketing_attempted' => false,
            'cancellation_attempted' => false,
        ];

        if ($dryRun || ! $confirmProvided || ! ($eligibility['eligible'] ?? false)) {
            if (! $dryRun && ! $confirmProvided) {
                $payload['blocked_message'] = 'Missing or invalid --confirm phrase. No booking meta mutation attempted.';
            } elseif (! $dryRun && ! ($eligibility['eligible'] ?? false)) {
                $payload['blocked_message'] = 'Fare-change acceptance eligibility gates blocked meta write.';
            }

            $this->emitPayload($payload);

            return $dryRun ? self::SUCCESS : self::FAILURE;
        }

        $record = $acceptance->buildAcceptanceRecord($booking, $reason, $acceptedBy);
        $meta = is_array($booking->meta) ? $booking->meta : [];
        $meta[SabreControlledPnrFareChangeAcceptance::META_KEY] = $record;
        $meta[SabreOfferRefreshAcceptance::META_ACCEPTED] = true;
        $meta[SabreOfferRefreshAcceptance::META_ACCEPTED_AT] = (string) ($record['accepted_at'] ?? now()->toIso8601String());
        $meta[SabreOfferRefreshAcceptance::META_ACCEPTED_BY] = (string) ($record['accepted_by'] ?? 'operator');
        $meta['offer_refresh_acceptance_source'] = SabreControlledPnrFareChangeAcceptance::ACCEPTANCE_SOURCE_ARTISAN;
        $booking->forceFill(['meta' => $meta]);
        $booking->save();

        $payload['classification'] = 'acceptance_written';
        $payload['db_mutation_attempted'] = true;
        $payload['acceptance_written'] = true;
        $payload['accepted_at'] = (string) ($record['accepted_at'] ?? '');
        $payload['accepted_for'] = (string) ($record['accepted_for'] ?? '');
        $payload['offer_refresh_accepted'] = true;

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
        $expected = 'ACCEPT-CONTROLLED-PNR-FARE-FOR-BOOKING-'.$booking->id;

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
