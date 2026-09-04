/**
 * Selected-fare background revalidation authority helpers (JP-PERF-FINAL-02).
 * Freshness mirrors Laravel `ota.offer_freshness.stale_after_seconds` (default 600).
 */

export const BOOK_NOW_VALIDATION_SOURCE = {
  FRESH_PREVALIDATION: "FRESH_PREVALIDATION",
  JOINED_INFLIGHT_PREVALIDATION: "JOINED_INFLIGHT_PREVALIDATION",
  NORMAL_FALLBACK_REVALIDATION: "NORMAL_FALLBACK_REVALIDATION",
  STALE_PREVALIDATION_REVALIDATED: "STALE_PREVALIDATION_REVALIDATED",
} as const;

export type BookNowValidationSource =
  (typeof BOOK_NOW_VALIDATION_SOURCE)[keyof typeof BOOK_NOW_VALIDATION_SOURCE];

/** Matches `SabreOfferFreshness::revalidationValiditySeconds()` / stale_after_seconds default. */
export const AUTHORITATIVE_REVALIDATION_FRESH_MS = 600_000;

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
