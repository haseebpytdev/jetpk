<?php

namespace App\Console\Commands;

use App\Models\Booking;
use App\Support\Bookings\SabreControlledPnrContextDigest;
use Illuminate\Console\Command;

/**
 * F9B: Read-only controlled Sabre PNR context diagnostics (no live Sabre HTTP, no DB mutation).
 */
class SabreControlledPnrContextCommand extends Command
{
    public const PRODUCTION_READONLY_CONFIRM_PHRASE = 'READONLY-CONTROLLED-PNR-CONTEXT';

    protected $signature = 'sabre:controlled-pnr-context
                            {--booking= : Booking ID}
                            {--reference= : Booking reference code}
                            {--json : Emit diagnostic JSON only}
                            {--confirm= : Production only: READONLY-CONTROLLED-PNR-CONTEXT}';

    protected $description = 'Controlled Sabre PNR context digest (read-only; production requires --confirm)';

    public function handle(SabreControlledPnrContextDigest $digest): int
    {
        if ($this->resolveGate() === null) {
            return self::FAILURE;
        }

        $booking = $this->resolveBooking();
        if ($booking === null) {
            if ($this->option('booking') === null && $this->option('reference') === null) {
                $this->components->error('Pass --booking={id} or --reference={code}.');
            } else {
                $this->components->error('Booking not found.');
            }

            return self::FAILURE;
        }

        $payload = $digest->classify($booking);
        $payload['live_supplier_call_attempted'] = false;
        $payload['pnr_create_attempted'] = false;
        $payload['ticketing_attempted'] = false;
        $payload['cancellation_attempted'] = false;

        if ((bool) $this->option('json')) {
            $this->line(json_encode($payload, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE));

            return self::SUCCESS;
        }

        $this->line('live_supplier_call_attempted=false');
        $this->line('pnr_create_attempted=false');
        $this->line('ticketing_attempted=false');
        $this->line('cancellation_attempted=false');
        $this->newLine();

        $keys = [
            'booking_id',
            'booking_reference',
            'booking_status',
            'payment_status',
            'supplier_provider',
            'supplier_connection_id',
            'pnr_present',
            'supplier_reference_present',
            'selected_offer_revalidation_status',
            'revalidation_status',
            'selected_offer_last_revalidated_at',
            'last_revalidated_at',
            'offer_refresh_status',
            'offer_refresh_reason',
            'sabre_booking_context_has_revalidation_linkage',
            'sabre_booking_context_ready_for_booking_payload',
            'sabre_booking_context_validating_carrier',
            'sabre_booking_context_brand_code',
            'segment_count',
            'carrier_chain',
            'certified_route_selection_category',
            'certified_route_selection_route_status',
            'certified_route_selection_endpoint_path',
            'certified_route_selection_payload_style',
            'sabre_checkout_outcome_status',
            'sabre_checkout_outcome_live_call_attempted',
            'sabre_checkout_outcome_error_code',
            'safe_refresh_context_present',
            'safe_refresh_context_complete',
            'normalized_offer_snapshot_present',
            'validated_offer_snapshot_present',
            'pricing_snapshot_present',
            'raw_payload_present',
            'pricing_snapshot_currency_present',
            'pricing_snapshot_converted_present',
            'has_strong_revalidation_linkage',
            'has_legacy_success_revalidation_signal',
            'has_payload_ready_context',
            'has_safe_refresh_context',
            'has_certified_route_selection',
            'has_usable_controlled_pnr_context',
            'controlled_context_classification',
            'controlled_context_reason_code',
        ];

        foreach ($keys as $key) {
            $this->printKeyValue($key, $payload[$key] ?? null);
        }

        $warnings = is_array($payload['controlled_context_warnings'] ?? null) ? array_values($payload['controlled_context_warnings']) : [];
        $blockers = is_array($payload['controlled_context_blockers'] ?? null) ? array_values($payload['controlled_context_blockers']) : [];
        $this->line('controlled_context_warnings='.json_encode($warnings, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE));
        $this->line('controlled_context_blockers='.json_encode($blockers, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE));

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
        if ($confirm === self::PRODUCTION_READONLY_CONFIRM_PHRASE) {
            return ['production_readonly_confirmed' => true];
        }

        if ($confirm === '') {
            $this->components->error(
                'Production requires --confirm='.self::PRODUCTION_READONLY_CONFIRM_PHRASE.' for read-only diagnostic.'
            );
        } else {
            $this->components->error('Invalid --confirm phrase for production read-only diagnostic.');
        }

        return null;
    }

    protected function printKeyValue(string $key, mixed $value): void
    {
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
