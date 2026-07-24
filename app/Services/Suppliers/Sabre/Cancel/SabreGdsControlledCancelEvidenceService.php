<?php

namespace App\Services\Suppliers\Sabre\Cancel;

use App\Enums\SupplierProvider;
use App\Models\AuditLog;
use App\Models\Booking;
use App\Models\User;
use App\Support\Security\SensitiveDataRedactor;
use Illuminate\Support\Facades\DB;

/**
 * Operator-controlled recording of confirmed Sabre GDS cancel evidence for legacy inspect runs (no supplier mutations).
 */
final class SabreGdsControlledCancelEvidenceService
{
    public const META_KEY = 'sabre_gds_controlled_cancel_evidence';

    public const AUDIT_ACTION = 'booking.sabre_gds_controlled_cancel_evidence_recorded';

    public const CONFIRM_PHRASE = 'RECORD-CONFIRMED-SABRE-GDS-CANCEL-EVIDENCE';

    /** @var list<string> */
    public const CONFIRMED_CLASSIFICATIONS = [
        SabreBookingCancelService::CLASSIFICATION_CANCEL_CONFIRMED,
        SabreBookingCancelService::CLASSIFICATION_CANCEL_CONFIRMED_AIR_SEGMENTS_REMOVED,
    ];

    public function __construct(
        private readonly SabreGdsCancelReadiness $readiness,
        private readonly SabreCancelBookingInspectProbe $inspectProbe,
    ) {}

    /**
     * @param  array<string, mixed>  $context
     * @return array<string, mixed>
     */
    public function recordEvidence(
        Booking $booking,
        string $classification,
        bool $verifyWithReadOnlyGetBooking,
        ?User $actor = null,
        array $context = [],
    ): array {
        $booking->refresh();
        $classification = strtoupper(trim($classification));

        if (! $this->isConfirmedClassification($classification)) {
            return [
                'success' => false,
                'reason_code' => 'invalid_classification',
                'booking_id' => $booking->id,
            ];
        }

        $meta = is_array($booking->meta) ? $booking->meta : [];
        $existing = is_array($meta[self::META_KEY] ?? null) ? $meta[self::META_KEY] : [];
        if ($this->hasRecordedEvidence($existing, $classification)) {
            return [
                'success' => true,
                'already_recorded' => true,
                'booking_id' => $booking->id,
                'classification' => $classification,
            ];
        }

        $provider = strtolower(trim((string) ($meta['supplier_provider'] ?? $booking->supplier ?? '')));
        if ($provider !== SupplierProvider::Sabre->value) {
            return [
                'success' => false,
                'reason_code' => 'not_sabre_booking',
                'booking_id' => $booking->id,
            ];
        }

        $pnr = trim((string) ($booking->pnr ?? ''));
        if ($pnr === '') {
            return [
                'success' => false,
                'reason_code' => 'pnr_missing',
                'booking_id' => $booking->id,
            ];
        }

        if ($this->readiness->isTicketed($booking, $meta)) {
            return [
                'success' => false,
                'reason_code' => 'ticketed_booking',
                'booking_id' => $booking->id,
            ];
        }

        $verification = null;
        if ($verifyWithReadOnlyGetBooking) {
            $verification = $this->inspectProbe->sanitizedPostCancelVerificationForBooking($booking);
            $segmentCount = (int) ($verification['post_cancel_segment_count'] ?? -1);
            $ticketNumbersPresent = ($verification['post_cancel_ticket_numbers_present'] ?? true) === true;
            $isTicketed = ($verification['post_cancel_is_ticketed'] ?? null) === true;

            if ($segmentCount !== 0 || $ticketNumbersPresent || $isTicketed) {
                return [
                    'success' => false,
                    'reason_code' => 'read_only_verification_failed',
                    'booking_id' => $booking->id,
                    'verification' => $verification,
                ];
            }
        }

        $evidenceSlice = SensitiveDataRedactor::redact([
            'recorded_at' => now()->toIso8601String(),
            'classification' => $classification,
            'source' => (string) ($context['source'] ?? 'sabre_gds_record_cancel_evidence_command'),
            'verification_mode' => $verifyWithReadOnlyGetBooking ? 'read_only_get_booking' : 'operator_attested',
            'verification' => $verification,
            'pnr_present' => true,
            'live_call_attempted' => true,
            'supplier_cancel_verified' => true,
        ]);

        return DB::transaction(function () use ($booking, $classification, $evidenceSlice, $actor, $context): array {
            /** @var Booking $locked */
            $locked = Booking::query()->lockForUpdate()->findOrFail($booking->id);
            $lockedMeta = is_array($locked->meta) ? $locked->meta : [];
            $existing = is_array($lockedMeta[self::META_KEY] ?? null) ? $lockedMeta[self::META_KEY] : [];

            if ($this->hasRecordedEvidence($existing, $classification)) {
                return [
                    'success' => true,
                    'already_recorded' => true,
                    'booking_id' => $locked->id,
                    'classification' => $classification,
                ];
            }

            $lockedMeta[self::META_KEY] = $evidenceSlice;
            $locked->forceFill(['meta' => $lockedMeta])->save();

            $this->writeAuditIfNeeded($locked, $evidenceSlice, $actor, $context);

            return [
                'success' => true,
                'already_recorded' => false,
                'booking_id' => $locked->id,
                'classification' => $classification,
            ];
        });
    }

    /**
     * @param  array<string, mixed>  $metaSlice
     */
    public function hasRecordedEvidence(array $metaSlice, ?string $classification = null): bool
    {
        if (trim((string) ($metaSlice['recorded_at'] ?? '')) === '') {
            return false;
        }

        $storedClassification = strtoupper(trim((string) ($metaSlice['classification'] ?? '')));
        if ($classification !== null) {
            return $storedClassification === strtoupper(trim($classification))
                && $this->isConfirmedClassification($storedClassification);
        }

        return $this->isConfirmedClassification($storedClassification);
    }

    public function isConfirmedClassification(string $classification): bool
    {
        return in_array(strtoupper(trim($classification)), self::CONFIRMED_CLASSIFICATIONS, true);
    }

    /**
     * @param  array<string, mixed>  $evidenceSlice
     * @param  array<string, mixed>  $context
     */
    protected function writeAuditIfNeeded(Booking $booking, array $evidenceSlice, ?User $actor, array $context): void
    {
        $exists = AuditLog::query()
            ->where('auditable_type', Booking::class)
            ->where('auditable_id', $booking->id)
            ->where('action', self::AUDIT_ACTION)
            ->exists();
        if ($exists) {
            return;
        }

        AuditLog::query()->create([
            'agency_id' => $booking->agency_id,
            'user_id' => $actor?->id,
            'action' => self::AUDIT_ACTION,
            'auditable_type' => Booking::class,
            'auditable_id' => $booking->id,
            'properties' => [
                'old_values' => [],
                'new_values' => SensitiveDataRedactor::redact([
                    'classification' => $evidenceSlice['classification'] ?? null,
                    'verification_mode' => $evidenceSlice['verification_mode'] ?? null,
                    'context_source' => (string) ($context['source'] ?? ''),
                ]),
            ],
        ]);
    }
}
