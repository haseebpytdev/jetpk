import assert from "node:assert/strict";
import test from "node:test";
import {
  buildPassengerFormData,
  buildPassengersFromContext,
  normalizeHydratedPassenger,
  normalizePassengerTitle,
} from "../../features/standard-booking/utils/passenger-form";
import type { StandardPassengersContext } from "../../features/standard-booking/types";

function baseContext(
  passengers: Array<Record<string, unknown>>,
): StandardPassengersContext {
  return {
    ok: true,
    booking_session: {
      id: "sess",
      status: "passenger_details",
      server_time: new Date().toISOString(),
      progress: [],
    },
    selection: {
      search_id: "s1",
      offer_id: "o1",
      from: "LHE",
      to: "DXB",
      depart: "2026-09-01",
      trip_type: "one_way",
      cabin: "economy",
    },
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
      expected: [{ index: 0, type: "adult", label: "Adult" }],
      lead_passenger_index: 0,
    },
    passenger_requirements: [],
    contact_requirements: [],
    document_requirements: {
      passport_required: true,
      national_id_allowed: false,
      passport_fields: [],
      national_id_fields: [],
    },
    existing_values: {
      passengers: passengers as never,
      contact: {},
    },
    checkout_summary: { total_formatted: "PKR 88,114", currency: "PKR" },
    notices: [],
    next_actions: {},
  } as StandardPassengersContext;
}

test("null title hydrates to Mr for adult male and submits Mr", () => {
  const passengers = buildPassengersFromContext(
    baseContext([{ title: null, gender: null, first_name: "Ali", last_name: "Khan" }]),
  );
  assert.equal(passengers[0].title, "Mr");
  assert.equal(passengers[0].gender, "male");

  const formData = buildPassengerFormData(baseContext([]), passengers, {
    contact_name: "Ali",
    email: "a@example.com",
    phone: "+923001234567",
    phone_country_code: "+92",
    phone_number: "3001234567",
    country: "Pakistan",
    create_account: false,
    password: "",
    password_confirmation: "",
  });

  assert.equal(formData.get("passengers[0][title]"), "Mr");
  assert.equal(formData.get("passengers[0][gender]"), "male");
  assert.notEqual(formData.get("passengers[0][title]"), "null");
  assert.notEqual(formData.get("passengers[0][gender]"), "null");
});

test("female adult null title becomes Ms, never Mrs/Miss", () => {
  const p = normalizeHydratedPassenger("adult", { title: "null", gender: "female" });
  assert.equal(p.title, "Ms");
  assert.equal(normalizePassengerTitle(undefined, "adult", "female"), "Ms");
});

test("FormData omits nullish optional fields", () => {
  const passengers = [
    normalizeHydratedPassenger("adult", {
      title: "Mr",
      gender: "male",
      first_name: "Ali",
      last_name: "Khan",
      nationality: null,
      passport_number: "undefined",
    }),
  ];
  const formData = buildPassengerFormData(baseContext([]), passengers, {
    contact_name: "",
    email: "a@example.com",
    phone: "+923001234567",
    phone_country_code: "+92",
    phone_number: "3001234567",
    country: "Pakistan",
    create_account: false,
    password: "",
    password_confirmation: "",
  });
  assert.equal(formData.get("passengers[0][nationality]"), null);
  assert.equal(formData.get("passengers[0][passport_number]"), null);
  assert.equal(formData.get("passengers[0][title]"), "Mr");
});

test("manual Mr selection remains Mr after normalize", () => {
  const p = normalizeHydratedPassenger("adult", { title: "Mr", gender: "male" });
  assert.equal(p.title, "Mr");
});
