<?php

namespace App\Support\Sabre\Scenario;

use App\Enums\BookingCancellationStatus;
use App\Enums\SupplierProvider;
use App\Models\Booking;
use App\Models\SupplierBooking;
use App\Services\Suppliers\Sabre\Cancel\SabreGdsCancellationReconciliationService;
use App\Support\Security\SensitiveDataRedactor;
use Illuminate\Support\Facades\DB;

/**
 * Atomic local booking + supplier booking closure after verified post-cancel retrieve (no supplier HTTP).
 */
final class SabreGdsQrUnticketedPostCancelLocalClosureService
{
    public function __construct(
        private readonly SabreGdsCancellationReconciliationService $reconciliationService,
    ) {}

    /**
     * @param  array<string, mixed>  $context
     * @return array<string, mixed>
     */
    public function applyVerifiedPostCancelClosure(Booking $booking, int $supplierBookingId, array $context = []): array
    {
        $reconcile = $this->reconciliationService->reconcileFromStoredEvidence($booking, array_merge($context, [
            'source' => 'qr_unticketed_post_cancel_retrieve',
        ]));

        if (($reconcile['success'] ?? false) !== true) {
            return array_merge($reconcile, ['closure_applied' => false]);
        }

        return DB::transaction(function () use ($booking, $supplierBookingId, $context, $reconcile): array {
            /** @var Booking $locked */
            $locked = Booking::query()->lockForUpdate()->findOrFail($booking->id);
            $preservedPnr = trim((string) ($locked->pnr ?? ''));
            $preservedSupplierReference = trim((string) ($locked->supplier_reference ?? ''));
            $meta = is_array($locked->meta) ? $locked->meta : [];

            $locked->forceFill([
                'cancellation_status' => BookingCancellationStatus::Cancelled->value,
                'pnr' => $preservedPnr !== '' ? $preservedPnr : $locked->pnr,
                'supplier_reference' => $preservedSupplierReference !== '' ? $preservedSupplierReference : $locked->supplier_reference,
            ])->save();

            $meta['qr_unticketed_post_cancel_retrieve'] = SensitiveDataRedactor::redact(array_merge(
                is_array($meta['qr_unticketed_post_cancel_retrieve'] ?? null) ? $meta['qr_unticketed_post_cancel_retrieve'] : [],
                [
                    'cancellation_closure_verified' => true,
                    'closed_at' => now()->toIso8601String(),
                    'lifecycle_run_id' => (string) ($context['lifecycle_run_id'] ?? ''),
                    'prior_cancellation_lifecycle_run_id' => (string) ($context['prior_cancellation_lifecycle_run_id'] ?? ''),
                ],
            ));
            $locked->forceFill(['meta' => $meta])->save();

            $supplierBooking = SupplierBooking::query()
                ->where('id', $supplierBookingId)
                ->where('booking_id', $locked->id)
                ->where('provider', SupplierProvider::Sabre->value)
                ->lockForUpdate()
                ->first();

            if ($supplierBooking !== null) {
                $sbMeta = is_array($supplierBooking->raw_summary) ? $supplierBooking->raw_summary : [];
                $sbMeta['sabre_gds_cancellation_confirmed'] = SensitiveDataRedactor::redact([
                    'confirmed_at' => now()->toIso8601String(),
                    'source' => 'qr_unticketed_post_cancel_retrieve',
                    'lifecycle_run_id' => (string) ($context['lifecycle_run_id'] ?? ''),
                ]);
                $supplierBooking->forceFill([
                    'status' => 'cancelled',
                    'raw_summary' => $sbMeta,
                ])->save();
            }

            return [
                'success' => true,
                'closure_applied' => true,
                'already_reconciled' => ($reconcile['already_reconciled'] ?? false) === true,
                'booking_id' => $locked->id,
                'supplier_booking_id' => $supplierBookingId,
                'booking_status' => (string) ($locked->status->value ?? $locked->status),
                'booking_cancellation_status' => (string) ($locked->cancellation_status ?? ''),
                'supplier_booking_status' => (string) ($locked->supplier_booking_status ?? ''),
                'pnr_preserved' => $preservedPnr !== '',
            ];
        });
    }
}
