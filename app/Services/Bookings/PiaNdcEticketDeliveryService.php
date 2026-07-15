<?php

namespace App\Services\Bookings;

use App\Enums\SupplierProvider;
use App\Models\AuditLog;
use App\Models\Booking;
use App\Models\User;
use App\Services\Communication\BookingCommunicationService;
use App\Services\Documents\BookingDocumentService;
use App\Services\Suppliers\PiaNdc\PiaNdcOrderOperationPreflight;
use App\Support\Bookings\PiaNdcVoidLocalReconciliation;
use Throwable;

/**
 * PIA NDC customer e-ticket PDF + email delivery after live ticketing (R12S).
 *
 * Does not call supplier APIs. Skips delivery on preview, dry-run, void, release, and failed ticketing.
 */
class PiaNdcEticketDeliveryService
{
    public const RESEND_CONFIRM_PHRASE = 'RESEND_PIA_ETICKET';

    public function __construct(
        private readonly BookingDocumentService $documentService,
        private readonly BookingCommunicationService $communicationService,
        private readonly PiaNdcOrderOperationPreflight $preflight,
    ) {}

    /**
     * @return array{sent: bool, message: string, document_id?: int}
     */
    public function deliverAfterSuccessfulTicketing(Booking $booking, User $actor): array
    {
        if (! $this->isPiaNdcBooking($booking)) {
            return ['sent' => false, 'message' => 'Not a PIA NDC booking.'];
        }

        if ($this->isVoided($booking)) {
            return ['sent' => false, 'message' => 'E-ticket email is not sent for voided tickets.'];
        }

        $booking->loadMissing(['tickets', 'contact', 'passengers', 'fareBreakdown']);
        if ($booking->tickets->isEmpty()) {
            return ['sent' => false, 'message' => 'No issued ticket records are available for e-ticket delivery.'];
        }

        try {
            $document = $this->documentService->generateTicketItinerary($booking->fresh(), $actor);
            $this->communicationService->notifyTicketIssuedOperationalOnly($booking->fresh());

            $this->writeAudit($booking, $actor, 'booking.pia_ndc_eticket_delivered', [
                'booking_document_id' => $document->id,
                'source' => 'ticketing_success',
            ]);

            $this->communicationService->logSystemEvent($booking, 'pia_ndc_eticket_delivered', [
                'booking_document_id' => $document->id,
                'source' => 'ticketing_success',
            ]);

            return [
                'sent' => true,
                'message' => 'E-ticket itinerary PDF generated and emailed to the customer.',
                'document_id' => $document->id,
            ];
        } catch (Throwable $e) {
            report($e);

            return [
                'sent' => false,
                'message' => 'E-ticket PDF/email could not be delivered. Ticketing records were not rolled back.',
            ];
        }
    }

    /**
     * @return array{sent: bool, message: string, document_id?: int}
     */
    public function resend(Booking $booking, User $actor, ?string $note = null): array
    {
        if (! $this->canResend($booking)) {
            return [
                'sent' => false,
                'message' => $this->resendBlockedReason($booking) ?? 'E-ticket resend is not available for this booking.',
            ];
        }

        $booking->loadMissing(['tickets', 'contact', 'passengers', 'fareBreakdown', 'documents']);

        try {
            $document = $this->documentService->generateTicketItinerary($booking->fresh(), $actor);

            $this->writeAudit($booking, $actor, 'booking.pia_ndc_eticket_resent', [
                'booking_document_id' => $document->id,
                'source' => 'admin_resend',
                'operator_note' => $note,
            ]);

            $this->communicationService->logSystemEvent($booking, 'pia_ndc_eticket_resent', [
                'booking_document_id' => $document->id,
                'source' => 'admin_resend',
                'operator_note' => $note,
            ]);

            return [
                'sent' => true,
                'message' => 'E-ticket email resent with PDF attachment.',
                'document_id' => $document->id,
            ];
        } catch (Throwable $e) {
            report($e);

            return [
                'sent' => false,
                'message' => 'E-ticket resend failed. The booking was not affected.',
            ];
        }
    }

    public function canResend(Booking $booking): bool
    {
        return $this->resendBlockedReason($booking) === null;
    }

    public function resendBlockedReason(Booking $booking): ?string
    {
        if (! $this->isPiaNdcBooking($booking)) {
            return 'Not a PIA NDC booking.';
        }

        if ($this->isVoided($booking)) {
            return 'Voided tickets cannot be sent.';
        }

        if (! $this->hasTicketArtifacts($booking)) {
            return 'No issued tickets are available for e-ticket resend.';
        }

        $email = trim((string) ($booking->contact?->email ?? $booking->customer?->email ?? ''));
        if ($email === '') {
            return 'Booking contact email is required for e-ticket resend.';
        }

        return null;
    }

    protected function hasTicketArtifacts(Booking $booking): bool
    {
        $booking->loadMissing('tickets');

        if ($booking->tickets->isNotEmpty()) {
            return true;
        }

        $meta = is_array($booking->meta) ? $booking->meta : [];
        $context = is_array($meta['pia_ndc_context'] ?? null) ? $meta['pia_ndc_context'] : [];

        return $this->preflight->realTicketNumbersPresent($context);
    }

    protected function isVoided(Booking $booking): bool
    {
        return PiaNdcVoidLocalReconciliation::isVoided($booking);
    }

    protected function isPiaNdcBooking(Booking $booking): bool
    {
        $meta = is_array($booking->meta) ? $booking->meta : [];
        $provider = strtolower(trim((string) ($meta['supplier_provider'] ?? $booking->supplier ?? '')));

        return $provider === SupplierProvider::PiaNdc->value;
    }

    /**
     * @param  array<string, mixed>  $newValues
     */
    protected function writeAudit(Booking $booking, User $actor, string $action, array $newValues): void
    {
        AuditLog::query()->create([
            'agency_id' => $booking->agency_id,
            'user_id' => $actor->id,
            'action' => $action,
            'auditable_type' => Booking::class,
            'auditable_id' => $booking->id,
            'properties' => [
                'old_values' => [],
                'new_values' => $newValues,
            ],
        ]);
    }
}
