<?php

namespace App\Console\Commands;

use App\Models\Booking;
use App\Support\Bookings\SabrePreCheckoutKnownFailureSoftBlock;
use App\Support\Bookings\SabrePreCheckoutSellabilityDryRun;
use App\Support\Bookings\SabrePreCheckoutSellabilityPresentation;
use App\Support\Bookings\SabreVerifiedAutoPnrCandidateDiscovery;
use Illuminate\Console\Command;

/**
 * E5G/E5H: Read-only verified-lane evidence and pre-checkout sellability diagnostics (no live Sabre HTTP).
 */
class SabreDiagnoseVerifiedAutoPnrCandidateCommand extends Command
{
    public const PRODUCTION_READONLY_CONFIRM_PHRASE = 'READONLY-EVIDENCE-DIAG';

    public const PRODUCTION_PRECHECKOUT_CONFIRM_PHRASE = 'READONLY-PRECHECKOUT-DRYRUN';

    protected $signature = 'sabre:diagnose-verified-auto-pnr-candidate
                            {--booking= : Booking ID}
                            {--confirm= : Production only: READONLY-EVIDENCE-DIAG or READONLY-PRECHECKOUT-DRYRUN with --precheckout}
                            {--precheckout : E5H pre-checkout sellability dry-run output}
                            {--json : Emit diagnostic JSON only}';

    protected $description = 'Verified-lane evidence / pre-checkout sellability dry-run (read-only; production requires --confirm)';

    public function handle(
        SabreVerifiedAutoPnrCandidateDiscovery $discovery,
        SabrePreCheckoutSellabilityDryRun $preCheckoutDryRun,
    ): int {
        if ($this->resolveGate() === null) {
            return self::FAILURE;
        }

        $raw = $this->option('booking');
        if ($raw === null || $raw === '' || ! is_numeric($raw)) {
            $this->components->error('Pass --booking={id} with a numeric booking id.');

            return self::FAILURE;
        }

        $booking = Booking::query()->find((int) $raw);
        if ($booking === null) {
            $this->components->error('Booking not found.');

            return self::FAILURE;
        }

        $this->line('live_supplier_call_attempted=false');
        $this->line('booking_status_updated=false');
        $this->newLine();

        if ((bool) $this->option('precheckout')) {
            $report = $preCheckoutDryRun->evaluate($booking);
            $presentation = SabrePreCheckoutSellabilityPresentation::fromDryRun($report);
            $softBlockFields = $this->buildSoftBlockFields($report);

            if ((bool) $this->option('json')) {
                $this->line('pre_checkout_sellability_dry_run_json='.json_encode([
                    ...$report,
                    'presentation' => $presentation,
                    ...$softBlockFields,
                ], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE));
            } else {
                $this->printPreCheckoutHumanReport($report, $presentation, $softBlockFields);
            }
        } else {
            $report = $discovery->diagnose($booking);

            if ((bool) $this->option('json')) {
                $this->line('verified_auto_pnr_candidate_diagnostic_json='.json_encode($report, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE));
            } else {
                $this->printHumanReport($report);
            }
        }

        return self::SUCCESS;
    }

    /**
     * @return array{production_readonly_confirmed: bool}|null
     */
    protected function resolveGate(): ?array
    {
        $env = (string) config('app.env', 'production');
        if (in_array($env, ['local', 'testing'], true)) {
            return ['production_readonly_confirmed' => false];
        }

        if ($env !== 'production') {
            $this->components->error('This command only runs when APP_ENV is local, testing, or production.');

            return null;
        }

        $confirm = trim((string) $this->option('confirm'));
        $expectedPhrase = (bool) $this->option('precheckout')
            ? self::PRODUCTION_PRECHECKOUT_CONFIRM_PHRASE
            : self::PRODUCTION_READONLY_CONFIRM_PHRASE;

        if ($confirm === $expectedPhrase) {
            return ['production_readonly_confirmed' => true];
        }

        if ($confirm === '') {
            $this->components->error(
                'Production requires --confirm='.$expectedPhrase.' for read-only diagnostic.'
            );
        } else {
            $this->components->error('Invalid --confirm phrase for production read-only diagnostic.');
        }

        return null;
    }

    /**
     * @param  array<string, mixed>  $report
     */
    protected function printHumanReport(array $report): void
    {
        foreach ([
            'booking_id',
            'booking_reference',
            'pnr_status',
            'pnr',
            'evidence_status',
            'evidence_reason_code',
            'matched_success_booking_id',
            'matched_failed_booking_id',
            'payload_strategy',
            'public_auto_pnr_allowed_now',
            'readiness_reason_code',
            'recommended_action',
        ] as $key) {
            $value = $report[$key] ?? null;
            if (is_bool($value)) {
                $this->line($key.'='.($value ? 'true' : 'false'));
            } elseif ($value === null || $value === '') {
                $this->line($key.'=');
            } else {
                $this->line($key.'='.$value);
            }
        }

        $segments = is_array($report['segment_summary'] ?? null) ? $report['segment_summary'] : [];
        $this->line('segment_summary='.json_encode($segments, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE));
    }

    /**
     * @param  array<string, mixed>  $report
     * @return array<string, mixed>
     */
    protected function buildSoftBlockFields(array $report): array
    {
        $wouldSoftBlock = SabrePreCheckoutKnownFailureSoftBlock::wouldSoftBlock($report);
        $reason = SabrePreCheckoutKnownFailureSoftBlock::softBlockReason($report);

        return [
            'soft_block_config_enabled' => SabrePreCheckoutKnownFailureSoftBlock::configEnabled(),
            'would_soft_block_public_checkout' => $wouldSoftBlock,
            'soft_block_reason' => $reason ?? '',
            'safe_customer_redirect_message' => SabrePreCheckoutKnownFailureSoftBlock::customerRedirectMessage(),
        ];
    }

    /**
     * @param  array<string, mixed>  $report
     * @param  array<string, mixed>  $presentation
     * @param  array<string, mixed>  $softBlockFields
     */
    protected function printPreCheckoutHumanReport(array $report, array $presentation, array $softBlockFields): void
    {
        foreach ([
            'booking_id',
            'booking_reference',
            'dry_run_status',
            'dry_run_reason_code',
            'recommended_checkout_action',
            'public_auto_pnr_allowed_now',
            'live_supplier_call_attempted',
            'booking_status_updated',
            'evidence_booking_id_success',
            'evidence_booking_id_failed',
        ] as $key) {
            $value = $report[$key] ?? null;
            if (is_bool($value)) {
                $this->line($key.'='.($value ? 'true' : 'false'));
            } elseif ($value === null || $value === '') {
                $this->line($key.'=');
            } else {
                $this->line($key.'='.$value);
            }
        }

        $segments = is_array($report['segment_summary'] ?? null) ? $report['segment_summary'] : [];
        $this->line('segment_summary='.json_encode($segments, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE));

        $this->newLine();
        $this->line('presentation_label='.($presentation['label'] ?? ''));
        $this->line('presentation_severity='.($presentation['severity'] ?? ''));
        $this->line('customer_message='.($presentation['customer_message'] ?? ''));
        $this->line('staff_message='.($presentation['staff_message'] ?? ''));
        $this->line('should_block_public_checkout='.(($presentation['should_block_public_checkout'] ?? false) ? 'true' : 'false'));
        $this->line('should_attempt_auto_pnr='.(($presentation['should_attempt_auto_pnr'] ?? false) ? 'true' : 'false'));

        $this->newLine();
        foreach ([
            'soft_block_config_enabled',
            'would_soft_block_public_checkout',
            'soft_block_reason',
            'safe_customer_redirect_message',
        ] as $key) {
            $value = $softBlockFields[$key] ?? null;
            if (is_bool($value)) {
                $this->line($key.'='.($value ? 'true' : 'false'));
            } elseif ($value === null || $value === '') {
                $this->line($key.'=');
            } else {
                $this->line($key.'='.$value);
            }
        }
    }
}
