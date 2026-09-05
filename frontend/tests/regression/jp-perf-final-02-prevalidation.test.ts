/**
 * JP-PERF-FINAL-02 — selected-fare prevalidation authority unit tests.
 * Mirrors frontend/features/flight-details/utils/prevalidation-authority.ts
 */
import assert from "node:assert/strict";
import { describe, it } from "node:test";

const BOOK_NOW_VALIDATION_SOURCE = {
  FRESH_PREVALIDATION: "FRESH_PREVALIDATION",
  JOINED_INFLIGHT_PREVALIDATION: "JOINED_INFLIGHT_PREVALIDATION",
  NORMAL_FALLBACK_REVALIDATION: "NORMAL_FALLBACK_REVALIDATION",
  STALE_PREVALIDATION_REVALIDATED: "STALE_PREVALIDATION_REVALIDATED",
};

const AUTHORITATIVE_REVALIDATION_FRESH_MS = 600_000;

function buildValidationSignature(params) {
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

function classifyBookNowValidationSource(entry, requestedKey, nowMs = Date.now()) {
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

function isAuthoritativeValidationFresh(completedAt, nowMs = Date.now()) {
  if (completedAt == null || !Number.isFinite(completedAt)) return false;
  return nowMs - completedAt < AUTHORITATIVE_REVALIDATION_FRESH_MS;
}

describe("JP-PERF-FINAL-02 prevalidation authority", () => {
  it("builds exact signature including fare and supplier discriminators", () => {
    const a = buildValidationSignature({
      searchId: "s1",
      offerId: "o1",
      fareOptionKey: "fare-a",
      supplierProvider: "Sabre",
    });
    const b = buildValidationSignature({
      searchId: "s1",
      offerId: "o1",
      fareOptionKey: "fare-b",
      supplierProvider: "Sabre",
    });
    const c = buildValidationSignature({
      searchId: "s1",
      offerId: "o1",
      fareOptionKey: "fare-a",
      supplierProvider: "Duffel",
    });
    assert.notEqual(a, b);
    assert.notEqual(a, c);
    assert.ok(a.includes("sabre"));
  });

  it("changed flight/offer invalidates signature match", () => {
    const a = buildValidationSignature({ searchId: "s1", offerId: "o1", fareOptionKey: "f1" });
    const b = buildValidationSignature({ searchId: "s1", offerId: "o2", fareOptionKey: "f1" });
    assert.notEqual(a, b);
  });

  it("classifies fresh completed prevalidation for reuse", () => {
    const key = buildValidationSignature({ searchId: "s1", offerId: "o1", fareOptionKey: "f1" });
    const now = 1_000_000;
    const source = classifyBookNowValidationSource(
      { key, startedAt: now - 5000, completedAt: now - 1000 },
      key,
      now,
    );
    assert.equal(source, BOOK_NOW_VALIDATION_SOURCE.FRESH_PREVALIDATION);
    assert.equal(isAuthoritativeValidationFresh(now - 1000, now), true);
  });

  it("classifies in-flight join", () => {
    const key = buildValidationSignature({ searchId: "s1", offerId: "o1", fareOptionKey: "f1" });
    const source = classifyBookNowValidationSource(
      { key, startedAt: Date.now(), completedAt: null },
      key,
    );
    assert.equal(source, BOOK_NOW_VALIDATION_SOURCE.JOINED_INFLIGHT_PREVALIDATION);
  });

  it("rejects stale completed prevalidation", () => {
    const key = buildValidationSignature({ searchId: "s1", offerId: "o1", fareOptionKey: "f1" });
    const now = 10_000_000;
    const completedAt = now - AUTHORITATIVE_REVALIDATION_FRESH_MS - 1;
    const source = classifyBookNowValidationSource(
      { key, startedAt: completedAt - 1000, completedAt },
      key,
      now,
    );
    assert.equal(source, BOOK_NOW_VALIDATION_SOURCE.STALE_PREVALIDATION_REVALIDATED);
    assert.equal(isAuthoritativeValidationFresh(completedAt, now), false);
  });

  it("falls back when signature mismatches (fare change)", () => {
    const keyA = buildValidationSignature({ searchId: "s1", offerId: "o1", fareOptionKey: "f1" });
    const keyB = buildValidationSignature({ searchId: "s1", offerId: "o1", fareOptionKey: "f2" });
    const source = classifyBookNowValidationSource(
      { key: keyA, startedAt: 1, completedAt: 2 },
      keyB,
      3,
    );
    assert.equal(source, BOOK_NOW_VALIDATION_SOURCE.NORMAL_FALLBACK_REVALIDATION);
  });

  it("uses established 600s freshness window (not invented short TTL)", () => {
    assert.equal(AUTHORITATIVE_REVALIDATION_FRESH_MS, 600_000);
  });

  it("skips traveler auto-reprice only on server-authoritative exact selection", async () => {
    const { shouldSkipPassengerAutoReprice } = await import(
      "../../features/standard-booking/utils/passenger-reprice-authority.ts"
    );
    const exact = {
      selection: { search_id: "s1", offer_id: "o1", fare_option_key: "fare-a" },
      itinerary: {
        authoritative_after_revalidation: true,
        selected_fare_option_key: "fare-a",
        bound_search_id: "s1",
        bound_offer_id: "o1",
      },
    };
    assert.equal(shouldSkipPassengerAutoReprice(exact), true);
    assert.equal(
      shouldSkipPassengerAutoReprice({
        ...exact,
        itinerary: { ...exact.itinerary, authoritative_after_revalidation: false },
      }),
      false,
    );
    assert.equal(
      shouldSkipPassengerAutoReprice({
        ...exact,
        selection: { ...exact.selection, fare_option_key: "fare-b" },
      }),
      false,
    );
    assert.equal(
      shouldSkipPassengerAutoReprice({
        ...exact,
        selection: { ...exact.selection, offer_id: "o2" },
      }),
      false,
    );
    assert.equal(
      shouldSkipPassengerAutoReprice({
        selection: { search_id: "s1", offer_id: "o1", fare_option_key: "fare-a" },
        itinerary: { selected_fare_option_key: "fare-a" },
      }),
      false,
    );
    assert.equal(shouldSkipPassengerAutoReprice(null), false);
    assert.equal(
      shouldSkipPassengerAutoReprice({
        selection: { search_id: "", offer_id: "o1", fare_option_key: "fare-a" },
        itinerary: exact.itinerary,
      }),
      false,
    );
  });

  it("maps synthetic base-offer UI key to empty supplier fare identity", () => {
    const BASE = "__jp_base_offer__";
    const toAuthoritativeFareOptionKey = (key, options = []) => {
      const trimmed = key?.trim() ?? "";
      if (!trimmed || trimmed === BASE) return undefined;
      const match = options.find((o) => o.option_key === trimmed);
      if (match && (match.is_base_offer_fare || match.option_key === BASE)) return undefined;
      return trimmed;
    };
    assert.equal(toAuthoritativeFareOptionKey(BASE, []), undefined);
    assert.equal(toAuthoritativeFareOptionKey("eclassic-pi0", []), "eclassic-pi0");
    assert.equal(
      toAuthoritativeFareOptionKey("eclassic-pi0", [{ option_key: "eclassic-pi0", is_base_offer_fare: true }]),
      undefined,
    );
  });
});
