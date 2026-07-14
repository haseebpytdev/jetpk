<?php

namespace App\Support\Bookings;

use App\Enums\SupplierProvider;
use App\Models\Booking;
use App\Models\BookingHoldSession;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * Links orphan IATI checkout hold sessions to new bookings when checkout identity matches.
 * Local-only: no supplier booking, PNR, or payment mutation.
 */
class IatiHoldSessionAutoLinkService
{
    public function attemptAutoLink(Booking $booking): ?BookingHoldSession
    {
        if (! IatiSupplierBookingEligibility::appliesTo($booking)) {
            return null;
        }

        if ($booking->hold_session_id !== null) {
            return null;
        }

        $meta = is_array($booking->meta) ? $booking->meta : [];
        $searchId = trim((string) ($meta['checkout_search_id'] ?? ''));
        $offerId = trim((string) ($meta['checkout_offer_id'] ?? $meta['original_offer_id'] ?? ''));

        if ($searchId === '' || $offerId === '') {
            return null;
        }

        $candidates = $this->findCandidates($booking, $searchId, $offerId);

        if ($candidates->isEmpty()) {
            return null;
        }

        if ($candidates->count() > 1) {
            Log::warning('iati_hold_session_auto_link_ambiguous', [
                'booking_id' => $booking->id,
                'search_id' => $searchId,
                'offer_id' => $offerId,
                'candidate_hold_session_ids' => $candidates->pluck('id')->map(fn ($id): int => (int) $id)->values()->all(),
            ]);

            return null;
        }

        /** @var BookingHoldSession $candidate */
        $candidate = $candidates->first();

        return $this->applyLink($booking, $candidate, $searchId, $offerId);
    }

    /**
     * @return Collection<int, BookingHoldSession>
     */
    protected function findCandidates(Booking $booking, string $searchId, string $offerId): Collection
    {
        return BookingHoldSession::query()
            ->where('search_id', $searchId)
            ->where('offer_id', $offerId)
            ->where('supplier_provider', SupplierProvider::Iati->value)
            ->where(function ($query) use ($booking): void {
                $query->whereNull('booking_id')
                    ->orWhere('booking_id', $booking->id);
            })
            ->orderBy('id')
            ->get();
    }

    protected function applyLink(
        Booking $booking,
        BookingHoldSession $candidate,
        string $searchId,
        string $offerId,
    ): BookingHoldSession {
        DB::transaction(function () use ($booking, $candidate): void {
            $candidate->forceFill(['booking_id' => $booking->id])->save();
            $booking->forceFill(['hold_session_id' => $candidate->id])->save();
        });

        $candidate->refresh();

        Log::info('iati_hold_session_auto_link_applied', [
            'booking_id' => $booking->id,
            'hold_session_id' => $candidate->id,
            'search_id' => $searchId,
            'offer_id' => $offerId,
        ]);

        return $candidate;
    }
}
