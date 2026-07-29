<?php

namespace App\Support\CustomerPortal;

use App\Enums\BookingCancellationStatus;
use App\Enums\BookingDocumentType;
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
        $payload['actions'] = $this->presentCustomerActions($booking);
        $payload['return_url'] = '/customer/bookings';

        return $payload;
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function presentCustomerActions(Booking $booking): array
    {
        $reference = $booking->booking_reference;
        $actions = [
            [
                'code' => 'view_invoice',
                'label' => 'View invoice',
                'available' => true,
                'url' => '/customer/invoices/'.$reference,
            ],
            [
                'code' => 'contact_support',
                'label' => 'Contact support',
                'available' => true,
                'url' => '/customer/support',
            ],
            [
                'code' => 'back_to_bookings',
                'label' => 'Back to bookings',
                'available' => true,
                'url' => '/customer/bookings',
            ],
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

        if ($booking->status !== BookingStatus::Cancelled) {
            $openCancellation = $booking->cancellationRequests->contains(
                fn ($row) => in_array($row->status->value, [
                    BookingCancellationStatus::Requested->value,
                    BookingCancellationStatus::Approved->value,
                ], true),
            );

            $actions[] = [
                'code' => 'request_cancellation',
                'label' => 'Request cancellation',
                'available' => ! $openCancellation,
                'url' => '/laravel/customer/bookings/'.$reference.'/cancellations',
                'reason_unavailable' => $openCancellation ? 'A cancellation request is already in progress.' : null,
            ];
        }

        return $actions;
    }
}
