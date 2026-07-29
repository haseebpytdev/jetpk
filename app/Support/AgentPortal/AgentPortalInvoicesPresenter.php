<?php

namespace App\Support\AgentPortal;

use App\Enums\BookingDocumentType;
use App\Models\Agent;
use App\Models\Booking;
use App\Models\BookingDocument;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;

/**
 * Agent invoice history JSON — booking invoices for agency bookings.
 */
class AgentPortalInvoicesPresenter
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
     * @return Builder<BookingDocument>
     */
    public function invoiceQuery(Agent $agent): Builder
    {
        return BookingDocument::query()
            ->where('document_type', BookingDocumentType::Invoice)
            ->whereHas('booking', fn (Builder $q) => $q->where('agent_id', $agent->id))
            ->with(['booking'])
            ->orderByDesc('generated_at')
            ->orderByDesc('created_at');
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
            'amount' => $booking ? AgentPortalStatusPresenter::bookingTotal($booking) : null,
            'currency' => (string) ($booking?->currency ?? 'PKR'),
            'payment_status' => $booking ? AgentPortalStatusPresenter::paymentStatus($booking) : null,
            'booking_status' => $booking ? AgentPortalStatusPresenter::bookingStatus($booking) : null,
            'agency_label' => $booking?->agent?->meta['agency_name'] ?? null,
            'pdf_available' => filled($document->file_path),
            'view_url' => $booking ? '/agent/bookings/'.$booking->booking_reference : null,
            'download_url' => filled($document->file_path)
                ? '/laravel/customer/documents/'.$document->id.'/download'
                : null,
            'print_url' => $booking ? '/agent/bookings/'.$booking->booking_reference : null,
        ];
    }
}
