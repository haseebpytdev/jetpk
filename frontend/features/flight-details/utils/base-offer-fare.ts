import type { FareFamilyOption, FlightOffer } from "@/features/flight-results/types";

/** Neutral customer label — never a fabricated airline brand family. */
export const BASE_FARE_LABEL = "Available Fare";
export const BASE_FARE_OPTION_KEY = "__jp_base_offer__";

/**
 * When the supplier exposes no branded fare catalog, build one truthful selectable
 * card from the base offer. option_key is NOT authoritative for Sabre brand qualifiers.
 */
export function buildBaseOfferFareCard(offer: FlightOffer): FareFamilyOption {
  return {
    option_key: BASE_FARE_OPTION_KEY,
    brand_name: BASE_FARE_LABEL,
    name: BASE_FARE_LABEL,
    displayed_price: offer.displayed_price ?? offer.final_customer_price ?? null,
    price_display: offer.price_display ?? null,
    cabin: offer.cabin ?? null,
    baggage: offer.baggage ?? null,
    checked_baggage: offer.baggage_checked_display ?? null,
    cabin_baggage: offer.baggage_cabin_display ?? null,
    refund_rule: offer.refundable === true ? "Refundable" : offer.refundable === false ? "Non-refundable" : null,
    can_select: true,
    selectable: true,
    selection_key_authoritative: false,
    is_synthetic_default: false,
    is_base_offer_fare: true,
  } as FareFamilyOption & { is_base_offer_fare?: boolean };
}

export function ensureSelectableFareCatalog(
  options: FareFamilyOption[],
  offer: FlightOffer | null | undefined,
): FareFamilyOption[] {
  if (options.length > 0) return options;
  if (!offer) return [];
  return [buildBaseOfferFareCard(offer)];
}

export function isBaseOfferFareOption(option: FareFamilyOption | undefined): boolean {
  if (!option) return false;
  return (
    (option as FareFamilyOption & { is_base_offer_fare?: boolean }).is_base_offer_fare === true
    || option.option_key === BASE_FARE_OPTION_KEY
  );
}
