import assert from "node:assert/strict";
import test from "node:test";
import {
  isActiveSearchStatus,
  mergeProgressiveResults,
  offerIdentityKey,
} from "../../features/flight-results/utils/merge-results";
import type { FlightResultsDataResponse } from "../../features/flight-results/types";

test("offerIdentityKey prefers offer_id", () => {
  assert.equal(offerIdentityKey({ offer_id: "ABC" }), "abc");
});

test("mergeProgressiveResults appends without duplicates", () => {
  const first: FlightResultsDataResponse = {
    search_id: "s1",
    page: 1,
    per_page: 12,
    total: 1,
    has_more: false,
    status: "partial",
    offers: [{ offer_id: "a", displayed_price: 100 }],
  };
  const second: FlightResultsDataResponse = {
    search_id: "s1",
    page: 1,
    per_page: 12,
    total: 2,
    has_more: false,
    status: "partial",
    offers: [
      { offer_id: "a", displayed_price: 100 },
      { offer_id: "b", displayed_price: 200 },
    ],
  };

  const merged = mergeProgressiveResults(first, second);
  assert.equal(merged.offers?.length, 2);
  assert.deepEqual(
    (merged.offers ?? []).map((o) => o.offer_id),
    ["a", "b"],
  );
});

test("isActiveSearchStatus covers progressive pipeline states", () => {
  assert.equal(isActiveSearchStatus("searching"), true);
  assert.equal(isActiveSearchStatus("partial"), true);
  assert.equal(isActiveSearchStatus("ready"), false);
  assert.equal(isActiveSearchStatus("empty"), false);
});
