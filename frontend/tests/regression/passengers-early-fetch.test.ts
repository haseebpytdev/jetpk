import assert from "node:assert/strict";
import test from "node:test";
import vm from "node:vm";
import { PASSENGERS_EARLY_FETCH_INLINE } from "../../features/standard-booking/utils/passengers-early-fetch-inline";
import {
  buildPassengersFetchQuery,
  hasPassengersHandoffQuery,
  PASSENGERS_FETCH_CREDENTIALS,
  PASSENGERS_JSON_HEADERS,
  PASSENGERS_LARAVEL_PATH,
  shouldFallbackAfterEarlyResult,
  shouldReuseEarlyPrime,
} from "../../features/standard-booking/utils/passengers-fetch-query";
import { buildPassengersFromContext } from "../../features/standard-booking/utils/passenger-form";
import type { StandardPassengersContext } from "../../features/standard-booking/types";

const HANDOFF =
  "search_id=s1&offer_id=o1&fare_option_key=fk1&outbound_key=ob1&bound=return&format=html";

function runInline(opts: {
  pathname?: string;
  search?: string;
  fetchImpl?: (...args: unknown[]) => Promise<unknown>;
}) {
  const calls: Array<{ url: string; init: RequestInit }> = [];
  const window: Record<string, unknown> = {};
  const location = {
    pathname: opts.pathname ?? "/booking/passengers",
    search: opts.search ?? `?${HANDOFF}`,
  };
  const fetchImpl =
    opts.fetchImpl ??
    (async () => ({
      ok: true,
      status: 200,
      headers: { get: () => "application/json" },
      json: async () => ({ ok: true, selection: { search_id: "s1", offer_id: "o1" } }),
    }));
  const fetch = (url: string, init: RequestInit) => {
    calls.push({ url, init });
    return fetchImpl(url, init);
  };
  const context = {
    window,
    location,
    fetch,
    performance: { now: () => 12 },
    URLSearchParams,
  };
  vm.runInNewContext(PASSENGERS_EARLY_FETCH_INLINE, context);
  return { window, calls, fetch };
}

test("early fetch uses the same Laravel GET, query, credentials, and headers as React", () => {
  const reactKey = buildPassengersFetchQuery(new URLSearchParams(HANDOFF));
  const { calls, window } = runInline({});
  assert.equal(calls.length, 1);
  assert.equal(calls[0].url, `${PASSENGERS_LARAVEL_PATH}?${reactKey}`);
  assert.equal(calls[0].init.credentials, PASSENGERS_FETCH_CREDENTIALS);
  assert.equal((calls[0].init.headers as { Accept: string }).Accept, PASSENGERS_JSON_HEADERS.Accept);
  assert.equal(
    (calls[0].init.headers as { "X-Requested-With": string })["X-Requested-With"],
    PASSENGERS_JSON_HEADERS["X-Requested-With"],
  );
  const prime = window.__jpPassengersPrime as { key: string };
  assert.equal(prime.key, reactKey);
  assert.equal(reactKey.includes("search_id=s1"), true);
  assert.equal(reactKey.includes("offer_id=o1"), true);
  assert.equal(reactKey.includes("fare_option_key=fk1"), true);
  assert.equal(reactKey.includes("outbound_key=ob1"), true);
  assert.equal(reactKey.includes("bound=return"), true);
});

test("early fetch starts before hydration and is reused without a second GET", async () => {
  const { calls, window } = runInline({});
  assert.equal(calls.length, 1);
  const boot = window.__jpTravelerBoot as { marks: Record<string, number> };
  assert.ok(boot.marks.EARLY_FETCH_START != null);
  assert.ok(boot.marks.TRAVELER_DOCUMENT_READY != null);
  const prime = window.__jpPassengersPrime as { key: string; promise: Promise<unknown> };
  const currentKey = buildPassengersFetchQuery(new URLSearchParams(HANDOFF));
  assert.equal(shouldReuseEarlyPrime(prime.key, currentKey), true);
  await prime.promise;
  assert.equal(calls.length, 1);
});

test("failed early GET falls back to a normal GET", async () => {
  let n = 0;
  const fetchImpl = async () => {
    n += 1;
    if (n === 1) {
      return {
        ok: false,
        status: 0,
        headers: { get: () => "" },
        json: async () => null,
      };
    }
    return {
      ok: true,
      status: 200,
      headers: { get: () => "application/json" },
      json: async () => ({ ok: true }),
    };
  };
  const { window, calls } = runInline({ fetchImpl });
  const early = await (window.__jpPassengersPrime as { promise: Promise<{ ok: boolean }> }).promise;
  assert.equal(shouldFallbackAfterEarlyResult(early), true);
  assert.equal(calls.length, 1);
  const fallback = await fetchImpl();
  assert.equal(fallback.ok, true);
  assert.equal(n, 2);
});

test("stale mismatched context is not reused", () => {
  assert.equal(shouldReuseEarlyPrime("search_id=a&offer_id=1&format=json", "search_id=b&offer_id=1&format=json"), false);
  assert.equal(shouldReuseEarlyPrime("search_id=a&offer_id=1&format=json", "search_id=a&offer_id=1&format=json"), true);
});

test("missing handoff does not issue an unsafe request", () => {
  const { calls, window } = runInline({ search: "" });
  assert.equal(calls.length, 0);
  assert.equal(window.__jpPassengersPrime, undefined);
  assert.equal(hasPassengersHandoffQuery({}), false);
  assert.equal(hasPassengersHandoffQuery({ search_id: "s1" }), false);
  assert.equal(hasPassengersHandoffQuery({ search_id: "s1", offer_id: "o1" }), true);
});

test("prime is cleared after consumption", () => {
  const { window } = runInline({});
  assert.ok(window.__jpPassengersPrime);
  window.__jpPassengersPrime = undefined;
  assert.equal(window.__jpPassengersPrime, undefined);
});

test("existing passenger rendering still works", () => {
  const context = {
    ok: true,
    booking_session: { id: "sess", status: "passenger_details", server_time: new Date().toISOString(), progress: [] },
    selection: { search_id: "s1", offer_id: "o1", from: "LHE", to: "DXB", depart: "2026-09-01", trip_type: "one_way", cabin: "economy" },
    itinerary: {
      trip_type: "one_way",
      origin: "LHE",
      destination: "DXB",
      depart_date: "2026-09-01",
      cabin: "economy",
      segments: [],
      return_segments: [],
      currency: "PKR",
      total_formatted: "PKR 88,114",
    },
    travellers: {
      adults: 1,
      children: 0,
      infants: 0,
      total: 1,
      expected: [{ index: 0, type: "ADT", label: "Adult" }],
      lead_passenger_index: 0,
    },
    passenger_requirements: [],
    contact_requirements: [],
    document_requirements: { passport_required: true, national_id_allowed: false, passport_fields: [], national_id_fields: [] },
    existing_values: {
      passengers: [{ first_name: "Ayesha", last_name: "Khan", type: "ADT", gender: "female" }],
      contact: {},
    },
    checkout_summary: {
      total_formatted: "PKR 88,114",
      currency: "PKR",
      passenger_counts: { adults: 1, children: 0, infants: 0, total: 1, expected: [], lead_passenger_index: 0 },
    },
    seat_extras_capability: { seat_map_available: false, ancillaries_available: false, message: "", progress_step: "" },
    countries: [],
    phone_dial_codes: [],
    auth: { authenticated: false, can_create_account: false, agent_booking_mode: false, agent_contact_locked: false },
  } as unknown as StandardPassengersContext;
  const rows = buildPassengersFromContext(context);
  assert.equal(rows.length, 1);
  assert.equal(rows[0].first_name, "Ayesha");
});
