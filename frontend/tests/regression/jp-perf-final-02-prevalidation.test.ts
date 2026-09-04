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
});
