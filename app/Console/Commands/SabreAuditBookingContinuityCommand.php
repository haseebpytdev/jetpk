<?php

namespace App\Console\Commands;

use App\Models\Booking;
use App\Services\Suppliers\Sabre\Diagnostics\SabreBookingContinuityAuditor;
use App\Services\Suppliers\Sabre\SabreInspectGate;
use Illuminate\Console\Command;
use Throwable;

/**
 * Sprint 11K-B / 11K-B2: Passive Sabre booking continuity audit (stored snapshot → context → revalidation draft → PNR draft).
 * Local/testing always; production only with --confirm=READONLY-CONTINUITY-AUDIT (read-only, no live HTTP).
 */
class SabreAuditBookingContinuityCommand extends Command
{
    public const PRODUCTION_READONLY_CONFIRM_PHRASE = 'READONLY-CONTINUITY-AUDIT';

    protected $signature = 'sabre:audit-booking-continuity
                            {--booking= : Booking ID}
                            {--confirm= : Production only: must be READONLY-CONTINUITY-AUDIT}
                            {--json : Emit booking_continuity_audit_json=... only}';

    protected $description = 'Sabre booking continuity audit (local/testing; production read-only with --confirm=READONLY-CONTINUITY-AUDIT; no live HTTP)';

    public function handle(SabreBookingContinuityAuditor $auditor): int
    {
        $gate = $this->resolveGate();
        if ($gate === null) {
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

        $this->printReadonlySafetyLines($gate['production_readonly_confirmed']);

        try {
            $report = $auditor->audit($booking);
        } catch (Throwable) {
            $this->components->error('Continuity audit failed safety check (details omitted).');

            return self::FAILURE;
        }

        if (isset($report['error'])) {
            $this->line('booking_id='.$booking->id);
            $this->line('error='.(string) $report['error']);

            return self::SUCCESS;
        }

        if ((bool) $this->option('json')) {
            $this->line('booking_continuity_audit_json='.json_encode($report, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE));
        } else {
            $this->printHumanReport($report);
        }

        return self::SUCCESS;
    }

    /**
     * @return array{production_readonly_confirmed: bool}|null Gate context, or null when blocked.
     */
    protected function resolveGate(): ?array
    {
        if (SabreInspectGate::allowed()) {
            return ['production_readonly_confirmed' => false];
        }

        $env = (string) config('app.env', 'production');
        if ($env !== 'production') {
            $this->components->error('This command only runs when APP_ENV is local or testing.');

            return null;
        }

        $confirm = trim((string) $this->option('confirm'));
        if ($confirm === self::PRODUCTION_READONLY_CONFIRM_PHRASE) {
            return ['production_readonly_confirmed' => true];
        }

        if ($confirm === '') {
            $this->components->error(
                'Production requires --confirm='.self::PRODUCTION_READONLY_CONFIRM_PHRASE.' for read-only audit.'
            );
        } else {
            $this->components->error('Invalid --confirm phrase for production read-only audit.');
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

    /**
     * @param  array<string, mixed>  $report
     */
    protected function printHumanReport(array $report): void
    {
        $this->line('report_version='.($report['report_version'] ?? ''));
        $this->line('booking_id='.($report['booking_id'] ?? ''));
        $this->line('readiness_recommendation='.($report['readiness_recommendation'] ?? ''));
        $this->line('final_diagnostic_recommendation='.($report['final_diagnostic_recommendation'] ?? ''));
        $this->line('pricing_context_ready='.(($report['pricing_context_ready'] ?? false) ? 'true' : 'false'));
        $reasons = is_array($report['readiness_reasons'] ?? null) ? $report['readiness_reasons'] : [];
        $this->line('readiness_reasons='.implode(',', array_slice($reasons, 0, 8)));
        $this->newLine();

        $overlay = is_array($report['host_outcome_overlay'] ?? null) ? $report['host_outcome_overlay'] : [];
        foreach ([
            'host_outcome_present',
            'host_outcome_status',
            'host_error_family',
            'host_checkout_status',
            'host_error_code',
            'host_safe_reason_code',
            'local_continuity_aligned',
            'host_rejected_after_local_continuity',
        ] as $overlayKey) {
            if (! array_key_exists($overlayKey, $overlay)) {
                continue;
            }
            $val = $overlay[$overlayKey];
            if (is_bool($val)) {
                $this->line($overlayKey.'='.($val ? 'true' : 'false'));
            } elseif ($val === null) {
                $this->line($overlayKey.'=');
            } else {
                $this->line($overlayKey.'='.(string) $val);
            }
        }
        $this->newLine();

        $present = is_array($report['sources_present'] ?? null) ? $report['sources_present'] : [];
        foreach ($present as $source => $isPresent) {
            $this->line('source_present.'.$source.'='.($isPresent ? 'true' : 'false'));
        }
        $this->newLine();

        $tableRows = [];
        foreach ((array) ($report['continuity_rows'] ?? []) as $row) {
            if (! is_array($row)) {
                continue;
            }
            $values = is_array($row['values_by_source'] ?? null) ? $row['values_by_source'] : [];
            $tableRows[] = [
                (string) ($row['field'] ?? ''),
                (string) ($row['status'] ?? ''),
                (string) ($row['authority'] ?? ''),
                (string) ($values['normalized_snapshot'] ?? '—'),
                (string) ($values['sabre_shop_context'] ?? '—'),
                (string) ($values['sabre_booking_context'] ?? '—'),
                (string) ($values['refreshed_offer_snapshot'] ?? '—'),
                (string) ($values['revalidation_linkage'] ?? '—'),
                (string) ($values['pnr_draft'] ?? '—'),
            ];
        }

        $this->table(
            ['field', 'status', 'authority', 'normalized', 'shop_ctx', 'booking_ctx', 'refreshed', 'reval', 'pnr_draft'],
            $tableRows,
        );
    }
}
