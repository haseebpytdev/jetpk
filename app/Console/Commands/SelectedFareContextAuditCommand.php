<?php

namespace App\Console\Commands;

use App\Models\Booking;
use App\Services\FlightSearch\FlightSearchResultStore;
use App\Support\Bookings\IatiSupplierBookingEligibility;
use App\Support\FlightSearch\SelectedFareContextAuditor;
use Illuminate\Console\Command;

class SelectedFareContextAuditCommand extends Command
{
    protected $signature = 'ota:selected-fare-context-audit
        {--search-id= : Cached public search id}
        {--offer-id= : Cached offer id}
        {--fare-option-key= : Selected fare option key from results}
        {--booking-id= : Optional draft booking id for review-stage audit}';

    protected $description = 'Audit selected fare context for results → passenger/review handoff (no booking/ticket/payment)';

    public function handle(FlightSearchResultStore $searchStore): int
    {
        $bookingId = (int) $this->option('booking-id');
        if ($bookingId > 0) {
            return $this->auditBooking($bookingId);
        }

        $searchId = trim((string) $this->option('search-id'));
        $offerId = trim((string) $this->option('offer-id'));
        $fareOptionKey = trim((string) $this->option('fare-option-key'));

        if ($searchId === '' || $offerId === '') {
            $this->error('Provide --search-id and --offer-id, or --booking-id.');

            return self::FAILURE;
        }

        $payload = $searchStore->get($searchId);
        if ($payload === null) {
            $this->error('Search not found or expired: '.$searchId);

            return self::FAILURE;
        }

        $offer = $searchStore->findOffer($searchId, $offerId);
        if ($offer === null) {
            $this->error('Offer not found in search cache: '.$offerId);

            return self::FAILURE;
        }

        $criteria = is_array($payload['criteria'] ?? null) ? $payload['criteria'] : [];
        $report = SelectedFareContextAuditor::buildReport($offer, $searchId, $offerId, $fareOptionKey, $criteria);

        $this->printReport($report);

        if ($fareOptionKey !== '' && ! ($report['selection_resolved'] ?? false)) {
            $this->warn('Selected fare option could not be resolved on cached offer.');

            return self::FAILURE;
        }

        return self::SUCCESS;
    }

    protected function auditBooking(int $bookingId): int
    {
        $booking = Booking::query()->find($bookingId);
        if ($booking === null) {
            $this->error('Booking not found: '.$bookingId);

            return self::FAILURE;
        }

        $meta = is_array($booking->meta) ? $booking->meta : [];
        $offer = IatiSupplierBookingEligibility::resolveOfferSnapshot($meta);
        if ($offer === []) {
            $this->error('Booking has no offer snapshot in meta.');

            return self::FAILURE;
        }

        $searchId = trim((string) ($meta['search_id'] ?? ''));
        $offerId = trim((string) ($meta['original_offer_id'] ?? $offer['offer_id'] ?? $offer['id'] ?? ''));
        $fareOptionKey = IatiSupplierBookingEligibility::selectedFareOptionKeyFromMeta($meta);
        $criteria = is_array($meta['search_criteria'] ?? null) ? $meta['search_criteria'] : [];
        $intent = is_array($meta['selected_fare_family_option'] ?? null) ? $meta['selected_fare_family_option'] : null;

        $report = SelectedFareContextAuditor::buildReport($offer, $searchId, $offerId, $fareOptionKey, $criteria, $intent);
        $report['booking_id'] = $booking->id;
        $report['booking_status'] = (string) ($booking->status?->value ?? $booking->status ?? '');
        $report['supplier_booking_eligible'] = IatiSupplierBookingEligibility::appliesTo($booking)
            ? IatiSupplierBookingEligibility::isEligible($booking)
            : null;

        $this->printReport($report);

        if ($fareOptionKey !== '' && ! ($report['selection_resolved'] ?? false)) {
            $this->warn('Selected fare option could not be resolved from booking snapshot.');

            return self::FAILURE;
        }

        if ($fareOptionKey !== '' && ! is_array($intent)) {
            $this->warn('Booking meta missing selected_fare_family_option.');

            return self::FAILURE;
        }

        return self::SUCCESS;
    }

    /**
     * @param  array<string, mixed>  $report
     */
    protected function printReport(array $report): void
    {
        foreach ($report as $key => $value) {
            if (is_array($value)) {
                $this->line($key.'='.json_encode($value, JSON_UNESCAPED_UNICODE));

                continue;
            }
            if (is_bool($value)) {
                $this->line($key.'='.($value ? 'true' : 'false'));

                continue;
            }
            $this->line($key.'='.(string) ($value ?? ''));
        }
    }
}
