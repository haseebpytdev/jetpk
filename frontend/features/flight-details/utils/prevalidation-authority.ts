/**
 * Selected-fare background revalidation authority helpers.
 * Booking-authority reuse is 5s (`OTA_SELECTED_OFFER_AUTHORITY_REUSE_SECONDS`).
 * Display freshness remains independent (refresh_due / stale_after).
 */

export const BOOK_NOW_VALIDATION_SOURCE = {
  FRESH_PREVALIDATION: "FRESH_PREVALIDATION",
  JOINED_INFLIGHT_PREVALIDATION: "JOINED_INFLIGHT_PREVALIDATION",
  NORMAL_FALLBACK_REVALIDATION: "NORMAL_FALLBACK_REVALIDATION",
  STALE_PREVALIDATION_REVALIDATED: "STALE_PREVALIDATION_REVALIDATED",
} as const;

export type BookNowValidationSource =
  (typeof BOOK_NOW_VALIDATION_SOURCE)[keyof typeof BOOK_NOW_VALIDATION_SOURCE];

/** Matches `SelectedOfferAuthority::reuseSeconds()` default (5s). */
export const AUTHORITATIVE_REVALIDATION_FRESH_MS = 5_000;

export type PrevalidationSignatureParams = {
  searchId: string;
  offerId: string;
  fareOptionKey?: string;
  comboId?: string;
  outboundKey?: string;
  outboundFareOptionKey?: string;
  returnFareOptionKey?: string;
  supplierProvider?: string;
  acceptFareChange?: boolean;
  origin?: string;
  destination?: string;
  departDate?: string;
  returnDate?: string;
  adults?: number;
  children?: number;
  infants?: number;
  cabin?: string;
  currency?: string;
};

export function buildValidationSignature(params: PrevalidationSignatureParams): string {
  return [
    params.searchId.trim(),
    params.offerId.trim(),
    (params.fareOptionKey ?? "").trim(),
    (params.comboId ?? "").trim(),
    (params.outboundKey ?? "").trim(),
    (params.outboundFareOptionKey ?? "").trim(),
    (params.returnFareOptionKey ?? "").trim(),
    (params.supplierProvider ?? "").trim().toLowerCase(),
    params.acceptFareChange ? "1" : "0",
    (params.origin ?? "").trim().toUpperCase(),
    (params.destination ?? "").trim().toUpperCase(),
    (params.departDate ?? "").trim(),
    (params.returnDate ?? "").trim(),
    String(params.adults ?? ""),
    String(params.children ?? ""),
    String(params.infants ?? ""),
    (params.cabin ?? "").trim().toLowerCase(),
    (params.currency ?? "").trim().toUpperCase(),
  ].join("|");
}

export type PrevalidationEntryState = {
  key: string;
  startedAt: number;
  completedAt: number | null;
};

export function classifyBookNowValidationSource(
  entry: PrevalidationEntryState | null,
  requestedKey: string,
  nowMs: number = Date.now(),
): BookNowValidationSource {
  if (!entry || entry.key !== requestedKey) {
    return BOOK_NOW_VALIDATION_SOURCE.NORMAL_FALLBACK_REVALIDATION;
  }
  if (entry.completedAt == null) {
    return BOOK_NOW_VALIDATION_SOURCE.JOINED_INFLIGHT_PREVALIDATION;
  }
  const age = nowMs - entry.completedAt;
  if (age >= AUTHORITATIVE_REVALIDATION_FRESH_MS) {
    return BOOK_NOW_VALIDATION_SOURCE.STALE_PREVALIDATION_REVALIDATED;
  }
  return BOOK_NOW_VALIDATION_SOURCE.FRESH_PREVALIDATION;
}

export function isAuthoritativeValidationFresh(
  completedAt: number | null | undefined,
  nowMs: number = Date.now(),
): boolean {
  if (completedAt == null || !Number.isFinite(completedAt)) return false;
  return nowMs - completedAt < AUTHORITATIVE_REVALIDATION_FRESH_MS;
}
