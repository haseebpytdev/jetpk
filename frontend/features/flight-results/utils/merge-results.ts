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

export function isActiveSearchStatus(status: string | undefined | null): boolean {
  const normalized = (status ?? "").toLowerCase();
  return (
    normalized === "queued" ||
    normalized === "searching" ||
    normalized === "partial" ||
    normalized === "in_progress"
  );
}

export function isTerminalSearchStatus(status: string | undefined | null): boolean {
  const normalized = (status ?? "").toLowerCase();
  return (
    normalized === "ready" ||
    normalized === "empty" ||
    normalized === "failed" ||
    normalized === "expired" ||
    normalized === "error"
  );
}

export function resolvePipelineStatus(payload: FlightResultsDataResponse): string {
  return (payload.status ?? payload.search_freshness?.status ?? "").toLowerCase();
}

/**
 * Merge progressive poll payloads.
 *
 * Active statuses (searching/partial): append/merge by identity.
 * Terminal ready/empty: canonical replacement — never retain rejected partial rows.
 * Terminal failed/expired/error: trust backend payload (do not invent completed inventory).
 */
export function mergeProgressiveResults(
  current: FlightResultsDataResponse | null,
  incoming: FlightResultsDataResponse,
): FlightResultsDataResponse {
  if (!current) return incoming;

  const incomingStatus = resolvePipelineStatus(incoming);

  if (incomingStatus === "ready" || incomingStatus === "empty") {
    return canonicalizeTerminalPayload(incoming);
  }

  if (
    incomingStatus === "failed" ||
    incomingStatus === "expired" ||
    incomingStatus === "error"
  ) {
    return canonicalizeTerminalPayload(incoming);
  }

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

function canonicalizeTerminalPayload(
  incoming: FlightResultsDataResponse,
): FlightResultsDataResponse {
  const offers = incoming.offers ?? [];
  const outbound = incoming.outbound_options ?? [];
  const paired = incoming.paired_options ?? [];

  return {
    ...incoming,
    offers,
    outbound_options: outbound,
    paired_options: paired,
    total:
      incoming.total ??
      Math.max(offers.length, outbound.length, paired.length),
  };
}
