import type { FlightOffer, FlightResultsDataResponse, OutboundOption, PairedReturnOption } from "../types";

export function offerIdentityKey(offer: FlightOffer): string {
  const id = (offer.offer_id ?? "").trim();
  if (id) return id.toLowerCase();
  return [
    offer.supplier_provider ?? "",
    offer.flight_number ?? "",
    offer.departure_time ?? "",
    String(offer.displayed_price ?? ""),
  ]
    .join("|")
    .toLowerCase();
}

export function outboundIdentityKey(option: OutboundOption): string {
  return String(option.outbound_key ?? "").trim().toLowerCase();
}

export function pairedIdentityKey(option: PairedReturnOption): string {
  return String(option.combo_id ?? "").trim().toLowerCase();
}

function mergeByKey<T>(existing: T[], incoming: T[], keyFn: (row: T) => string): T[] {
  const map = new Map<string, T>();
  for (const row of existing) {
    const key = keyFn(row);
    if (key) map.set(key, row);
  }
  for (const row of incoming) {
    const key = keyFn(row);
    if (!key) continue;
    map.set(key, row);
  }
  return Array.from(map.values());
}

/** Merge progressive poll payloads without wiping earlier offers or duplicating identities. */
export function mergeProgressiveResults(
  current: FlightResultsDataResponse | null,
  incoming: FlightResultsDataResponse,
): FlightResultsDataResponse {
  if (!current) return incoming;

  const offers = mergeByKey(current.offers ?? [], incoming.offers ?? [], offerIdentityKey);
  const outbound = mergeByKey(
    current.outbound_options ?? [],
    incoming.outbound_options ?? [],
    outboundIdentityKey,
  );
  const paired = mergeByKey(
    current.paired_options ?? [],
    incoming.paired_options ?? [],
    pairedIdentityKey,
  );

  return {
    ...incoming,
    offers,
    outbound_options: outbound,
    paired_options: paired,
    total: Math.max(
      incoming.total ?? 0,
      offers.length,
      outbound.length,
      paired.length,
      current.total ?? 0,
    ),
  };
}

export function isActiveSearchStatus(status: string | undefined | null): boolean {
  const normalized = (status ?? "").toLowerCase();
  return (
    normalized === "queued" ||
    normalized === "searching" ||
    normalized === "partial" ||
    normalized === "in_progress"
  );
}

export function resolvePipelineStatus(payload: FlightResultsDataResponse): string {
  return (payload.status ?? payload.search_freshness?.status ?? "").toLowerCase();
}
