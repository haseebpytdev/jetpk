<?php

namespace App\Support\CustomerPortal;

use App\Enums\BookingCancellationStatus;
use App\Enums\BookingDocumentType;
use App\Enums\BookingRefundStatus;
use App\Enums\BookingStatus;
use App\Models\Booking;
use App\Support\Booking\StandardBookingJsonPresenter;
use App\Support\Bookings\BookingSupplierConfirmationNoticeResolver;
use App\Support\Payments\PublicAbhiPayCheckoutPresenter;
use Illuminate\Http\Request;

/**
 * Customer portal booking detail JSON — reuses JP-FE-10 standard booking presenter.
 */
class CustomerPortalBookingDetailPresenter
{
    public function __construct(
        protected StandardBookingJsonPresenter $standardPresenter,
        protected PublicAbhiPayCheckoutPresenter $abhiPayPresenter,
    ) {}

    /**
     * @return array<string, mixed>
     */
    public function present(Booking $booking, Request $request): array
    {
        $booking->loadMissing([
            'passengers',
            'contact',
            'fareBreakdown',
            'payments',
            'tickets.passenger',
            'documents',
            'supplierBookings',
            'cancellationRequests',
            'refunds',
        ]);

        $meta = is_array($booking->meta) ? $booking->meta : [];
        $offer = is_array($meta['flight_offer_snapshot'] ?? null) ? $meta['flight_offer_snapshot'] : null;
        $criteria = is_array($meta['search_criteria'] ?? null) ? $meta['search_criteria'] : [
            'origin' => '',
            'destination' => '',
            'depart_date' => $booking->travel_date?->format('Y-m-d'),
            'trip_type' => 'one_way',
        ];

        $leadPassenger = $booking->passengers->firstWhere('is_lead_passenger', true) ?? $booking->passengers->first();
        $contact = $booking->contact;

        $draft = [
            'flight_id' => is_array($offer) ? ($offer['id'] ?? '') : '',
            'booking_reference' => $booking->booking_reference,
            'booking_method' => $meta['booking_method'] ?? 'pay_later',
            'title' => $leadPassenger?->title,
            'first_name' => $leadPassenger?->first_name,
            'last_name' => $leadPassenger?->last_name,
            'email' => $contact?->email,
            'phone' => $contact?->phone,
            'country' => $contact?->country,
            'search_from' => $criteria['origin'] ?? '',
            'search_to' => $criteria['destination'] ?? '',
            'search_depart' => $criteria['depart_date'] ?? '',
        ];

        $abhiPayCheckout = $this->abhiPayPresenter->forBooking($booking, afterSubmission: true);
        $supplierConfirmationNotice = BookingSupplierConfirmationNoticeResolver::resolveForBooking(
            $booking,
            is_array($meta['sabre_checkout_outcome'] ?? null) ? $meta['sabre_checkout_outcome'] : null,
            null,
        );

        $viewData = [
            'draft' => $draft,
            'offer' => $offer,
            'criteria' => $criteria,
            'booking' => $booking,
            'abhiPayCheckout' => $abhiPayCheckout,
            'guestAbhiPayToken' => null,
            'supplierConfirmationNotice' => $supplierConfirmationNotice,
        ];

        $payload = $this->standardPresenter->presentConfirmation($viewData, $request);
        if (($payload['ok'] ?? false) !== true) {
            return $payload;
        }

        $payload['source'] = 'customer_portal';
        $payload['booking'] = [
            'id' => $booking->id,
            'booking_reference' => $booking->display_reference,
            'status' => (string) ($booking->status?->value ?? $booking->status ?? ''),
        ];
        $payload['actions'] = $this->presentCustomerActions($booking);
        $payload['capabilities'] = $this->presentCapabilities($booking);
        $payload['cancellation'] = $this->presentCancellationSummary($booking);
        $payload['refund'] = $this->presentRefundSummary($booking);
        $payload['return_url'] = '/customer/bookings';

        return $payload;
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function presentCustomerActions(Booking $booking): array
    {
        $detail = CustomerPortalBookingUrl::detailPath($booking);
        $reference = trim((string) ($booking->booking_reference ?? ''));
        $actions = [];

        if ($booking->status === BookingStatus::Draft) {
            $actions[] = [
                'code' => 'resume_checkout',
                'label' => 'Resume checkout',
                'available' => true,
                'url' => CustomerPortalBookingUrl::resumePath($booking),
            ];
            $actions[] = [
                'code' => 'view_draft',
                'label' => 'View draft',
                'available' => true,
                'url' => $detail,
            ];
        }

        $actions[] = [
            'code' => 'view_invoice',
            'label' => 'View invoice',
            'available' => $reference !== '',
            'url' => $reference !== '' ? '/customer/invoices/'.$reference : null,
        ];
        $actions[] = [
            'code' => 'contact_support',
            'label' => 'Contact support',
            'available' => true,
            'url' => '/customer/support',
        ];
        $actions[] = [
            'code' => 'back_to_bookings',
            'label' => 'Back to bookings',
            'available' => true,
            'url' => '/customer/bookings',
        ];

        $invoiceDoc = $booking->documents
            ->first(fn ($doc) => $doc->document_type === BookingDocumentType::Invoice && filled($doc->file_path));

        if ($invoiceDoc !== null) {
            $actions[] = [
                'code' => 'download_invoice',
                'label' => 'Download invoice',
                'available' => true,
                'url' => '/laravel/customer/documents/'.$invoiceDoc->id.'/download',
            ];
        }

        return $actions;
    }

    /**
     * @return array<string, mixed>
     */
    private function presentCapabilities(Booking $booking): array
    {
        $reference = $booking->booking_reference;
        $openCancellation = $this->hasOpenCancellationRequest($booking);
        $invoiceDoc = $booking->documents
            ->first(fn ($doc) => $doc->document_type === BookingDocumentType::Invoice && filled($doc->file_path));
        $ticketDoc = $booking->documents
            ->first(fn ($doc) => in_array($doc->document_type, [BookingDocumentType::TicketItinerary, BookingDocumentType::BookingConfirmation], true)
                && filled($doc->file_path));
        $hasTickets = $booking->tickets->isNotEmpty();
        $canCancel = $booking->status !== BookingStatus::Cancelled && ! $openCancellation;

        return [
            'can_view' => true,
            'can_download_invoice' => $invoiceDoc !== null,
            'can_download_ticket' => $ticketDoc !== null || $hasTickets,
            'can_request_cancellation' => $canCancel,
            'can_view_cancellation' => $booking->cancellationRequests->isNotEmpty(),
            'can_request_refund' => false,
            'can_view_refund' => $booking->refunds->isNotEmpty(),
            'can_retry_payment' => false,
            'can_contact_support' => true,
            'reason_codes' => [
                'can_download_invoice' => $invoiceDoc === null ? 'document_not_ready' : null,
                'can_download_ticket' => (! $ticketDoc && ! $hasTickets) ? 'ticket_not_issued' : null,
                'can_request_cancellation' => $booking->status === BookingStatus::Cancelled
                    ? 'booking_not_cancellable'
                    : ($openCancellation ? 'cancellation_already_requested' : null),
                'can_request_refund' => 'customer_refund_request_unavailable',
            ],
            'mutation_urls' => [
                'request_cancellation' => $canCancel
                    ? '/laravel/customer/bookings/'.$reference.'/cancellations'
                    : null,
            ],
            'download_urls' => [
                'invoice' => $invoiceDoc !== null
                    ? '/laravel/customer/documents/'.$invoiceDoc->id.'/download'
                    : null,
                'ticket' => $ticketDoc !== null
                    ? '/laravel/customer/documents/'.$ticketDoc->id.'/download'
                    : null,
            ],
            'navigation_urls' => [
                'view_invoice' => '/customer/invoices/'.$reference,
                'contact_support' => '/customer/support',
                'back_to_bookings' => '/customer/bookings',
            ],
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function presentCancellationSummary(Booking $booking): array
    {
        $latest = $booking->cancellationRequests->sortByDesc('id')->first();
        $open = $this->hasOpenCancellationRequest($booking);

        if ($booking->status === BookingStatus::Cancelled) {
            return [
                'state' => 'cancelled',
                'label' => 'Cancelled',
                'message' => 'This booking has been cancelled.',
                'request' => $latest !== null ? $this->presentCancellationRequestRow($latest) : null,
            ];
        }

        if ($latest !== null && $latest->status === BookingCancellationStatus::Rejected) {
            return [
                'state' => 'rejected',
                'label' => 'Cancellation rejected',
                'message' => 'Your cancellation request was not approved.',
                'request' => $this->presentCancellationRequestRow($latest),
            ];
        }

        if ($open) {
            $status = (string) ($latest?->status->value ?? 'requested');

            return [
                'state' => $status === 'approved' ? 'under_review' : 'request_submitted',
                'label' => $status === 'approved' ? 'Under review' : 'Request submitted',
                'message' => 'Your cancellation request is being reviewed. This does not mean the booking is cancelled yet.',
                'request' => $latest !== null ? $this->presentCancellationRequestRow($latest) : null,
            ];
        }

        if ($booking->status !== BookingStatus::Cancelled) {
            return [
                'state' => 'available',
                'label' => 'Cancellation available',
                'message' => 'You can submit a cancellation request for review.',
                'request' => null,
            ];
        }

        return [
            'state' => 'unavailable',
            'label' => 'Cancellation unavailable',
            'message' => 'Cancellation is not available for this booking.',
            'request' => null,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function presentRefundSummary(Booking $booking): array
    {
        $latest = $booking->refunds->sortByDesc('id')->first();

        if ($latest === null) {
            return [
                'state' => 'not_eligible',
                'label' => 'No refund on file',
                'message' => 'Refund requests are handled by our support team after cancellation review.',
                'can_request' => false,
                'request' => null,
            ];
        }

        $status = (string) ($latest->status->value ?? $latest->status);
        $state = match ($status) {
            BookingRefundStatus::Paid->value, 'paid', 'refunded' => 'paid',
            BookingRefundStatus::Rejected->value, 'rejected' => 'rejected',
            BookingRefundStatus::Approved->value, 'approved' => 'approved',
            default => 'under_review',
        };

        return [
            'state' => $state,
            'label' => ucfirst(str_replace('_', ' ', $status)),
            'message' => 'Refund status is updated by our team. Submission does not guarantee payment.',
            'can_request' => false,
            'request' => [
                'id' => $latest->id,
                'status' => $status,
                'status_label' => ucfirst(str_replace('_', ' ', $status)),
                'amount' => $latest->amount,
                'currency' => $latest->currency,
                'updated_at' => ($latest->updated_at ?? $latest->created_at)?->toIso8601String(),
            ],
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function presentCancellationRequestRow($request): array
    {
        return [
            'id' => $request->id,
            'status' => (string) ($request->status->value ?? $request->status),
            'status_label' => ucfirst(str_replace('_', ' ', (string) ($request->status->value ?? $request->status))),
            'requested_at' => $request->created_at?->toIso8601String(),
        ];
    }

    private function hasOpenCancellationRequest(Booking $booking): bool
    {
        return $booking->cancellationRequests->contains(
            fn ($row) => in_array($row->status->value, [
                BookingCancellationStatus::Requested->value,
                BookingCancellationStatus::Approved->value,
            ], true),
        );
    }
}
