<?php

namespace App\Support\Sabre;

/**
 * Normalizes Sabre diagnostic reason codes into safe human messages (F5).
 */
final class SabreReadinessReasonPresenter
{
    /** @var array<string, string> */
    private const MESSAGES = [
        'auto_pnr_flag_disabled' => 'Auto-PNR is disabled by configuration.',
        'public_flag_disabled' => 'Public auto-PNR is disabled by configuration.',
        'public_auto_pnr_disabled' => 'Public auto-PNR is disabled by configuration.',
        'ticketing_disabled' => 'Live Sabre ticketing is disabled by configuration.',
        'no_eligible_gds_offer' => 'No eligible GDS offer is linked for PNR creation.',
        'revalidation_empty_or_unusable_response' => 'Sabre revalidation returned empty or unusable data.',
        'no_usable_fare_linkage' => 'Fare linkage/pricing context is incomplete.',
        'missing_supplier_connection' => 'No active Sabre supplier connection is configured.',
        'missing_sabre_pnr' => 'No PNR or supplier reference is stored.',
        'not_sabre_booking' => 'This booking is not a Sabre booking.',
        'ticketed_booking_blocked' => 'Action blocked because the booking is already ticketed.',
        'cancelled_booking_blocked' => 'Action blocked because the booking is cancelled.',
        'manual_review_required' => 'Manual review is required before proceeding.',
        'existing_pnr_present' => 'A PNR or supplier reference already exists for this booking.',
        'missing_booking_reference' => 'Booking reference is missing.',
        'missing_passengers' => 'Passenger records are required before PNR creation.',
        'missing_required_passenger_fields' => 'Required passenger fields are incomplete.',
        'missing_contact' => 'Booking contact details are required before PNR creation.',
        'missing_pricing_context' => 'Sabre pricing context is incomplete.',
        'missing_revalidation_context' => 'Sabre revalidation linkage is incomplete.',
        'stale_pricing' => 'Offer pricing context is stale — refresh before PNR create.',
        'revalidation_expired' => 'Sabre revalidation has expired — revalidate before controlled PNR create.',
        'offer_refresh_customer_confirmation_required' => 'Offer refresh requires customer fare acceptance before PNR create.',
        'price_change_confirmation_required' => 'Price change confirmation is required before PNR create.',
        'supplier_mutation_disabled' => 'Supplier mutation is disabled by configuration or platform module.',
        'admin_confirmation_required' => 'Explicit admin or command confirmation is required.',
        'exact_create_confirmation_required' => 'Exact controlled PNR create confirmation is required before any live supplier call.',
        'unsupported_itinerary' => 'Itinerary type is not supported for controlled PNR create.',
        'mixed_carrier_interline_blocked' => 'Mixed-carrier or interline itineraries are blocked for controlled PNR.',
        'payment_not_verified' => 'Payment verification is required before PNR creation.',
        'eligible_controlled_pnr' => 'Booking is eligible for controlled PNR readiness review.',
        'blocked_ineligible' => 'Booking is not eligible for controlled PNR creation.',
    ];

    /** @var array<string, string> */
    private const ALIASES = [
        'blocked_no_supplier_connection' => 'missing_supplier_connection',
        'missing_supplier_connection_id' => 'missing_supplier_connection',
        'not_sabre' => 'not_sabre_booking',
        'not_sabre_booking' => 'not_sabre_booking',
        'blocked_not_sabre' => 'not_sabre_booking',
        'blocked_ticketing_enabled' => 'ticketing_disabled',
        'ticketing_enabled' => 'ticketing_disabled',
        'booking_missing_pnr' => 'missing_sabre_pnr',
        'blocked_missing_pnr' => 'missing_sabre_pnr',
        'sabre_revalidation_empty_or_unusable_response' => 'revalidation_empty_or_unusable_response',
        'feature_flag_disabled' => 'auto_pnr_flag_disabled',
        'blocked_already_has_pnr' => 'existing_pnr_present',
        'blocked_already_has_supplier_reference' => 'existing_pnr_present',
        'blocked_no_supplier_connection' => 'missing_supplier_connection',
        'blocked_missing_required_documents' => 'missing_required_passenger_fields',
        'blocked_mixed_carrier' => 'mixed_carrier_interline_blocked',
        'blocked_not_sabre' => 'not_sabre_booking',
        'blocked_by_flags' => 'supplier_mutation_disabled',
        'revalidation_linkage_incomplete' => 'missing_revalidation_context',
    ];

    public function normalizeCode(string $code): string
    {
        $slug = strtolower(trim(str_replace([' ', '-'], '_', $code)));
        if ($slug === '') {
            return 'unknown';
        }

        if (isset(self::ALIASES[$slug])) {
            return self::ALIASES[$slug];
        }

        if (isset(self::MESSAGES[$slug])) {
            return $slug;
        }

        return $slug;
    }

    public function messageForCode(string $code): string
    {
        $normalized = $this->normalizeCode($code);

        if (isset(self::MESSAGES[$normalized])) {
            return self::MESSAGES[$normalized];
        }

        $display = str_replace('_', ' ', $normalized);

        return 'Diagnostic reason: '.$display;
    }

    /**
     * @param  list<mixed>|mixed  $codes
     * @return list<string>
     */
    public function messagesForCodes(mixed $codes): array
    {
        if (! is_array($codes) || $codes === []) {
            return [];
        }

        $out = [];
        foreach ($codes as $code) {
            if (! is_scalar($code)) {
                continue;
            }
            $text = trim((string) $code);
            if ($text === '') {
                continue;
            }
            $out[] = $this->messageForCode($text);
        }

        return $out;
    }
}
