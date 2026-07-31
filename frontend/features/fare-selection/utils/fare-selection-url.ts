/**
 * Opaque handoff URLs for the canonical fare-selection journey step.
 * Never carries offer JSON, prices, passenger data, or supplier payloads.
 */
export function buildFareSelectionUrl(params: {
  searchId: string;
  offerId: string;
  fareOptionKey?: string;
}): string {
  const query = new URLSearchParams();
  query.set("search_id", params.searchId);
  query.set("offer_id", params.offerId);
  if (params.fareOptionKey) {
    query.set("fare_option_key", params.fareOptionKey);
  }
  return `/flights/fare-selection?${query.toString()}`;
}

export function parseFareSelectionParams(searchParams: Record<string, string | undefined>): {
  searchId: string;
  offerId: string;
  fareOptionKey?: string;
} | null {
  const searchId = searchParams.search_id?.trim();
  const offerId = searchParams.offer_id?.trim();
  if (!searchId || !offerId) return null;
  const fareOptionKey = searchParams.fare_option_key?.trim() || undefined;
  return { searchId, offerId, fareOptionKey };
}
