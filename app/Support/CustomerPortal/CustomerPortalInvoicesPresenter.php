<?php

namespace App\Support\CustomerPortal;

use App\Enums\BookingDocumentType;
use App\Models\Booking;
use App\Models\BookingDocument;
use App\Models\User;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;

/**
 * Customer invoice history JSON presenter.
 */
class CustomerPortalInvoicesPresenter
{
    /**
     * @return array<string, mixed>
     */
    public function presentIndex(LengthAwarePaginator $documents): array
    {
        return [
            'ok' => true,
            'invoices' => collect($documents->items())
                ->map(fn (BookingDocument $document) => $this->presentListItem($document))
                ->values()
                ->all(),
            'pagination' => [
                'current_page' => $documents->currentPage(),
                'last_page' => $documents->lastPage(),
                'per_page' => $documents->perPage(),
                'total' => $documents->total(),
                'from' => $documents->firstItem(),
                'to' => $documents->lastItem(),
            ],
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function presentListItem(BookingDocument $document): array
    {
        $booking = $document->booking;

        return [
            'invoice_number' => filled($document->document_number)
                ? (string) $document->document_number
                : ($booking?->display_reference),
            'booking_reference' => $booking?->display_reference,
            'issue_date' => ($document->generated_at ?? $document->created_at)?->toDateString(),
            'amount' => $booking ? CustomerPortalStatusPresenter::customerPayable($booking) : null,
            'currency' => (string) ($booking?->currency ?? 'PKR'),
            'payment_status' => $booking ? CustomerPortalStatusPresenter::paymentStatus($booking) : null,
            'booking_status' => $booking ? CustomerPortalStatusPresenter::bookingStatus($booking) : null,
            'pdf_available' => filled($document->file_path),
            'view_url' => $booking ? '/customer/invoices/'.$booking->booking_reference : null,
            'download_url' => filled($document->file_path)
                ? '/laravel/customer/documents/'.$document->id.'/download'
                : null,
            'print_url' => $booking ? '/customer/invoices/'.$booking->booking_reference : null,
        ];
    }

    /**
     * @return Builder<BookingDocument>
     */
    public function invoiceQuery(User $user): Builder
    {
        return BookingDocument::query()
            ->where('document_type', BookingDocumentType::Invoice)
            ->whereHas('booking', fn (Builder $q) => $q->where('customer_id', $user->id))
            ->with(['booking'])
            ->orderByDesc('generated_at')
            ->orderByDesc('created_at');
    }

    /**
     * @return array<string, mixed>
     */
    public function presentDetail(Booking $booking): array
    {
        $document = $booking->documents
            ->first(fn (BookingDocument $doc) => $doc->document_type === BookingDocumentType::Invoice);

        return [
            'ok' => true,
            'invoice_number' => filled($document?->document_number)
                ? (string) $document->document_number
                : $booking->display_reference,
            'booking_reference' => $booking->display_reference,
            'issue_date' => ($document?->generated_at ?? $booking->created_at)?->toDateString(),
            'amount' => CustomerPortalStatusPresenter::customerPayable($booking),
            'currency' => (string) ($booking->currency ?? 'PKR'),
            'payment_status' => CustomerPortalStatusPresenter::paymentStatus($booking),
            'booking_status' => CustomerPortalStatusPresenter::bookingStatus($booking),
            'pdf_available' => $document !== null && filled($document->file_path),
            'download_url' => $document !== null && filled($document->file_path)
                ? '/laravel/customer/documents/'.$document->id.'/download'
                : null,
            'booking_detail_url' => '/customer/bookings/'.$booking->booking_reference,
        ];
    }
}
