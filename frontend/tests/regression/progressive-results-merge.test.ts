import assert from "node:assert/strict";
import test from "node:test";
import {
  isActiveSearchStatus,
  mergeProgressiveResults,
  offerIdentityKey,
  pairedIdentityKey,
  resolvePipelineStatus,
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

test("mergeProgressiveResults preserves earlier offers when later poll is empty searching", () => {
  const first: FlightResultsDataResponse = {
    search_id: "s1",
    page: 1,
    per_page: 12,
    total: 1,
    has_more: false,
    status: "partial",
    offers: [{ offer_id: "kept", displayed_price: 100 }],
  };
  const second: FlightResultsDataResponse = {
    search_id: "s1",
    page: 1,
    per_page: 12,
    total: 0,
    has_more: false,
    status: "searching",
    offers: [],
  };

  const merged = mergeProgressiveResults(first, second);
  assert.equal(merged.status, "searching");
  assert.equal(merged.offers?.length, 1);
  assert.equal(merged.offers?.[0]?.offer_id, "kept");
  assert.equal(merged.total, 1);
});

test("mergeProgressiveResults dedupes paired options by combo_id", () => {
  const first: FlightResultsDataResponse = {
    search_id: "s1",
    page: 1,
    per_page: 12,
    total: 1,
    has_more: false,
    status: "partial",
    paired_options: [{ combo_id: "pair-1", total_price: 90000 } as never],
  };
  const second: FlightResultsDataResponse = {
    search_id: "s1",
    page: 1,
    per_page: 12,
    total: 2,
    has_more: false,
    status: "partial",
    paired_options: [
      { combo_id: "pair-1", total_price: 90000 } as never,
      { combo_id: "pair-2", total_price: 95000 } as never,
    ],
  };
  const merged = mergeProgressiveResults(first, second);
  assert.equal(merged.paired_options?.length, 2);
  assert.equal(pairedIdentityKey(merged.paired_options![0]), "pair-1");
});

test("isActiveSearchStatus covers progressive pipeline states", () => {
  assert.equal(isActiveSearchStatus("searching"), true);
  assert.equal(isActiveSearchStatus("partial"), true);
  assert.equal(isActiveSearchStatus("ready"), false);
  assert.equal(isActiveSearchStatus("empty"), false);
});

test("resolvePipelineStatus prefers top-level status", () => {
  assert.equal(
    resolvePipelineStatus({
      search_id: "s1",
      page: 1,
      per_page: 12,
      total: 0,
      has_more: false,
      status: "partial",
      search_freshness: { status: "ready" } as never,
    }),
    "partial",
  );
});
