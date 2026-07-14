<?php

namespace App\Console\Commands;

use App\Models\SupplierBookingAttempt;
use App\Services\Suppliers\Sabre\SabreInspectGate;
use Illuminate\Console\Command;

class SabreInspectBookingAttemptCommand extends Command
{
    protected $signature = 'sabre:inspect-booking-attempt {--attempt= : Supplier booking attempt ID}';

    protected $description = '[local/testing only] Safe summary for a supplier_booking_attempts row (no raw payload, no PII)';

    public function handle(): int
    {
        if (! SabreInspectGate::allowed()) {
            $this->components->error('This command only runs when APP_ENV is local or testing.');

            return self::FAILURE;
        }

        $raw = $this->option('attempt');
        if ($raw === null || $raw === '' || ! is_numeric($raw)) {
            $this->components->error('Pass --attempt={id} with a numeric supplier_booking_attempts.id.');

            return self::FAILURE;
        }

        $attemptId = (int) $raw;
        $attempt = SupplierBookingAttempt::query()->with('booking')->find($attemptId);
        if ($attempt === null) {
            $this->components->error('Attempt not found.');

            return self::FAILURE;
        }

        $booking = $attempt->booking;
        $safe = is_array($attempt->safe_summary) ? $attempt->safe_summary : [];
        $codes = $safe['response_error_codes'] ?? [];
        $msgs = $safe['response_error_messages'] ?? [];
        $fields = $safe['response_error_fields'] ?? [];
        $missing = $safe['response_missing_fields'] ?? [];
        $topKeys = $safe['response_top_level_keys'] ?? [];
        $codesLine = is_array($codes) && $codes !== [] ? implode(', ', array_map(static fn ($c) => (string) $c, $codes)) : '';
        $msgsLine = is_array($msgs) && $msgs !== [] ? implode(' | ', array_map(static fn ($m) => (string) $m, array_slice($msgs, 0, 4))) : '';
        $fieldsLine = is_array($fields) && $fields !== [] ? implode(' | ', array_map(static fn ($m) => (string) $m, array_slice($fields, 0, 8))) : '';
        $missingLine = is_array($missing) && $missing !== [] ? implode(' | ', array_map(static fn ($m) => (string) $m, array_slice($missing, 0, 8))) : '';
        $topKeysLine = is_array($topKeys) && $topKeys !== [] ? implode(', ', array_map(static fn ($k) => (string) $k, array_slice($topKeys, 0, 24))) : '';
        $pnrPresent = ($booking !== null && trim((string) ($booking->pnr ?? '')) !== '')
            || (isset($safe['pnr']) && is_string($safe['pnr']) && trim($safe['pnr']) !== '');

        $this->line('attempt_id='.$attempt->id);
        $this->line('booking_id='.$attempt->booking_id);
        $this->line('provider='.(string) $attempt->provider);
        $this->line('action='.(string) $attempt->action);
        $this->line('payload_style='.(string) ($safe['payload_style'] ?? '—'));
        $this->line('endpoint_path='.(string) ($safe['endpoint_path'] ?? '—'));
        $this->line('http_status='.(string) ($safe['http_status'] ?? '—'));
        $this->line('status='.(string) $attempt->status);
        $this->line('error_code='.(string) ($attempt->error_code ?? '—'));
        $this->line('supplier_reference='.(string) ($attempt->supplier_reference ?? '—'));
        $this->line('pnr_present='.($pnrPresent ? 'yes' : 'no'));
        $this->line('response_error_count='.(string) ($safe['response_error_count'] ?? '—'));
        $this->line('response_error_codes='.($codesLine !== '' ? $codesLine : '—'));
        $this->line('response_error_messages='.($msgsLine !== '' ? $msgsLine : '—'));
        $this->line('response_error_fields='.($fieldsLine !== '' ? $fieldsLine : '—'));
        $this->line('response_missing_fields='.($missingLine !== '' ? $missingLine : '—'));
        $this->line('response_top_level_keys='.($topKeysLine !== '' ? $topKeysLine : '—'));
        $this->line('response_top_level_error_code='.(string) ($safe['response_top_level_error_code'] ?? '—'));
        $this->line('response_top_level_type='.(string) ($safe['response_top_level_type'] ?? '—'));
        $addl = $safe['response_additional_messages'] ?? [];
        $addlLine = is_array($addl) && $addl !== [] ? implode(' | ', array_map(static fn ($m) => (string) $m, array_slice($addl, 0, 4))) : '';
        $this->line('response_additional_messages='.($addlLine !== '' ? $addlLine : '—'));
        $this->line('wire_root_keys='.(is_array($safe['wire_root_keys'] ?? null) ? json_encode(array_slice($safe['wire_root_keys'], 0, 24)) : '—'));
        $this->line('wire_has_flight_offer_at_root='.(isset($safe['wire_has_flight_offer_at_root']) ? ($safe['wire_has_flight_offer_at_root'] ? 'true' : 'false') : '—'));
        $this->line('wire_has_flight_details_at_root='.(isset($safe['wire_has_flight_details_at_root']) ? ($safe['wire_has_flight_details_at_root'] ? 'true' : 'false') : '—'));
        $this->line('wire_has_required_product_at_root='.(isset($safe['wire_has_required_product_at_root']) ? ($safe['wire_has_required_product_at_root'] ? 'true' : 'false') : '—'));
        $this->line('wire_flight_offer_segment_count='.(string) ($safe['wire_flight_offer_segment_count'] ?? '—'));
        $this->line('wire_flight_details_segment_count='.(string) ($safe['wire_flight_details_segment_count'] ?? '—'));
        $this->line('wire_traveler_count='.(string) ($safe['wire_traveler_count'] ?? '—'));
        $this->line('request_id='.(string) ($safe['request_id'] ?? '—'));
        $this->line('request_correlation_id='.(string) ($safe['request_correlation_id'] ?? '—'));
        $this->line('trace_id='.(string) ($safe['trace_id'] ?? '—'));

        return self::SUCCESS;
    }
}
