<?php

namespace App\Console\Commands;

use App\Enums\SupplierProvider;
use App\Models\Booking;
use App\Models\SupplierBookingAttempt;
use App\Services\Suppliers\Sabre\SabreInspectGate;
use App\Support\Bookings\ComplexItineraryPolicy;
use App\Support\Bookings\SabrePnrFailureClassifier;
use Illuminate\Console\Command;

/**
 * S1 inspect-only: classify latest Sabre PNR attempt (create_pnr or certification; no HTTP, no payloads).
 */
class SabreClassifyPnrFailureCommand extends Command
{
    /** @var list<string> */
    public const PNR_ATTEMPT_ACTIONS = ['create_pnr', 'create_pnr_certification'];

    protected $signature = 'sabre:classify-pnr-failure
                            {--booking= : Booking primary key}
                            {--action=any : create_pnr, create_pnr_certification, or any (default any)}';

    protected $description = '[local/testing only] Classify latest Sabre PNR attempt — safe JSON only (no Sabre HTTP, no raw response).';

    public function handle(): int
    {
        if (! SabreInspectGate::allowed()) {
            $this->emitJson([
                'error' => 'environment_not_allowed',
                'booking_id' => $this->resolveBookingId(),
            ]);

            return self::FAILURE;
        }

        $bookingId = $this->resolveBookingId();
        if ($bookingId === null) {
            $this->emitJson(['error' => 'missing_booking_id']);

            return self::FAILURE;
        }

        $pnrActions = $this->resolvePnrActionFilter();
        if ($pnrActions === null) {
            $this->emitJson([
                'error' => 'invalid_action',
                'booking_id' => $bookingId,
                'action' => (string) $this->option('action'),
                'allowed_actions' => array_merge(['any'], self::PNR_ATTEMPT_ACTIONS),
            ]);

            return self::FAILURE;
        }

        $booking = Booking::query()->find($bookingId);
        if ($booking === null) {
            $this->emitJson([
                'error' => 'booking_not_found',
                'booking_id' => $bookingId,
            ]);

            return self::FAILURE;
        }

        $this->emitJson($this->buildPayload($booking, $pnrActions));

        return self::SUCCESS;
    }

    /**
     * @param  list<string>  $pnrActions
     * @return array<string, mixed>
     */
    protected function buildPayload(Booking $booking, array $pnrActions): array
    {
        $meta = is_array($booking->meta) ? $booking->meta : [];
        $provider = strtolower(trim((string) ($meta['supplier_provider'] ?? $booking->supplier ?? '')));
        $isSabre = $provider === SupplierProvider::Sabre->value;

        $pnrPresent = trim((string) ($booking->pnr ?? '')) !== '';
        $supplierReferencePresent = trim((string) ($booking->supplier_reference ?? '')) !== '';

        $attempt = SupplierBookingAttempt::query()
            ->where('booking_id', $booking->id)
            ->where('provider', SupplierProvider::Sabre->value)
            ->whereIn('action', $pnrActions)
            ->orderByDesc('id')
            ->first();

        $complexDeferred = $isSabre
            && ComplexItineraryPolicy::isComplex($booking)
            && ! ComplexItineraryPolicy::complexItineraryPnrEnabled();

        $attemptErrorCode = trim((string) ($attempt?->error_code ?? ''));
        $safeSummary = is_array($attempt?->safe_summary) ? $attempt->safe_summary : [];

        if ($complexDeferred && ($attempt === null || $attemptErrorCode === ComplexItineraryPolicy::ERROR_CODE)) {
            $source = 'complex_policy';
            $classified = SabrePnrFailureClassifier::classify(ComplexItineraryPolicy::ERROR_CODE, $safeSummary);
        } elseif ($attempt !== null) {
            $source = 'latest_attempt';
            $classified = SabrePnrFailureClassifier::classify(
                $attemptErrorCode !== '' ? $attemptErrorCode : null,
                $safeSummary,
            );
        } else {
            $source = 'fallback';
            $classified = [
                'classification' => '',
                'next_action' => '',
                'retry_allowed' => true,
                'admin_message' => '',
                'customer_message' => '',
            ];
        }

        if ($complexDeferred) {
            $classified['retry_allowed'] = false;
        }

        return [
            'booking_id' => $booking->id,
            'latest_attempt_id' => $attempt?->id,
            'latest_attempt_action' => $attempt !== null ? (string) $attempt->action : null,
            'latest_attempt_status' => $attempt !== null ? (string) $attempt->status : null,
            'latest_attempt_error_code' => $attemptErrorCode !== '' ? $attemptErrorCode : null,
            'classification' => (string) ($classified['classification'] ?? ''),
            'retry_allowed' => (bool) ($classified['retry_allowed'] ?? true),
            'next_action' => (string) ($classified['next_action'] ?? ''),
            'staff_message' => (string) ($classified['admin_message'] ?? ''),
            'customer_message' => (string) ($classified['customer_message'] ?? ''),
            'source' => $source,
            'pnr_present' => $pnrPresent,
            'supplier_reference_present' => $supplierReferencePresent,
        ];
    }

    protected function resolveBookingId(): ?int
    {
        $raw = $this->option('booking');
        if ($raw === null || $raw === '' || ! is_numeric($raw)) {
            return null;
        }

        return (int) $raw;
    }

    /**
     * @return list<string>|null
     */
    protected function resolvePnrActionFilter(): ?array
    {
        $raw = strtolower(trim((string) $this->option('action')));
        if ($raw === '' || $raw === 'any') {
            return self::PNR_ATTEMPT_ACTIONS;
        }

        if (in_array($raw, self::PNR_ATTEMPT_ACTIONS, true)) {
            return [$raw];
        }

        return null;
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    protected function emitJson(array $payload): void
    {
        $this->line('pnr_failure_classification_json='.json_encode($payload, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE));
    }
}
