<?php

namespace App\Services\Suppliers\Sabre\Cancel;

use App\Enums\BookingCancellationStatus;
use App\Enums\BookingStatus;
use App\Enums\SupplierProvider;
use App\Models\AuditLog;
use App\Models\Booking;
use App\Models\SupplierBooking;
use App\Models\SupplierBookingAttempt;
use App\Models\User;
use App\Services\Communication\BookingCommunicationService;
use App\Support\Security\SensitiveDataRedactor;
use Illuminate\Support\Facades\DB;

/**
 * Idempotent local reconciliation for already-confirmed Sabre GDS cancellations (no supplier HTTP).
 */
final class SabreGdsCancellationReconciliationService
{
    public const META_RECONCILED_KEY = 'sabre_gds_cancellation_reconciled';

    public const AUDIT_ACTION = 'booking.sabre_gds_cancellation_reconciled';

    /** @var list<string> */
    private const CONFIRMED_CLASSIFICATIONS = [
        SabreBookingCancelService::CLASSIFICATION_CANCEL_CONFIRMED,
        SabreBookingCancelService::CLASSIFICATION_CANCEL_CONFIRMED_AIR_SEGMENTS_REMOVED,
    ];

    public function __construct(
        private readonly BookingCommunicationService $communicationService,
        private readonly SabreGdsControlledCancelEvidenceService $controlledCancelEvidenceService,
    ) {}

    /**
     * @param  array<string, mixed>  $context
     * @return array<string, mixed>
     */
    public function reconcileFromStoredEvidence(Booking $booking, array $context = []): array
    {
        $booking->refresh();
        $meta = is_array($booking->meta) ? $booking->meta : [];

        if ($this->alreadyReconciled($booking, $meta)) {
            return [
                'success' => true,
                'already_reconciled' => true,
                'booking_id' => $booking->id,
                'status' => (string) ($booking->status->value ?? $booking->status),
                'supplier_booking_status' => (string) ($booking->supplier_booking_status ?? ''),
            ];
        }

        $evidence = $this->resolveConfirmedCancelEvidence($booking, $meta);
        if ($evidence === null) {
            return [
                'success' => false,
                'reason_code' => 'no_confirmed_cancel_evidence',
                'booking_id' => $booking->id,
            ];
        }

        $preservedPnr = trim((string) ($booking->pnr ?? ''));
        $preservedSupplierReference = trim((string) ($booking->supplier_reference ?? ''));

        return DB::transaction(function () use ($booking, $context, $evidence, $preservedPnr, $preservedSupplierReference): array {
            /** @var Booking $locked */
            $locked = Booking::query()->lockForUpdate()->findOrFail($booking->id);
            $lockedMeta = is_array($locked->meta) ? $locked->meta : [];

            if ($this->alreadyReconciled($locked, $lockedMeta)) {
                return [
                    'success' => true,
                    'already_reconciled' => true,
                    'booking_id' => $locked->id,
                ];
            }

            $fromStatus = $locked->status;
            $cancelledAt = $locked->cancelled_at ?? now();

            $lockedMeta[self::META_RECONCILED_KEY] = SensitiveDataRedactor::redact([
                'reconciled_at' => now()->toIso8601String(),
                'classification' => $evidence['classification'],
                'evidence_source' => $evidence['source'],
                'context_source' => (string) ($context['source'] ?? ''),
            ]);

            $locked->forceFill([
                'status' => BookingStatus::Cancelled,
                'supplier_booking_status' => 'cancelled',
                'cancellation_status' => BookingCancellationStatus::Cancelled->value,
                'cancelled_at' => $cancelledAt,
                'pnr' => $preservedPnr !== '' ? $preservedPnr : $locked->pnr,
                'supplier_reference' => $preservedSupplierReference !== '' ? $preservedSupplierReference : $locked->supplier_reference,
                'meta' => $lockedMeta,
            ])->save();

            SupplierBooking::query()
                ->where('booking_id', $locked->id)
                ->where('provider', SupplierProvider::Sabre->value)
                ->update(['status' => 'cancelled']);

            $this->writeStatusLogIfNeeded($locked, $fromStatus, $context);
            $this->writeAuditIfNeeded($locked, $evidence, $context);
            $this->communicationService->sendCancellationConfirmedIfNeeded($locked->fresh());

            return [
                'success' => true,
                'already_reconciled' => false,
                'booking_id' => $locked->id,
                'classification' => $evidence['classification'],
                'pnr_preserved' => $preservedPnr !== '',
                'supplier_reference_preserved' => $preservedSupplierReference !== '',
            ];
        });
    }

    /**
     * @param  array<string, mixed>  $meta
     */
    public function alreadyReconciled(Booking $booking, ?array $meta = null): bool
    {
        if ($booking->status === BookingStatus::Cancelled
            && $booking->cancelled_at !== null
            && strtolower(trim((string) ($booking->supplier_booking_status ?? ''))) === 'cancelled') {
            $meta = $meta ?? (is_array($booking->meta) ? $booking->meta : []);
            if (is_array($meta[self::META_RECONCILED_KEY] ?? null)) {
                return true;
            }
        }

        $meta = $meta ?? (is_array($booking->meta) ? $booking->meta : []);

        return is_array($meta[self::META_RECONCILED_KEY] ?? null)
            && trim((string) ($meta[self::META_RECONCILED_KEY]['reconciled_at'] ?? '')) !== '';
    }

    /**
     * @param  array<string, mixed>  $meta
     * @return array{classification: string, source: string}|null
     */
    protected function resolveConfirmedCancelEvidence(Booking $booking, array $meta): ?array
    {
        $cancelMeta = is_array($meta[SabreGdsCancelReadiness::META_KEY] ?? null)
            ? $meta[SabreGdsCancelReadiness::META_KEY]
            : [];

        $classification = trim((string) ($cancelMeta['classification'] ?? ''));
        if (($cancelMeta['supplier_cancel_verified'] ?? false) === true
            && $this->isConfirmedClassification($classification)) {
            return [
                'classification' => $classification,
                'source' => SabreGdsCancelReadiness::META_KEY,
            ];
        }

        $attempt = $this->latestSuccessfulCancelAttempt($booking);
        if ($attempt instanceof SupplierBookingAttempt) {
            $safe = is_array($attempt->safe_summary) ? $attempt->safe_summary : [];
            $attemptClassification = trim((string) ($safe['classification'] ?? ''));
            if ($this->isConfirmedClassification($attemptClassification)) {
                return [
                    'classification' => $attemptClassification,
                    'source' => 'supplier_booking_attempt',
                ];
            }
        }

        $legacyEvidence = is_array($meta[SabreGdsControlledCancelEvidenceService::META_KEY] ?? null)
            ? $meta[SabreGdsControlledCancelEvidenceService::META_KEY]
            : [];
        $legacyClassification = trim((string) ($legacyEvidence['classification'] ?? ''));
        if ($this->controlledCancelEvidenceService->hasRecordedEvidence($legacyEvidence)
            && $this->isConfirmedClassification($legacyClassification)) {
            return [
                'classification' => $legacyClassification,
                'source' => SabreGdsControlledCancelEvidenceService::META_KEY,
            ];
        }

        return null;
    }

    protected function isConfirmedClassification(string $classification): bool
    {
        return in_array(strtoupper(trim($classification)), self::CONFIRMED_CLASSIFICATIONS, true);
    }

    /**
     * @param  array<string, mixed>  $evidence
     * @param  array<string, mixed>  $context
     */
    protected function writeAuditIfNeeded(Booking $booking, array $evidence, array $context): void
    {
        $exists = AuditLog::query()
            ->where('auditable_type', Booking::class)
            ->where('auditable_id', $booking->id)
            ->where('action', self::AUDIT_ACTION)
            ->exists();
        if ($exists) {
            return;
        }

        $actor = $context['actor'] ?? null;

        AuditLog::query()->create([
            'agency_id' => $booking->agency_id,
            'user_id' => $actor instanceof User ? $actor->id : null,
            'action' => self::AUDIT_ACTION,
            'auditable_type' => Booking::class,
            'auditable_id' => $booking->id,
            'properties' => [
                'old_values' => [],
                'new_values' => SensitiveDataRedactor::redact([
                    'classification' => $evidence['classification'],
                    'evidence_source' => $evidence['source'],
                    'context_source' => (string) ($context['source'] ?? ''),
                ]),
            ],
        ]);
    }

    /**
     * @param  array<string, mixed>  $context
     */
    protected function writeStatusLogIfNeeded(Booking $booking, ?BookingStatus $fromStatus, array $context): void
    {
        $exists = $booking->statusLogs()
            ->where('to_status', BookingStatus::Cancelled->value)
            ->where('note', 'Sabre GDS cancellation reconciled from stored supplier evidence')
            ->exists();
        if ($exists) {
            return;
        }

        $actor = $context['actor'] ?? null;

        $booking->statusLogs()->create([
            'from_status' => $fromStatus?->value,
            'to_status' => BookingStatus::Cancelled->value,
            'user_id' => $actor instanceof User ? $actor->id : null,
            'note' => 'Sabre GDS cancellation reconciled from stored supplier evidence',
            'context' => SensitiveDataRedactor::redact([
                'source' => (string) ($context['source'] ?? 'sabre_gds_cancellation_reconciliation'),
                'run_id' => $context['run_id'] ?? null,
            ]),
        ]);
    }

    protected function latestSuccessfulCancelAttempt(Booking $booking): ?SupplierBookingAttempt
    {
        return SupplierBookingAttempt::query()
            ->where('booking_id', $booking->id)
            ->where('provider', SupplierProvider::Sabre->value)
            ->whereIn('action', ['cancel_booking', 'release_pnr', 'cancel_pnr'])
            ->where('status', 'success')
            ->orderByDesc('id')
            ->first();
    }
}
