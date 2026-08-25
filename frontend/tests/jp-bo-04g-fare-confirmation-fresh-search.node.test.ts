import assert from "node:assert/strict";
import { describe, it } from "node:test";
import {
  buildFreshResultsSearchParams,
  shouldRefreshResultsAfterCheckoutReturn,
} from "../features/flight-results/utils/checkout-nav";

describe("checkout-nav fresh results safety", () => {
  it("preserves criteria and strips selection authority keys", () => {
    const source = new URLSearchParams({
      search_id: "old-search",
      trip_type: "round_trip",
      origin: "LHE",
      destination: "DXB",
      departure_date: "2026-09-10",
      return_date: "2026-09-17",
      adults: "1",
      children: "0",
      infants: "0",
      cabin: "economy",
      view: "pair",
      offer_id: "offer-1",
      combo_id: "combo-1",
      fare_option_key: "fare-basic",
      outbound_key: "out-1",
      sort: "cheapest",
    });

    const next = buildFreshResultsSearchParams(source);
    assert.equal(next.get("search_id"), null);
    assert.equal(next.get("offer_id"), null);
    assert.equal(next.get("combo_id"), null);
    assert.equal(next.get("fare_option_key"), null);
    assert.equal(next.get("outbound_key"), null);
    assert.equal(next.get("trip_type"), "round_trip");
    assert.equal(next.get("origin"), "LHE");
    assert.equal(next.get("destination"), "DXB");
    assert.equal(next.get("departure_date"), "2026-09-10");
    assert.equal(next.get("return_date"), "2026-09-17");
    assert.equal(next.get("adults"), "1");
    assert.equal(next.get("cabin"), "economy");
    assert.equal(next.get("view"), "pair");
    assert.equal(next.get("sort"), null);
  });

  it("requires checkout provenance and back/bfcache navigation", () => {
    const persisted = { persisted: true } as PageTransitionEvent;
    const normal = { persisted: false } as PageTransitionEvent;

    assert.equal(shouldRefreshResultsAfterCheckoutReturn(normal), false);
    // Without session flag / checkout referrer, persisted alone is insufficient.
    assert.equal(shouldRefreshResultsAfterCheckoutReturn(persisted), false);
  });
});
