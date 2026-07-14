<?php

namespace App\Console\Commands;

use App\Services\Developer\DevCpMonitoringSnapshotService;
use App\Support\Audits\OtaAuditReportWriter;
use App\Support\Sabre\SabreCommandSafetyOutput;
use Illuminate\Console\Command;

class OtaAuditSabreStatusCommand extends Command
{
    protected $signature = 'ota:audit-sabre-status {--export=docs/audits/OTA_SABRE_STATUS_REPORT.md}';

    protected $description = 'Generate read-only Sabre supplier status audit report';

    public function handle(DevCpMonitoringSnapshotService $monitoring): int
    {
        foreach (SabreCommandSafetyOutput::readOnlyBanner() as $line) {
            $this->line($line);
        }

        $snapshot = $monitoring->sabreStatus();

        $lines = [
            '# OTA Sabre Status Report',
            '',
            'Generated: '.now()->toIso8601String(),
            '',
            'Classification: **READ-ONLY** (no supplier mutation)',
            SabreCommandSafetyOutput::liveSupplierCallAttempted(false),
            '',
            '## Module flags',
            '',
            '| Flag | Value | Classification |',
            '|------|-------|----------------|',
            '| sabre_gds | '.(($snapshot['sabre_gds_enabled'] ?? false) ? 'on' : 'off').' | safe |',
            '| sabre_ndc | '.(($snapshot['sabre_ndc_enabled'] ?? false) ? 'on' : 'off').' | safe |',
            '| booking_enabled (config) | '.(($snapshot['booking_enabled'] ?? false) ? 'true' : 'false').' | needs manual verification |',
            '| ticketing_enabled (config) | '.(($snapshot['ticketing_enabled'] ?? false) ? 'true' : 'false').' | needs manual verification |',
            '',
        ];

        $warnings = $snapshot['warnings'] ?? [];
        if ($warnings !== []) {
            $lines[] = '## Warnings';
            $lines[] = '';
            foreach ($warnings as $warning) {
                $lines[] = '- '.$warning;
            }
            $lines[] = '';
        }

        $primary = $snapshot['primary_connection'] ?? null;
        $lines[] = '## Primary active connection';
        $lines[] = '';
        if (is_array($primary)) {
            $lines[] = '- ID: **'.($primary['id'] ?? '?').'**';
            $lines[] = '- Name: **'.($primary['name'] ?? '—').'**';
            $lines[] = '- Environment: '.($primary['environment'] ?? '—');
            $lines[] = '- Base host: '.($primary['base_host'] ?? '—');
            $lines[] = '- Auth keys present: '.(($primary['credential_keys_present'] ?? false) ? 'yes' : 'no');
        } else {
            $lines[] = '- None configured';
        }
        $lines[] = '';

        $flags = $snapshot['config_flags'] ?? [];
        $lines[] = '## Config flags';
        $lines[] = '';
        if ($flags === []) {
            $lines[] = '- Not available';
        } else {
            foreach ($flags as $key => $value) {
                $lines[] = '- '.$key.': '.($value ? 'enabled' : 'disabled');
            }
        }
        $lines[] = '';

        $lines[] = '## Provider mutation policy';
        $lines[] = '';
        $lines[] = '| Capability | Status | Live call | Production |';
        $lines[] = '|------------|--------|-----------|------------|';
        foreach ($snapshot['mutation_policy'] ?? [] as $row) {
            $lines[] = '| '.($row['label'] ?? $row['key'] ?? '—')
                .' | '.($row['status'] ?? '—')
                .' | '.(($row['live_supplier_call_allowed'] ?? false) ? 'yes' : 'no')
                .' | '.(($row['production_allowed'] ?? false) ? 'yes' : 'no').' |';
        }
        $lines[] = '';

        $routes = $snapshot['route_readiness'] ?? [];
        $lines[] = '## Retrieve/sync route readiness';
        $lines[] = '';
        $lines[] = '- admin.bookings.sync-pnr-itinerary: '.(($routes['admin_sync_pnr_itinerary_registered'] ?? false) ? 'registered' : 'missing');
        $lines[] = '- staff.bookings.sync-pnr-itinerary: '.(($routes['staff_sync_pnr_itinerary_registered'] ?? false) ? 'registered' : 'missing');
        $lines[] = '';

        $lines[] = '## Connections ('.count($snapshot['connections'] ?? []).')';
        $lines[] = '';
        foreach ($snapshot['connections'] ?? [] as $conn) {
            $lines[] = '- #'.($conn['id'] ?? '?').' Agency #'.($conn['agency_id'] ?? '?').': **'.($conn['name'] ?? '—').'**'
                .' — active='.(($conn['is_active'] ?? false) ? 'yes' : 'no')
                .', status='.($conn['status'] ?? '—')
                .', env='.($conn['environment'] ?? '—')
                .', host='.($conn['base_host'] ?? '—')
                .', gds='.(($conn['sabre_gds_enabled'] ?? true) ? 'on' : 'off')
                .', ndc='.(($conn['sabre_ndc_enabled'] ?? false) ? 'on' : 'off')
                .', auth='.(($conn['credential_keys_present'] ?? false) ? 'yes' : 'no')
                .' (safe — no credentials)';
        }

        $lines[] = '';
        $lines[] = '## Recent failures ('.count($snapshot['recent_failures'] ?? []).')';
        $lines[] = '';
        foreach ($snapshot['recent_failures'] ?? [] as $row) {
            $summary = $row['safe_summary'] ?? $row['error_message'] ?? '—';
            $lines[] = '- Booking #'.($row['booking_id'] ?? '?').': '.($row['error_code'] ?? '—').' — '.$summary;
        }

        $export = (string) $this->option('export');
        OtaAuditReportWriter::write(base_path($export), $lines);
        $this->info('Report written: '.$export);

        return self::SUCCESS;
    }
}
