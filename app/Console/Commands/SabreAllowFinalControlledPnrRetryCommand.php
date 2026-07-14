<?php

namespace App\Console\Commands;

use App\Models\Booking;
use App\Support\Bookings\SabreControlledFinalPnrRetryAllowanceGate;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Schema;

/**
 * F9Q: Record explicit one-shot controlled PNR retry allowance after F9P final readiness is green (meta only; no supplier HTTP).
 */
class SabreAllowFinalControlledPnrRetryCommand extends Command
{
    protected $signature = 'sabre:allow-final-controlled-pnr-retry
                            {--booking= : Booking ID}
                            {--reference= : Booking reference code}
                            {--dry-run : Evaluate eligibility only — no DB mutation}
                            {--confirm= : Exact phrase ALLOW-FINAL-CONTROLLED-PNR-RETRY-FOR-BOOKING-{id} to write meta}
                            {--json : Emit machine-readable lines only}';

    protected $description = 'Allow one-shot controlled Sabre PNR retry after F9P final readiness (meta only; exact --confirm required to mutate)';

    public function handle(SabreControlledFinalPnrRetryAllowanceGate $gate): int
    {
        $booking = $this->resolveBooking();
        if ($booking === null) {
            $this->emitPayload([
                'error' => ($this->option('booking') === null && $this->option('reference') === null)
                    ? 'missing_booking_option'
                    : 'booking_not_found',
                'db_mutation_attempted' => false,
                'allowance_written' => false,
                'live_supplier_call_attempted' => false,
                'pnr_create_attempted' => false,
                'ticketing_attempted' => false,
                'cancellation_attempted' => false,
            ]);

            return self::FAILURE;
        }

        $dryRun = $this->option('dry-run') === true;
        $confirmProvided = $this->confirmPhraseMatches($booking);
        $productionBlocked = $this->productionRequiresConfirm() && ! $dryRun && ! $confirmProvided;
        $eligibility = $gate->evaluateAllowanceEligibility($booking, ! $dryRun, $confirmProvided);

        $classification = 'allowance_evaluation';
        if ($dryRun) {
            $classification = 'allowance_dry_run_only';
        } elseif ($productionBlocked) {
            $classification = 'allowance_blocked_missing_confirmation';
        } elseif (! ($eligibility['eligible'] ?? false)) {
            $classification = 'allowance_blocked_ineligible';
        } else {
            $classification = 'allowance_ready_for_confirm';
        }

        $payload = [
            'classification' => $classification,
            'booking_id' => $booking->id,
            'booking_reference' => (string) ($booking->reference_code ?? $booking->booking_reference ?? ''),
            'allowance_eligible' => (bool) ($eligibility['eligible'] ?? false),
            'allowance_blockers' => is_array($eligibility['blockers'] ?? null) ? array_values($eligibility['blockers']) : [],
            'final_pnr_retry_ready' => (bool) ($eligibility['final_pnr_retry_ready'] ?? false),
            'final_freshness_ready' => (bool) ($eligibility['final_freshness_ready'] ?? false),
            'final_pnr_retry_blockers' => is_array($eligibility['final_pnr_retry_blockers'] ?? null)
                ? array_values($eligibility['final_pnr_retry_blockers'])
                : [],
            'existing_retry_allowances_consumed' => (bool) ($eligibility['existing_retry_allowances_consumed'] ?? false),
            'exact_allow_confirm_phrase' => SabreControlledFinalPnrRetryAllowanceGate::confirmPhraseForBooking($booking),
            'exact_create_confirm_phrase' => SabreControlledFinalPnrRetryAllowanceGate::createConfirmPhraseForBooking($booking),
            'recommended_controlled_create_command' => 'php artisan sabre:controlled-create-pnr --booking='.$booking->id.' --dry-run',
            'db_mutation_attempted' => false,
            'allowance_written' => false,
            'live_supplier_call_attempted' => false,
            'pnr_create_attempted' => false,
            'ticketing_attempted' => false,
            'cancellation_attempted' => false,
        ];

        if (in_array('final_freshness_expired', $payload['allowance_blockers'], true)
            || in_array('final_pnr_retry_not_ready', $payload['allowance_blockers'], true)) {
            $payload['recommended_freshness_rerun'] = 'Re-run sabre:controlled-apply-fresh-pnr-context, sabre:controlled-apply-strong-revalidation-linkage, then sabre:controlled-pnr-final-readiness before allowance.';
        }

        if ($dryRun || $productionBlocked || ! ($eligibility['eligible'] ?? false)) {
            if ($productionBlocked) {
                $payload['blocked_message'] = 'Missing or invalid --confirm phrase. No booking meta mutation attempted.';
            } elseif (! $dryRun && ! ($eligibility['eligible'] ?? false)) {
                $payload['blocked_message'] = 'Allowance eligibility gates blocked meta write.';
            }

            $this->emitPayload($payload);

            return $dryRun ? self::SUCCESS : self::FAILURE;
        }

        $finalReadiness = $gate->evaluateAllowanceEligibility($booking, false, true);
        $record = $gate->buildAllowanceRecord($booking, $finalReadiness);
        $meta = is_array($booking->meta) ? $booking->meta : [];
        $meta[SabreControlledFinalPnrRetryAllowanceGate::META_KEY] = $record;
        $booking->forceFill(['meta' => $meta]);
        $booking->save();

        $payload['classification'] = 'allowance_written';
        $payload['db_mutation_attempted'] = true;
        $payload['allowance_written'] = true;
        $payload['allowed_at'] = (string) ($record['allowed_at'] ?? '');
        $payload['expires_at'] = (string) ($record['expires_at'] ?? '');
        $payload['final_readiness_checked_at'] = (string) ($record['final_readiness_checked_at'] ?? '');

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
            $booking = Booking::query()->where('booking_reference', $reference)->first();
            if ($booking !== null) {
                return $booking;
            }

            if (Schema::hasColumn('bookings', 'reference_code')) {
                return Booking::query()->where('reference_code', $reference)->first();
            }
        }

        return null;
    }

    protected function confirmPhraseMatches(Booking $booking): bool
    {
        return trim((string) $this->option('confirm')) === SabreControlledFinalPnrRetryAllowanceGate::confirmPhraseForBooking($booking);
    }

    protected function productionRequiresConfirm(): bool
    {
        return (string) config('app.env', 'production') === 'production';
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
