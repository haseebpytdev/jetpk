<?php

namespace App\Support\GuestBooking;

use App\Enums\BookingCancellationStatus;
use App\Enums\BookingDocumentType;
use App\Enums\BookingStatus;
use App\Models\Booking;
use App\Support\Booking\StandardBookingJsonPresenter;
use App\Support\Bookings\BookingPaymentSummaryPresenter;
use App\Support\Bookings\BookingSupplierConfirmationNoticeResolver;
use App\Support\Payments\PublicAbhiPayCheckoutPresenter;
use App\Support\Travel\TravelDocumentFormatter;
use Illuminate\Http\Request;

/**
 * Guest lookup booking detail JSON — token-scoped, customer-safe fields only.
 */
class GuestBookingDetailPresenter
{
    public function __construct(
        protected StandardBookingJsonPresenter $standardPresenter,
        protected PublicAbhiPayCheckoutPresenter $abhiPayPresenter,
    ) {}

    /**
     * @return array<string, mixed>
     */
    public function present(Booking $booking, string $token, Request $request): array
    {
        $booking->loadMissing([
            'passengers',
            'contact',
            'fareBreakdown',
            'statusLogs',
            'payments',
            'tickets.passenger',
            'documents',
            'communicationLogs',
            'cancellationRequests.requester',
            'refunds',
            'supplierBookings',
        ]);

        $meta = is_array($booking->meta) ? $booking->meta : [];
        $hasPnr = filled($booking->pnr)
            || $booking->supplierBookings->contains(fn ($sb) => filled($sb->pnr));
        $hasLinkedAccount = filled($booking->customer_id);
        $canUploadProof = ! $hasLinkedAccount
            && $booking->status !== BookingStatus::Cancelled
            && BookingPaymentSummaryPresenter::canUploadProof($booking, true);

        $openCancellation = $booking->cancellationRequests->contains(
            fn ($r) => in_array($r->status->value, [
                BookingCancellationStatus::Requested->value,
                BookingCancellationStatus::Approved->value,
            ], true),
        );
        $canRequestCancellation = ! $hasLinkedAccount
            && $booking->status !== BookingStatus::Cancelled
            && ! $openCancellation;

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

        $viewData = [
            'draft' => $draft,
            'offer' => $offer,
            'criteria' => $criteria,
            'booking' => $booking,
            'abhiPayCheckout' => $this->abhiPayPresenter->forBooking($booking, afterSubmission: true),
            'guestAbhiPayToken' => $token,
            'supplierConfirmationNotice' => BookingSupplierConfirmationNoticeResolver::resolveForBooking(
                $booking,
                is_array($meta['sabre_checkout_outcome'] ?? null) ? $meta['sabre_checkout_outcome'] : null,
                null,
            ),
        ];

        $payload = $this->standardPresenter->presentConfirmation($viewData, $request);
        if (($payload['ok'] ?? false) !== true) {
            return $payload;
        }

        $payload['source'] = 'guest_lookup';
        $payload['viewer_mode'] = 'guest';
        $payload['passengers'] = $this->presentMaskedPassengers($booking);
        $payload['contact'] = [
            'email_masked' => TravelDocumentFormatter::maskEmail($contact?->email),
            'phone_masked' => TravelDocumentFormatter::maskPhone($contact?->phone),
        ];
        $payload['capabilities'] = $this->presentCapabilities($booking, $token, $canUploadProof, $canRequestCancellation);
        $payload['cancellation'] = $this->presentCancellationSummary($booking);
        $payload['refund'] = $this->presentRefundSummary($booking);
        $payload['blade_fallback_url'] = client_route('guest.bookings.show', ['booking' => $booking, 'token' => $token]);
        $payload['lookup_url'] = '/lookup-booking';

        return $payload;
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function presentMaskedPassengers(Booking $booking): array
    {
        return $booking->passengers
            ->sortBy('passenger_index')
            ->values()
            ->map(fn ($passenger): array => [
                'passenger_type' => (string) $passenger->passenger_type,
                'display_name' => TravelDocumentFormatter::maskPersonName(
                    $passenger->title,
                    $passenger->first_name,
                    $passenger->last_name,
                ),
                'is_lead_passenger' => (bool) $passenger->is_lead_passenger,
                'passport_number_masked' => TravelDocumentFormatter::maskPassport($passenger->passport_number),
                'national_id_masked' => TravelDocumentFormatter::maskPassport($passenger->national_id_number),
            ])
            ->all();
    }

    /**
     * @return array<string, mixed>
     */
    private function presentCapabilities(
        Booking $booking,
        string $token,
        bool $canUploadProof,
        bool $canRequestCancellation,
    ): array {
        $bookingId = $booking->id;
        $base = "/laravel/guest/bookings/{$bookingId}/access/{$token}";

        $invoiceDoc = $booking->documents
            ->first(fn ($doc) => $doc->document_type === BookingDocumentType::Invoice && filled($doc->file_path));
        $ticketDoc = $booking->documents
            ->first(fn ($doc) => in_array($doc->document_type, [BookingDocumentType::TicketItinerary, BookingDocumentType::BookingConfirmation], true)
                && filled($doc->file_path));

        $documents = $booking->documents
            ->filter(fn ($doc) => filled($doc->file_path))
            ->map(fn ($doc): array => [
                'id' => $doc->id,
                'title' => (string) ($doc->title ?? $doc->document_type?->value ?? 'Document'),
                'status' => (string) ($doc->status?->value ?? 'available'),
                'download_url' => '/laravel/guest/documents/'.$doc->id.'/download?token='.urlencode($token),
            ])
            ->values()
            ->all();

        return [
            'can_request_cancellation' => $canRequestCancellation,
            'can_upload_payment_proof' => $canUploadProof,
            'can_download_documents' => $documents !== [],
            'mutation_urls' => [
                'request_cancellation' => $canRequestCancellation
                    ? "{$base}/cancellations?format=json"
                    : null,
                'payment_proof' => $canUploadProof
                    ? "{$base}/payment-proof?format=json"
                    : null,
            ],
            'blade_fallback_urls' => [
                'guest_detail' => "{$base}",
                'abhipay_start' => "{$base}/abhipay/start",
                'promo_apply' => "{$base}/promo/apply",
                'promo_remove' => "{$base}/promo/remove",
            ],
            'download_urls' => [
                'invoice' => $invoiceDoc !== null
                    ? '/laravel/guest/documents/'.$invoiceDoc->id.'/download?token='.urlencode($token)
                    : null,
                'ticket' => $ticketDoc !== null
                    ? '/laravel/guest/documents/'.$ticketDoc->id.'/download?token='.urlencode($token)
                    : null,
            ],
            'documents' => $documents,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function presentCancellationSummary(Booking $booking): array
    {
        $latest = $booking->cancellationRequests->sortByDesc('id')->first();
        $open = $booking->cancellationRequests->contains(
            fn ($r) => in_array($r->status->value, [
                BookingCancellationStatus::Requested->value,
                BookingCancellationStatus::Approved->value,
            ], true),
        );

        if ($booking->status === BookingStatus::Cancelled) {
            return [
                'state' => 'cancelled',
                'label' => 'Cancelled',
                'message' => 'This booking has been cancelled.',
                'request' => $latest !== null ? [
                    'status' => (string) ($latest->status->value ?? $latest->status),
                    'status_label' => ucfirst(str_replace('_', ' ', (string) ($latest->status->value ?? $latest->status))),
                ] : null,
            ];
        }

        if ($open) {
            return [
                'state' => 'request_submitted',
                'label' => 'Request submitted',
                'message' => 'Your cancellation request is being reviewed.',
                'request' => $latest !== null ? [
                    'status' => (string) ($latest->status->value ?? $latest->status),
                    'status_label' => ucfirst(str_replace('_', ' ', (string) ($latest->status->value ?? $latest->status))),
                ] : null,
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

        return [
            'state' => $status,
            'label' => ucfirst(str_replace('_', ' ', $status)),
            'message' => 'Refund status is updated by our team.',
            'can_request' => false,
            'request' => [
                'status' => $status,
                'status_label' => ucfirst(str_replace('_', ' ', $status)),
            ],
        ];
    }
}
