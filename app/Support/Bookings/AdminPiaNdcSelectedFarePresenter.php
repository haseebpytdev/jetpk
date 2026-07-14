<?php

namespace App\Support\Bookings;

use App\Enums\SupplierProvider;
use App\Models\Booking;

/**
 * Admin booking detail: read-only selected PIA NDC branded fare snapshot.
 */
final class AdminPiaNdcSelectedFarePresenter
{
    /**
     * @return array<string, mixed>
     */
    public function panel(Booking $booking): array
    {
        $meta = is_array($booking->meta) ? $booking->meta : [];
        $provider = strtolower(trim((string) ($meta['supplier_provider'] ?? $booking->supplier ?? '')));
        if ($provider !== SupplierProvider::PiaNdc->value) {
            return ['show' => false];
        }

        $selected = is_array($meta['selected_fare_family_option'] ?? null)
            ? $meta['selected_fare_family_option']
            : null;
        $outbound = is_array($meta['outbound_selected_fare_family_option'] ?? null)
            ? $meta['outbound_selected_fare_family_option']
            : null;
        $return = is_array($meta['return_selected_fare_family_option'] ?? null)
            ? $meta['return_selected_fare_family_option']
            : null;

        if ($selected === null && $outbound === null && $return === null) {
            return ['show' => false];
        }

        $currency = strtoupper(trim((string) ($booking->currency ?? 'PKR')));

        return [
            'show' => true,
            'title' => 'Selected branded fare (PIA NDC)',
            'selected' => $selected !== null ? $this->legRow($selected, 'Selected') : null,
            'outbound' => $outbound !== null ? $this->legRow($outbound, 'Outbound') : null,
            'return' => $return !== null ? $this->legRow($return, 'Return') : null,
            'selected_fare_total' => $booking->selected_fare_total !== null
                ? number_format((float) $booking->selected_fare_total, 0, '.', ',').' '.$currency
                : null,
            'revalidated_fare_total' => $booking->revalidated_fare_total !== null
                ? number_format((float) $booking->revalidated_fare_total, 0, '.', ',').' '.$currency
                : null,
        ];
    }

    /**
     * @param  array<string, mixed>  $intent
     * @return array<string, mixed>
     */
    private function legRow(array $intent, string $label): array
    {
        $ctx = is_array($intent['provider_context'] ?? null) ? $intent['provider_context'] : [];
        $brand = trim((string) ($intent['name'] ?? $intent['brand_name'] ?? ''));
        $fareBasis = trim((string) ($intent['fare_basis'] ?? $ctx['fare_basis'] ?? ''));
        $bookingClass = trim((string) ($intent['booking_class'] ?? $ctx['rbd'] ?? ''));
        $baggage = trim((string) (
            $intent['check_in_summary']
            ?? $intent['baggage_summary']
            ?? ''
        ));
        $offerRef = trim((string) ($ctx['offer_ref_id'] ?? ''));
        $offerItemRef = trim((string) ($ctx['offer_item_ref_id'] ?? ''));
        $priceTotal = $intent['price_total'] ?? null;
        $currency = strtoupper(trim((string) ($intent['currency'] ?? 'PKR')));
        $priceDisplay = null;
        if (is_numeric($priceTotal) && (float) $priceTotal > 0) {
            $priceDisplay = number_format((float) $priceTotal, 0, '.', ',').' '.$currency;
        }

        return array_filter([
            'label' => $label,
            'brand_name' => $brand !== '' ? $brand : null,
            'fare_basis' => $fareBasis !== '' ? $fareBasis : null,
            'booking_class' => $bookingClass !== '' ? $bookingClass : null,
            'baggage' => $baggage !== '' ? $baggage : null,
            'offer_ref_masked' => $this->maskOfferRef($offerRef),
            'offer_item_ref_masked' => $this->maskOfferRef($offerItemRef),
            'price_display' => $priceDisplay,
        ], static fn (mixed $v): bool => $v !== null && $v !== '');
    }

    private function maskOfferRef(string $offerRef): ?string
    {
        $offerRef = trim($offerRef);
        if ($offerRef === '') {
            return null;
        }
        if (strlen($offerRef) <= 20) {
            return substr($offerRef, 0, 8).'…';
        }

        return substr($offerRef, 0, 12).'…'.substr($offerRef, -8);
    }
}
