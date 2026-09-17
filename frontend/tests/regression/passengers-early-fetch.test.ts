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
import { buildPassengerFormData, buildPassengersFromContext } from "../../features/standard-booking/utils/passenger-form";
import type { StandardPassengersContext } from "../../features/standard-booking/types";

const HANDOFF =
  "search_id=s1&offer_id=o1&fare_option_key=fk1&outbound_key=ob1&bound=return&format=html";

function runInline(opts: {
  pathname?: string;
  search?: string;
  fetchImpl?: (...args: unknown[]) => Promise<unknown>;
  sessionStorage?: Record<string, string>;
}) {
  const calls: Array<{ url: string; init: RequestInit }> = [];
  const window: Record<string, unknown> = {};
  const location = {
    pathname: opts.pathname ?? "/booking/passengers",
    search: opts.search ?? `?${HANDOFF}`,
  };
  const store = { ...(opts.sessionStorage ?? {}) };
  const sessionStorage = {
    getItem: (k: string) => (k in store ? store[k] : null),
    setItem: (k: string, v: string) => {
      store[k] = v;
    },
    removeItem: (k: string) => {
      delete store[k];
    },
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
    sessionStorage,
    performance: { now: () => 12 },
    URLSearchParams,
    Date,
    Promise,
  };
  vm.runInNewContext(PASSENGERS_EARLY_FETCH_INLINE, context);
  return { window, calls, fetch, store };
}

test("early fetch uses the same Laravel GET, query, credentials, and headers as React", () => {
  const reactKey = buildPassengersFetchQuery(new URLSearchParams(HANDOFF));
  const { calls, window } = runInline({});
  assert.equal(calls.length, 1);
  assert.equal(calls[0].url, `${PASSENGERS_LARAVEL_PATH}?${reactKey}`);
  assert.equal(calls[0].init.credentials, PASSENGERS_FETCH_CREDENTIALS);
  assert.equal((calls[0].init.headers as { Accept: string }).Accept, PASSENGERS_JSON_HEADERS.Accept);
  const prime = window.__jpPassengersPrime as { key: string };
  assert.equal(prime.key, reactKey);
});

test("sessionStorage prime hydrates without network", async () => {
  const key = buildPassengersFetchQuery(new URLSearchParams(HANDOFF));
  const { calls, window, store } = runInline({
    sessionStorage: {
      "jp-passengers-context-prime": JSON.stringify({
        key,
        at: Date.now(),
        data: { ok: true, selection: { search_id: "s1", offer_id: "o1" } },
      }),
    },
  });
  assert.equal(calls.length, 0);
  const prime = window.__jpPassengersPrime as { key: string; promise: Promise<{ ok: boolean; source?: string }> };
  assert.equal(prime.key, key);
  const result = await prime.promise;
  assert.equal(result.ok, true);
  assert.equal(store["jp-passengers-context-prime"], undefined);
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
  assert.equal(hasPassengersHandoffQuery({ search_id: "s1", offer_id: "o1" }), true);
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

test("buildPassengersFromContext ignores null title and keeps Mr default", () => {
  const context = {
    travellers: {
      adults: 1,
      children: 0,
      infants: 0,
      total: 1,
      lead_passenger_index: 0,
      expected: [{ type: "adult", label: "Adult" }],
    },
    existing_values: {
      passengers: [
        {
          title: null,
          first_name: "AHMED",
          last_name: "KHAN",
          gender: null,
        },
      ],
      contact: {},
    },
    selection: {
      search_id: "s1",
      offer_id: "o1",
      from: "LHE",
      to: "DXB",
      depart: "2026-10-15",
      trip_type: "one_way",
      cabin: "economy",
    },
    consent: { terms_version: "jetpk-checkout-terms-2026-08-22" },
  } as unknown as StandardPassengersContext;

  const rows = buildPassengersFromContext(context);
  assert.equal(rows[0].title, "Mr");
  assert.equal(rows[0].gender, "male");
  assert.equal(rows[0].first_name, "AHMED");
});

test("buildPassengerFormData never writes literal null strings", () => {
  const context = {
    travellers: { adults: 1, children: 0, infants: 0, total: 1, lead_passenger_index: 0, expected: [] },
    selection: {
      search_id: "s1",
      offer_id: "o1",
      from: "LHE",
      to: "DXB",
      depart: "2026-10-15",
      trip_type: "one_way",
      cabin: "economy",
    },
    consent: { terms_version: "jetpk-checkout-terms-2026-08-22" },
  } as unknown as StandardPassengersContext;

  const formData = buildPassengerFormData(
    context,
    [
      {
        passenger_type: "adult",
        title: null as unknown as string,
        first_name: "AHMED",
        last_name: "KHAN",
        gender: "male",
        date_of_birth: "1990-05-15",
        nationality: "PK",
        document_type: "passport",
        passport_number: "AB1234567",
        passport_issuing_country: "PK",
        passport_expiry_date: "2030-12-31",
        passport_issue_date: "2020-01-10",
        national_id_number: "",
      },
    ],
    {
      contact_name: "",
      email: "qa@example.test",
      phone: "03001234567",
      phone_country_code: "+92",
      phone_number: "3001234567",
      country: "Pakistan",
      create_account: false,
      password: "",
      password_confirmation: "",
    },
    { termsAccepted: true },
  );

  assert.equal(formData.get("passengers[0][title]"), null);
  assert.equal(formData.get("passengers[0][first_name]"), "AHMED");
});
