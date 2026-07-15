<?php

namespace App\Services\Documents;

use App\Enums\BookingDocumentStatus;
use App\Enums\BookingDocumentType;
use App\Models\AuditLog;
use App\Models\Booking;
use App\Models\BookingDocument;
use App\Models\BookingPayment;
use App\Models\BookingRefund;
use App\Models\User;
use App\Services\Communication\BookingCommunicationService;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use RuntimeException;
use Throwable;

class BookingDocumentService
{
    public function __construct(
        protected BookingCommunicationService $communicationService,
    ) {}

    public function generateBookingConfirmation(Booking $booking, User $actor): BookingDocument
    {
        return $this->generate(
            booking: $booking->fresh(['agency.agencySetting', 'contact', 'passengers', 'fareBreakdown']),
            actor: $actor,
            type: BookingDocumentType::BookingConfirmation,
            title: 'Booking Confirmation',
            documentNumber: 'BC-'.($booking->booking_reference ?: (string) $booking->id),
            view: 'pdf.booking-confirmation',
        );
    }

    public function generatePaymentReceipt(BookingPayment $payment, User $actor): BookingDocument
    {
        $payment = $payment->fresh(['booking.agency.agencySetting', 'booking.contact', 'booking.passengers', 'booking.fareBreakdown']);
        if ($payment->status->value !== 'verified') {
            throw new RuntimeException('Payment receipt can only be generated for verified payments.');
        }

        return $this->generate(
            booking: $payment->booking,
            actor: $actor,
            type: BookingDocumentType::PaymentReceipt,
            title: 'Payment Receipt',
            documentNumber: 'RCPT-'.$payment->id.'-'.now()->format('Ymd'),
            view: 'pdf.payment-receipt',
            bookingPayment: $payment,
        );
    }

    public function generateTicketItinerary(Booking $booking, User $actor): BookingDocument
    {
        $booking = $booking->fresh(['agency.agencySetting', 'contact', 'passengers', 'fareBreakdown', 'tickets.passenger']);
        if ($booking->tickets->isEmpty()) {
            throw new RuntimeException('Ticket itinerary requires issued tickets.');
        }

        $document = $this->generate(
            booking: $booking,
            actor: $actor,
            type: BookingDocumentType::TicketItinerary,
            title: 'Ticket Itinerary',
            documentNumber: 'TKT-'.($booking->booking_reference ?: (string) $booking->id),
            view: 'pdf.ticket-itinerary',
        );

        try {
            $this->communicationService->sendItineraryReady($booking->fresh(), $document, $actor);
        } catch (Throwable $e) {
            report($e);
        }

        return $document;
    }

    public function generateInvoice(Booking $booking, User $actor): BookingDocument
    {
        $booking = $booking->fresh(['agency.agencySetting', 'contact', 'passengers', 'fareBreakdown']);
        if ($booking->fareBreakdown === null || (float) ($booking->fareBreakdown->total ?? 0) <= 0) {
            throw new RuntimeException('Invoice requires fare snapshot with total amount.');
        }

        return $this->generate(
            booking: $booking,
            actor: $actor,
            type: BookingDocumentType::Invoice,
            title: 'Invoice',
            documentNumber: 'INV-'.($booking->booking_reference ?: (string) $booking->id),
            view: 'pdf.invoice',
        );
    }

    public function generateRefundNote(Booking $booking, User $actor): BookingDocument
    {
        $booking = $booking->fresh(['agency.agencySetting', 'contact', 'passengers', 'fareBreakdown', 'refunds']);
        $eligibleRefund = $booking->refunds
            ->filter(fn (BookingRefund $refund) => in_array((string) $refund->status->value, ['approved', 'paid'], true))
            ->sortByDesc('created_at')
            ->first();
        if ($eligibleRefund === null) {
            throw new RuntimeException('Refund note requires approved or paid refund record.');
        }

        return $this->generate(
            booking: $booking,
            actor: $actor,
            type: BookingDocumentType::RefundNote,
            title: 'Refund Note',
            documentNumber: 'RFD-'.($booking->booking_reference ?: (string) $booking->id).'-'.$eligibleRefund->id,
            view: 'pdf.refund-note',
        );
    }

    public function generateCancellationConfirmation(Booking $booking, User $actor): BookingDocument
    {
        $booking = $booking->fresh(['agency.agencySetting', 'contact', 'passengers', 'fareBreakdown', 'cancellationRequests']);
        $processedCancellation = $booking->cancellationRequests
            ->first(fn ($c) => (string) ($c->status->value ?? '') === 'processed');
        if ($processedCancellation === null) {
            throw new RuntimeException('Cancellation confirmation requires processed cancellation.');
        }

        return $this->generate(
            booking: $booking,
            actor: $actor,
            type: BookingDocumentType::CancellationConfirmation,
            title: 'Cancellation Confirmation',
            documentNumber: 'CC-'.($booking->booking_reference ?: (string) $booking->id).'-'.$processedCancellation->id,
            view: 'pdf.cancellation-confirmation',
        );
    }

    protected function generate(
        Booking $booking,
        User $actor,
        BookingDocumentType $type,
        string $title,
        string $documentNumber,
        string $view,
        ?BookingPayment $bookingPayment = null,
    ): BookingDocument {
        try {
            $existing = BookingDocument::query()
                ->where('booking_id', $booking->id)
                ->where('booking_payment_id', $bookingPayment?->id)
                ->where('document_type', $type)
                ->where('status', BookingDocumentStatus::Generated)
                ->where('created_at', '>=', now()->subMinutes(2))
                ->latest('id')
                ->first();
            if ($existing !== null) {
                return $existing;
            }

            if (! class_exists(Pdf::class)) {
                throw new RuntimeException('PDF package is not available.');
            }

            $binary = Pdf::loadView($view, [
                'booking' => $booking,
                'payment' => $bookingPayment,
                'documentNumber' => $documentNumber,
                'generatedAt' => now(),
            ])->output();

            return DB::transaction(function () use ($booking, $actor, $type, $title, $documentNumber, $binary, $bookingPayment): BookingDocument {
                $directory = 'private/agency-'.$booking->agency_id.'/bookings/'.$booking->id.'/documents';
                $filename = strtolower($type->value).'-'.now()->format('YmdHis').'.pdf';
                $path = $directory.'/'.$filename;
                Storage::disk('local')->put($path, $binary);

                $document = BookingDocument::query()->create([
                    'agency_id' => $booking->agency_id,
                    'booking_id' => $booking->id,
                    'booking_payment_id' => $bookingPayment?->id,
                    'document_type' => $type,
                    'document_number' => $documentNumber,
                    'title' => $title,
                    'file_path' => $path,
                    'status' => BookingDocumentStatus::Generated,
                    'generated_by' => $actor->id,
                    'generated_at' => now(),
                    'meta' => ['source' => 'pdf_service'],
                ]);

                $this->writeAudit($booking, $actor, 'booking.document_generated', [
                    'booking_document_id' => $document->id,
                    'type' => $type->value,
                    'number' => $documentNumber,
                ]);
                $this->communicationService->logSystemEvent($booking, 'document_generated', [
                    'document_id' => $document->id,
                    'document_type' => $type->value,
                ]);

                return $document;
            });
        } catch (Throwable $e) {
            $failed = BookingDocument::query()->create([
                'agency_id' => $booking->agency_id,
                'booking_id' => $booking->id,
                'booking_payment_id' => $bookingPayment?->id,
                'document_type' => $type,
                'document_number' => $documentNumber,
                'title' => $title,
                'status' => BookingDocumentStatus::Failed,
                'generated_by' => $actor->id,
                'generated_at' => now(),
                'meta' => ['error' => $e->getMessage()],
            ]);
            $this->writeAudit($booking, $actor, 'booking.document_generation_failed', [
                'booking_document_id' => $failed->id,
                'type' => $type->value,
            ]);

            throw new RuntimeException('Document generation failed.');
        }
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
