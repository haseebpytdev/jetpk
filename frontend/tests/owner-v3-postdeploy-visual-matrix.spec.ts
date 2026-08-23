/**
 * Owner V3 remediation — travelers/review visual shots 05–08.
 * Reuses Wave-9 genuine mock review contract (no live PNR/payment).
 */
import { test, expect } from "@playwright/test";
import fs from "node:fs";
import path from "node:path";

const outDir = path.resolve(process.cwd(), "..", "tmp", "owner-v3-postdeploy-remediation");

function reviewContext() {
  const total = 88114;
  return {
    ok: true,
    booking_session: {
      id: "v3-review-session",
      status: "review",
      expires_at: new Date(Date.now() + 15 * 60_000).toISOString(),
      server_time: new Date().toISOString(),
      progress: [
        { key: "flight_selected", label: "Flight Selected", state: "completed" },
        { key: "passenger_details", label: "Passenger Details", state: "completed" },
        { key: "review", label: "Review", state: "current" },
        { key: "payment", label: "Payment", state: "upcoming" },
      ],
    },
    booking_reference: null,
    itinerary: {
      trip_type: "one_way",
      route_label: "LHE → DXB",
      origin: "LHE",
      destination: "DXB",
      depart_date: "2026-09-01",
      airline_name: "Emirates",
      airline_code: "EK",
      airline_logo_url: null,
      flight_number: "EK625",
      cabin: "economy",
      fare_family: "ECONOMY FLEX",
      stops: 0,
      duration: "3h 25m",
      baggage: "30 kg",
      cabin_baggage: "7 kg",
      checked_baggage: "30 kg",
      meal: "Meal included",
      refund_rule: "Refundable with fee",
      change_rule: "Changes with fee",
      segments: [
        {
          origin_airport_code: "LHE",
          destination_airport_code: "DXB",
          origin_city: "Lahore",
          destination_city: "Dubai",
          departure_time_display: "09:15",
          arrival_time_display: "11:40",
          departure_date_display: "01 Sep 2026",
          arrival_date_display: "01 Sep 2026",
          airline_name: "Emirates",
          airline_code: "EK",
          flight_number: "EK625",
          duration_display: "3h 25m",
          cabin: "Economy",
        },
      ],
      return_segments: [],
      total_formatted: "PKR 88,114",
      currency: "PKR",
      selected_fare_option_key: "fare-flex",
      selected_fare: {
        fare_family: "ECONOMY FLEX",
        checked_baggage: "30 kg",
        cabin_baggage: "7 kg",
        customer_total: total,
        currency: "PKR",
      },
    },
    passengers: [
      {
        passenger_type: "adult",
        title: "Mr",
        first_name: "Ali",
        last_name: "Khan",
        gender: "male",
        date_of_birth: "1990-01-15",
        nationality: "PK",
        document_type: "passport",
        passport_number_masked: "••••8776",
        passport_issuing_country: "PK",
        passport_expiry_date: "2032-01-15",
      },
    ],
    contact: {
      name: "Ali Khan",
      email: "ali.visual@example.com",
      phone: "+923001234567",
      phone_country_code: "+92",
      phone_number: "3001234567",
      country: "Pakistan",
    },
    checkout_summary: {
      total_formatted: "PKR 88,114",
      currency: "PKR",
      passenger_counts: { adults: 1, children: 0, infants: 0, total: 1 },
      lines: [{ label: "Flight", amount_formatted: "PKR 88,114" }],
    },
    payment_methods: [
      {
        code: "manual",
        canonical: "pay_later",
        label: "Manual Payment",
        description: "Submit your booking and follow the provided payment instructions.",
        available: true,
        fee: null,
        currency: "PKR",
      },
      {
        code: "card",
        canonical: "online_card",
        label: "Pay by Card",
        description: "Debit / credit card through secure online payment.",
        available: true,
        fee: null,
        currency: "PKR",
      },
    ],
    selected_payment_method: "manual",
    consent: {
      terms_version: "1",
      privacy_version: "1",
      terms_url: "/terms",
      privacy_url: "/privacy",
      required: true,
      prechecked: false,
    },
    change_flight: { safe: true, results_url: "/flights", abandon_url: "/" },
  };
}

function passengersContext() {
  return {
    ok: true,
    booking_session: {
      id: "v3-pax-session",
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
      passengers: [{ title: null, gender: null, first_name: "", last_name: "" }],
      contact: {},
    },
    checkout_summary: {
      total_formatted: "PKR 88,114",
      currency: "PKR",
      passenger_counts: { adults: 1, children: 0, infants: 0, total: 1 },
    },
    seat_extras_capability: {
      seat_map_available: false,
      ancillaries_available: false,
      message: "",
      progress_step: "",
    },
    countries: [{ code: "PK", name: "Pakistan" }],
    phone_dial_codes: [{ code: "+92", label: "Pakistan (+92)" }],
    auth: {
      authenticated: false,
      can_create_account: false,
      agent_booking_mode: false,
      agent_contact_locked: false,
    },
  };
}

function checkoutState() {
  return {
    ok: true,
    step: "review",
    booking_session_id: "v3-review-session",
  };
}

async function shot(page: import("@playwright/test").Page, name: string) {
  fs.mkdirSync(outDir, { recursive: true });
  const file = path.join(outDir, name);
  await page.screenshot({ path: file, fullPage: true });
  expect(fs.statSync(file).size).toBeGreaterThan(8_000);
}

test("capture travelers and review remediation shots", async ({ page }) => {
  await page.route("**/booking/passengers**", async (route) => {
    const req = route.request();
    const url = req.url();
    const wantsJson = url.includes("format=json") || (req.headers()["accept"] || "").includes("application/json");
    if (!wantsJson || req.method() !== "GET") {
      await route.continue();
      return;
    }
    await route.fulfill({
      status: 200,
      contentType: "application/json",
      body: JSON.stringify(passengersContext()),
    });
  });

  await page.route("**/booking/review**", async (route) => {
    const req = route.request();
    const url = req.url();
    if (req.method() !== "GET") {
      await route.fulfill({ status: 200, contentType: "application/json", body: JSON.stringify({ ok: true }) });
      return;
    }
    const wantsJson = url.includes("format=json") || (req.headers()["accept"] || "").includes("application/json");
    if (!wantsJson) {
      await route.continue();
      return;
    }
    await route.fulfill({
      status: 200,
      contentType: "application/json",
      body: JSON.stringify(reviewContext()),
    });
  });

  await page.route("**/booking/checkout-state**", async (route) => {
    const req = route.request();
    const url = req.url();
    const wantsJson = url.includes("format=json") || (req.headers()["accept"] || "").includes("application/json");
    if (!wantsJson) {
      await route.continue();
      return;
    }
    await route.fulfill({
      status: 200,
      contentType: "application/json",
      body: JSON.stringify(checkoutState()),
    });
  });

  await page.setViewportSize({ width: 1280, height: 900 });
  await page.goto("/booking/passengers", { waitUntil: "networkidle" });
  await expect(page.getByTestId("passenger-card-0")).toBeVisible({ timeout: 60_000 });
  await shot(page, "05-travelers-mr-default.png");
  await shot(page, "06-travelers-exact-pkr.png");

  await page.goto("/booking/review", { waitUntil: "networkidle" });
  await expect(page.getByTestId("booking-review-page")).toBeVisible({ timeout: 60_000 });
  const payment = page.getByTestId("payment-method-selector").or(page.getByTestId("review-payment-method-sidebar"));
  await payment.first().scrollIntoViewIfNeeded();
  await shot(page, "07-review-right-payment-column.png");

  await page.getByTestId("review-traveler-card").first().scrollIntoViewIfNeeded();
  await shot(page, "08-review-normalized-passenger.png");
});
