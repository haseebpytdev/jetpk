<?php

namespace App\Console\Commands;

use App\Models\Booking;
use App\Services\Suppliers\Sabre\SabreInspectGate;
use App\Support\Sabre\GdsPnrCreate\SabreGdsPnrCreateStrategyDigest;
use App\Support\Sabre\GdsPnrCreate\SabreGdsPnrCreateStrategyRegistry;
use App\Support\Sabre\GdsPnrCreate\SabreGdsPnrCreateStrategySelector;
use Illuminate\Console\Command;

/**
 * Read-only digest of all candidate Sabre GDS PNR create strategies for a booking (no live HTTP, no raw payload / PII).
 */
class SabreGdsPnrStrategyDigestCommand extends Command
{
    public const PRODUCTION_READONLY_CONFIRM_PHRASE = 'READONLY-GDS-PNR-STRATEGY-DIGEST';

    protected $signature = 'sabre:gds-pnr-strategy-digest
                            {--booking= : Booking ID}
                            {--confirm= : Production only: READONLY-GDS-PNR-STRATEGY-DIGEST}';

    protected $description = '[read-only] Safe GDS PNR create strategy digest for all candidate formats';

    public function handle(
        SabreGdsPnrCreateStrategyDigest $digestBuilder,
        SabreGdsPnrCreateStrategySelector $selector,
    ): int {
        $gate = $this->resolveGate();
        if ($gate === null) {
            return self::FAILURE;
        }

        $bookingId = $this->option('booking');
        if ($bookingId === null || $bookingId === '' || ! is_numeric($bookingId)) {
            $this->components->error('Pass --booking={id} with a numeric booking id.');

            return self::FAILURE;
        }

        $booking = Booking::query()->find((int) $bookingId);
        if ($booking === null) {
            $this->components->error('Booking not found.');

            return self::FAILURE;
        }

        $this->printReadonlySafetyLines($gate['production_readonly_confirmed']);

        $selection = $selector->selectForBooking($booking);
        $summary = $digestBuilder->buildBookingSummary($booking);
        foreach ($summary as $key => $value) {
            $this->printLine($key, $value);
        }
        $this->newLine();

        $candidates = $digestBuilder->buildCandidateDigests($booking, $selection);
        foreach ($candidates as $index => $candidate) {
            $this->line('candidate['.$index.']');
            foreach ($candidate as $key => $value) {
                $this->printLine('  '.$key, $value);
            }
            $this->newLine();
        }

        $this->line('selected_strategy='.(string) ($selection['selected_strategy'] ?? ''));
        $this->line('selection_reason='.(string) ($selection['selection_reason'] ?? ''));
        $this->line('eligible_strategies='.json_encode($selection['eligible_strategies'] ?? [], JSON_UNESCAPED_SLASHES));
        $this->line('blocked_strategies='.json_encode($selection['blocked_strategies'] ?? [], JSON_UNESCAPED_SLASHES));
        $this->line('fallback_available='.(($selection['fallback_available'] ?? false) ? 'true' : 'false'));
        $knownGood = $selection['known_good_strategy_evidence'] ?? null;
        if (is_array($knownGood) && $knownGood !== []) {
            $this->line('known_good_strategy_evidence='.json_encode($knownGood, JSON_UNESCAPED_SLASHES));
        }
        $v25Reason = $selection['passenger_records_v2_5_gds_not_selected_reason'] ?? null;
        if (is_string($v25Reason) && $v25Reason !== '') {
            $this->line('passenger_records_v2_5_gds_not_selected_reason='.$v25Reason);
        }
        $traditionalReason = $selection['traditional_not_selected_reason'] ?? null;
        if (is_string($traditionalReason) && $traditionalReason !== '') {
            $this->line('traditional_not_selected_reason='.$traditionalReason);
        }
        $this->line('public_auto_certified='.(($selection['public_auto_certified'] ?? false) ? 'true' : 'false'));
        $publicAutoBlockReason = $selection['public_auto_block_reason'] ?? null;
        if (is_string($publicAutoBlockReason) && $publicAutoBlockReason !== '') {
            $this->line('public_auto_block_reason='.$publicAutoBlockReason);
        }
        foreach ([
            'auto_pnr_context_completion_status',
            'completion_sources_used',
            'public_auto_pnr_attempt_ready',
            'completed_booking_classes_by_segment_count',
            'completed_fare_basis_codes_by_segment_count',
            'expanded_single_fare_component_to_all_segments',
            'exact_refresh_attempted',
            'exact_refresh_result',
            'previous_attempt_failed',
            'previous_failed_strategy',
            'previous_host_error_family',
            'safe_retry_requires_admin_confirmation',
        ] as $completionKey) {
            if (array_key_exists($completionKey, $summary)) {
                $this->printLine($completionKey, $summary[$completionKey]);
            }
        }
        $checkoutOutcome = data_get($booking->meta, 'sabre_checkout_outcome', []);
        if (is_array($checkoutOutcome)) {
            $hostFamily = trim((string) ($checkoutOutcome['sabre_host_classification']['host_error_family'] ?? ''));
            if ($hostFamily !== '') {
                $this->line('checkout_host_error_family='.$hostFamily);
            }
            $fingerprint = $checkoutOutcome['sabre_host_rejection_fingerprint'] ?? null;
            if (is_array($fingerprint) && $fingerprint !== []) {
                foreach (['trip_type', 'carrier_chain', 'validating_carrier', 'segment_count', 'booking_classes_by_segment_count', 'fare_basis_codes_by_segment_count', 'host_error_family', 'safe_reason_code', 'retry_policy'] as $fpKey) {
                    if (array_key_exists($fpKey, $fingerprint)) {
                        $this->printLine('checkout_fingerprint_'.$fpKey, $fingerprint[$fpKey]);
                    }
                }
            }
        }
        $iatiCandidate = null;
        foreach ($candidates as $candidate) {
            if (($candidate['strategy_code'] ?? '') === SabreGdsPnrCreateStrategyRegistry::STRATEGY_IATI_LIKE_CPNR_V2_4_GDS) {
                $iatiCandidate = $candidate;
                break;
            }
        }
        if (is_array($iatiCandidate)) {
            $this->line('iati_like_cpnr_v2_4_gds_admin_confirmed_fallback_allowed='.(($iatiCandidate['admin_confirmed_fallback_allowed'] ?? false) ? 'true' : 'false'));
        }

        return self::SUCCESS;
    }

    /**
     * @return array{production_readonly_confirmed: bool}|null
     */
    protected function resolveGate(): ?array
    {
        if (SabreInspectGate::allowed()) {
            return ['production_readonly_confirmed' => false];
        }

        $env = (string) config('app.env', 'production');
        if ($env !== 'production') {
            $this->components->error('This command only runs when APP_ENV is local, testing, or production.');

            return null;
        }

        $confirm = trim((string) $this->option('confirm'));
        if ($confirm === self::PRODUCTION_READONLY_CONFIRM_PHRASE) {
            return ['production_readonly_confirmed' => true];
        }

        if ($confirm === '') {
            $this->components->error(
                'Production requires --confirm='.self::PRODUCTION_READONLY_CONFIRM_PHRASE.' for read-only inspect.'
            );
        } else {
            $this->components->error('Invalid --confirm phrase for production read-only inspect.');
        }

        return null;
    }

    protected function printReadonlySafetyLines(bool $productionReadonlyConfirmed): void
    {
        $this->line('production_readonly_confirmed='.($productionReadonlyConfirmed ? 'true' : 'false'));
        $this->line('live_supplier_call_attempted=false');
        $this->line('booking_status_updated=false');
        $this->newLine();
    }

    protected function printLine(string $key, mixed $value): void
    {
        if (is_array($value)) {
            $this->line($key.'='.json_encode($value, JSON_UNESCAPED_SLASHES));
        } elseif (is_bool($value)) {
            $this->line($key.'='.($value ? 'true' : 'false'));
        } elseif ($value === null) {
            return;
        } else {
            $this->line($key.'='.$value);
        }
    }
}
