import assert from "node:assert/strict";
import test from "node:test";
import {
  isActiveSearchStatus,
  isTerminalSearchStatus,
  mergeProgressiveResults,
  offerIdentityKey,
  pairedIdentityKey,
  resolvePipelineStatus,
} from "../../features/flight-results/utils/merge-results";
import { mergeProgressiveReturnOptions } from "../../features/flight-results/utils/merge-return-options";
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

test("TERMINAL_READY_REMOVES_REJECTED_PARTIAL", () => {
  const partial: FlightResultsDataResponse = {
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
  const ready: FlightResultsDataResponse = {
    search_id: "s1",
    page: 1,
    per_page: 12,
    total: 1,
    has_more: false,
    status: "ready",
    offers: [{ offer_id: "b", displayed_price: 200 }],
  };

  const merged = mergeProgressiveResults(partial, ready);
  assert.deepEqual(
    (merged.offers ?? []).map((o) => o.offer_id),
    ["b"],
  );
  assert.equal(merged.total, 1);
  assert.equal(merged.status, "ready");
});

test("TERMINAL_TOTAL_CAN_DECREASE_TO_CANONICAL", () => {
  const partial: FlightResultsDataResponse = {
    search_id: "s1",
    page: 1,
    per_page: 12,
    total: 5,
    has_more: false,
    status: "partial",
    offers: [
      { offer_id: "a", displayed_price: 100 },
      { offer_id: "b", displayed_price: 200 },
      { offer_id: "c", displayed_price: 300 },
    ],
  };
  const ready: FlightResultsDataResponse = {
    search_id: "s1",
    page: 1,
    per_page: 12,
    total: 2,
    has_more: false,
    status: "ready",
    offers: [
      { offer_id: "b", displayed_price: 200 },
      { offer_id: "c", displayed_price: 300 },
    ],
  };

  const merged = mergeProgressiveResults(partial, ready);
  assert.equal(merged.total, 2);
  assert.ok((merged.total ?? 0) < (partial.total ?? 0));
});

test("TERMINAL_READY_REFRESHES_EXISTING_OFFER", () => {
  const partial: FlightResultsDataResponse = {
    search_id: "s1",
    page: 1,
    per_page: 12,
    total: 1,
    has_more: false,
    status: "partial",
    offers: [{ offer_id: "a", displayed_price: 100 }],
  };
  const ready: FlightResultsDataResponse = {
    search_id: "s1",
    page: 1,
    per_page: 12,
    total: 1,
    has_more: false,
    status: "ready",
    offers: [{ offer_id: "a", displayed_price: 115 }],
  };

  const merged = mergeProgressiveResults(partial, ready);
  assert.equal(merged.offers?.length, 1);
  assert.equal(merged.offers?.[0]?.displayed_price, 115);
});

test("PAIRED_TERMINAL_CANONICALITY", () => {
  const partial: FlightResultsDataResponse = {
    search_id: "s1",
    page: 1,
    per_page: 12,
    total: 2,
    has_more: false,
    status: "partial",
    paired_options: [
      { combo_id: "pair-a", total_price: 90000 } as never,
      { combo_id: "pair-b", total_price: 95000 } as never,
    ],
  };
  const ready: FlightResultsDataResponse = {
    search_id: "s1",
    page: 1,
    per_page: 12,
    total: 1,
    has_more: false,
    status: "ready",
    paired_options: [{ combo_id: "pair-b", total_price: 94000 } as never],
  };

  const merged = mergeProgressiveResults(partial, ready);
  assert.equal(merged.paired_options?.length, 1);
  assert.equal(pairedIdentityKey(merged.paired_options![0]), "pair-b");
  assert.equal((merged.paired_options![0] as unknown as { total_price: number }).total_price, 94000);
});

test("SPLIT_OUTBOUND_TERMINAL_CANONICALITY", () => {
  const partial: FlightResultsDataResponse = {
    search_id: "s1",
    page: 1,
    per_page: 12,
    total: 2,
    has_more: false,
    status: "partial",
    outbound_options: [
      { outbound_key: "out-a", price: 40000 } as never,
      { outbound_key: "out-b", price: 45000 } as never,
    ],
  };
  const ready: FlightResultsDataResponse = {
    search_id: "s1",
    page: 1,
    per_page: 12,
    total: 1,
    has_more: false,
    status: "ready",
    outbound_options: [{ outbound_key: "out-b", price: 44000 } as never],
  };

  const merged = mergeProgressiveResults(partial, ready);
  assert.equal(merged.outbound_options?.length, 1);
  assert.equal(merged.outbound_options?.[0]?.outbound_key, "out-b");
});

test("SPLIT_RETURN_TERMINAL_CANONICALITY", () => {
  const partial = [
    { combo_id: "ret-a", total_price: 30000 },
    { combo_id: "ret-b", total_price: 32000 },
  ];
  const ready = [{ combo_id: "ret-b", total_price: 31000 }];

  const merged = mergeProgressiveReturnOptions(partial, ready, "ready");
  assert.deepEqual(
    merged.map((o) => o.combo_id),
    ["ret-b"],
  );
  assert.equal(merged[0]?.total_price, 31000);
});

test("terminal empty does not preserve stale partial offers", () => {
  const partial: FlightResultsDataResponse = {
    search_id: "s1",
    page: 1,
    per_page: 12,
    total: 1,
    has_more: false,
    status: "partial",
    offers: [{ offer_id: "stale", displayed_price: 100 }],
  };
  const empty: FlightResultsDataResponse = {
    search_id: "s1",
    page: 1,
    per_page: 12,
    total: 0,
    has_more: false,
    status: "empty",
    offers: [],
    empty_message: "No flights match your search.",
  };

  const merged = mergeProgressiveResults(partial, empty);
  assert.equal(merged.offers?.length, 0);
  assert.equal(merged.total, 0);
  assert.equal(merged.status, "empty");
});

test("isActiveSearchStatus covers progressive pipeline states", () => {
  assert.equal(isActiveSearchStatus("searching"), true);
  assert.equal(isActiveSearchStatus("partial"), true);
  assert.equal(isActiveSearchStatus("ready"), false);
  assert.equal(isActiveSearchStatus("empty"), false);
  assert.equal(isTerminalSearchStatus("ready"), true);
  assert.equal(isTerminalSearchStatus("partial"), false);
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
