<?php

namespace App\Support\CustomerPortal;

use App\Enums\BookingStatus;
use App\Models\Booking;
use App\Services\Booking\BookingDraftService;
use App\Support\PublicBooking;
use Illuminate\Http\Request;
use InvalidArgumentException;

/**
 * Rehydrates JetPakistan-local Draft checkout session for an owned customer booking.
 * Does not create supplier bookings, PNRs, payments, or new Draft rows.
 */
final class CustomerDraftCheckoutResume
{
    public function __construct(
        protected BookingDraftService $bookingDraft,
    ) {}

    /**
     * @return array{booking_id: int, next_url: string}
     */
    public function resumeOwnedDraft(Request $request, Booking $booking): array
    {
        if ($booking->status !== BookingStatus::Draft) {
            throw new InvalidArgumentException('Only Draft bookings can be resumed.');
        }

        if (filled($booking->pnr)
            || in_array((string) ($booking->supplier_hold_status ?? ''), ['held', 'confirmed', 'ticketed'], true)
            || (string) ($booking->payment_status ?? '') === 'paid'
            || $booking->tickets()->exists()
        ) {
            throw new InvalidArgumentException('This booking can no longer be resumed for checkout.');
        }

        $booking->loadMissing(['passengers', 'contact']);

        $meta = is_array($booking->meta) ? $booking->meta : [];
        $criteria = is_array($meta['search_criteria'] ?? null) ? $meta['search_criteria'] : [];
        $offerId = trim((string) ($meta['checkout_offer_id'] ?? $booking->flight_offer_id ?? data_get($meta, 'flight_offer_snapshot.id', '')));
        $searchId = trim((string) ($meta['checkout_search_id'] ?? ''));

        // Bind THIS draft into the session — do not invent a new booking.
        $request->session()->put(PublicBooking::SESSION_BOOKING_ID, $booking->id);

        $this->bookingDraft->clear();
        $this->bookingDraft->merge([
            'flight_id' => $offerId,
            'offer_id' => $offerId,
            'search_id' => $searchId,
            'search_from' => (string) ($criteria['origin'] ?? ''),
            'search_to' => (string) ($criteria['destination'] ?? ''),
            'search_depart' => (string) ($criteria['depart_date'] ?? ''),
            'return_date' => (string) ($criteria['return_date'] ?? ''),
            'trip_type' => (string) ($criteria['trip_type'] ?? 'one_way'),
            'cabin' => (string) ($criteria['cabin'] ?? 'economy'),
            'adults' => max(1, (int) ($criteria['adults'] ?? 1)),
            'children' => max(0, (int) ($criteria['children'] ?? 0)),
            'infants' => max(0, (int) ($criteria['infants'] ?? 0)),
            'fare_option_key' => trim((string) ($meta['fare_option_key'] ?? '')),
            'selected_fare_family_option' => is_array($meta['selected_fare_family_option'] ?? null)
                ? $meta['selected_fare_family_option']
                : null,
            'resumed_from_customer_portal' => true,
            'resumed_booking_id' => $booking->id,
        ]);

        $persistedPassengers = $booking->passengers
            ->sortBy('passenger_index')
            ->values()
            ->map(static function ($passenger): array {
                return [
                    'title' => $passenger->title,
                    'first_name' => $passenger->first_name,
                    'last_name' => $passenger->last_name,
                    'gender' => $passenger->gender,
                    'date_of_birth' => $passenger->date_of_birth?->format('Y-m-d'),
                    'nationality' => $passenger->nationality,
                    'document_type' => $passenger->document_type,
                    'passport_number' => $passenger->passport_number,
                    'passport_issuing_country' => $passenger->passport_issuing_country,
                    'passport_expiry_date' => $passenger->passport_expiry_date?->format('Y-m-d'),
                    'passport_issue_date' => $passenger->passport_issue_date?->format('Y-m-d'),
                    'national_id_number' => $passenger->national_id_number,
                ];
            })
            ->all();

        $contact = $booking->contact;
        $contactMeta = is_array($contact?->meta) ? $contact->meta : [];
        $request->attributes->set('wave9_persisted_passenger_values', [
            'passengers' => $persistedPassengers,
            'contact' => [
                'contact_name' => (string) ($contactMeta['contact_name'] ?? ''),
                'email' => (string) ($contact?->email ?? ''),
                'phone' => (string) ($contact?->phone ?? ''),
                'country' => (string) ($contact?->country ?? ''),
            ],
        ]);

        return [
            'booking_id' => (int) $booking->id,
            'next_url' => client_url('/booking/passengers'),
        ];
    }
}
