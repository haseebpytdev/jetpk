/**
 * Traveler auto-reprice skip uses server passengers itinerary authority only.
 * Book Now timing / sessionStorage is instrumentation, never commercial authority.
 */
export type PassengerRepriceAuthorityContext = {
  selection?: {
    search_id?: string;
    offer_id?: string;
    fare_option_key?: string;
  } | null;
  itinerary?: {
    authoritative_after_revalidation?: boolean;
    selected_fare_option_key?: string | null;
    bound_search_id?: string | null;
    bound_offer_id?: string | null;
  } | null;
} | null | undefined;

function norm(value: string | null | undefined): string {
  return (value ?? "").trim();
}

export function shouldSkipPassengerAutoReprice(
  context: PassengerRepriceAuthorityContext,
): boolean {
  const itinerary = context?.itinerary;
  const selection = context?.selection;
  if (itinerary?.authoritative_after_revalidation !== true) {
    return false;
  }

  const searchId = norm(selection?.search_id);
  const offerId = norm(selection?.offer_id);
  const boundSearch = norm(itinerary.bound_search_id);
  const boundOffer = norm(itinerary.bound_offer_id);
  if (!searchId || !offerId || !boundSearch || !boundOffer) {
    return false;
  }
  if (searchId !== boundSearch || offerId !== boundOffer) {
    return false;
  }

  const selectionKey = norm(selection?.fare_option_key);
  const itineraryKey = norm(itinerary.selected_fare_option_key);
  if (!selectionKey || !itineraryKey || selectionKey !== itineraryKey) {
    return false;
  }

  return true;
}
