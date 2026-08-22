/**
 * Wave-9 visual matrix — Review / Payment genuine mock screenshots.
 * Writes to ../tmp/owner-v3-flight-wave-9/
 */
import { test, expect } from "@playwright/test";
import fs from "node:fs";
import path from "node:path";

const outDir = path.resolve(process.cwd(), "..", "tmp", "owner-v3-flight-wave-9");

function reviewContext(opts: {
  connected?: boolean;
  multipax?: boolean;
  fareChange?: boolean;
  cardAvailable?: boolean;
} = {}) {
  const connected = opts.connected === true;
  const multipax = opts.multipax === true;
  const total = multipax ? 158178 : 79089;
  const base = multipax ? 123670 : 61835;
  const taxes = multipax ? 34508 : 17254;

  const outbound = connected
    ? [
        {
          origin_airport_code: "ISB",
          destination_airport_code: "AUH",
          origin_city: "Islamabad",
          destination_city: "Abu Dhabi",
          departure_time_display: "08:25",
          arrival_time_display: "10:40",
          departure_date_display: "18 Sep 2026",
          arrival_date_display: "18 Sep 2026",
          airline_name: "Etihad Airways",
          airline_code: "EY",
          flight_number: "EY231",
          duration_display: "3h 15m",
          layover_display: "2h 15m",
          cabin: "Economy",
          aircraft: "A320",
        },
        {
          origin_airport_code: "AUH",
          destination_airport_code: "DXB",
          origin_city: "Abu Dhabi",
          destination_city: "Dubai",
          departure_time_display: "12:55",
          arrival_time_display: "14:10",
          departure_date_display: "18 Sep 2026",
          arrival_date_display: "18 Sep 2026",
          airline_name: "Etihad Airways",
          airline_code: "EY",
          flight_number: "EY612",
          duration_display: "1h 15m",
          cabin: "Economy",
        },
      ]
    : [
        {
          origin_airport_code: "ISB",
          destination_airport_code: "DXB",
          origin_city: "Islamabad",
          destination_city: "Dubai",
          departure_time_display: "08:25",
          arrival_time_display: "10:25",
          departure_date_display: "18 Sep 2026",
          arrival_date_display: "18 Sep 2026",
          airline_name: "Etihad Airways",
          airline_code: "EY",
          flight_number: "EY231",
          duration_display: "4h 00m",
          cabin: "Economy",
          aircraft: "B787",
          terminal: "T1",
        },
      ];

  const passengers = multipax
    ? [
        {
          passenger_type: "adult",
          title: "Mr",
          first_name: "Haseeb",
          last_name: "Asif",
          gender: "male",
          date_of_birth: "1990-01-15",
          nationality: "PK",
          document_type: "passport",
          passport_number_masked: "••••8776",
          passport_issuing_country: "PK",
          passport_expiry_date: "2032-01-15",
        },
        {
          passenger_type: "child",
          title: "Ms",
          first_name: "Ayesha",
          last_name: "Asif",
          gender: "female",
          date_of_birth: "2016-05-01",
          nationality: "PK",
          document_type: "passport",
          passport_number_masked: "••••1122",
          passport_issuing_country: "PK",
          passport_expiry_date: "2030-05-01",
        },
      ]
    : [
        {
          passenger_type: "adult",
          title: "Mr",
          first_name: "Haseeb",
          last_name: "Asif",
          gender: "male",
          date_of_birth: "1990-01-15",
          nationality: "PK",
          document_type: "passport",
          passport_number_masked: "••••8776",
          passport_issuing_country: "PK",
          passport_expiry_date: "2032-01-15",
        },
      ];

  const methods = [
    {
      code: "manual",
      canonical: "pay_later",
      label: "Manual Payment",
      description: "Submit your booking and follow the provided payment instructions.",
      available: true,
      fee: null,
      currency: "PKR",
    },
  ];
  if (opts.cardAvailable !== false) {
    methods.push({
      code: "card",
      canonical: "online_card",
      label: "Pay by Card",
      description: "Debit / credit card through secure online payment.",
      available: true,
      fee: null,
      currency: "PKR",
    });
  }

  return {
    ok: true,
    booking_session: {
      id: "wave9-review-session",
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
      route_label: connected ? "ISB → AUH → DXB" : "ISB → DXB",
      origin: "ISB",
      destination: "DXB",
      depart_date: "2026-09-18",
      airline_name: "Etihad Airways",
      airline_code: "EY",
      airline_logo_url: null,
      flight_number: "EY231",
      cabin: "economy",
      fare_family: "ECONOMY COMFORT",
      stops: connected ? 1 : 0,
      duration: connected ? "5h 45m" : "4h 00m",
      baggage: "30 kg",
      cabin_baggage: "7 kg",
      checked_baggage: "30 kg",
      meal: "Meal included",
      refund_rule: "Refundable with fee",
      change_rule: "Changes with fee",
      segments: outbound,
      return_segments: [],
      total_formatted: `PKR ${total.toLocaleString("en-PK")}`,
      currency: "PKR",
      selected_fare_option_key: "fare-comfort",
      selected_fare: {
        fare_family: "ECONOMY COMFORT",
        checked_baggage: "30 kg",
        cabin_baggage: "7 kg",
        customer_total: total,
        currency: "PKR",
      },
    },
    passengers,
    contact: {
      name: "Haseeb Asif",
      email: "haseeb.wave9@example.com",
      phone: "+92 300 1112233",
      country: "PK",
    },
    documents: passengers.map((p) => ({
      passenger_label: `${p.first_name} ${p.last_name}`,
      document_type: p.document_type,
      passport_number_masked: p.passport_number_masked,
      passport_issuing_country: p.passport_issuing_country,
      passport_expiry_date: p.passport_expiry_date,
    })),
    pricing: {
      currency: "PKR",
      base_fare: base,
      taxes,
      service_charges: 0,
      total,
      formatted_total: `PKR ${total.toLocaleString("en-PK")}`,
      formatted_base_fare: `PKR ${base.toLocaleString("en-PK")}`,
      formatted_taxes: `PKR ${taxes.toLocaleString("en-PK")}`,
      selected_fare_total: total,
    },
    payment_methods: methods,
    terms: { required: false, terms_url: "/terms", privacy_url: "/privacy" },
    fare_change: opts.fareChange
      ? {
          fare_changed: true,
          requires_acceptance: true,
          old_total: 75000,
          new_total: total,
          old_total_formatted: "PKR 75,000",
          new_total_formatted: `PKR ${total.toLocaleString("en-PK")}`,
          accept_url: "/booking/1/accept-updated-fare",
          decline_url: "/booking/1/decline-updated-fare",
        }
      : null,
    submit_blocked: opts.fareChange === true,
    submit_blocked_reason: opts.fareChange ? "Please accept the updated fare to continue." : null,
    notices: [],
    next_actions: {
      edit_passengers_url: "/booking/passengers",
      accept_fare_url: opts.fareChange ? "/booking/1/accept-updated-fare" : null,
      decline_fare_url: opts.fareChange ? "/booking/1/decline-updated-fare" : null,
    },
  };
}

function checkoutState() {
  const review = reviewContext();
  return {
    ok: true,
    booking_session: {
      ...review.booking_session,
      status: "payment",
      progress: review.booking_session.progress.map((s) =>
        s.key === "payment" ? { ...s, state: "current" } : s.key === "review" ? { ...s, state: "completed" } : s,
      ),
    },
    booking_reference: "JP-WAVE9-001",
    itinerary: review.itinerary,
    passengers: review.passengers,
    pricing: review.pricing,
    payment_status: { code: "unpaid", label: "Unpaid" },
    card_payment: {
      formatted_amount: review.pricing.formatted_total,
      show_pay_button: true,
      start_endpoint: "/booking/payment/abhipay/start",
      ticketing_note: "Ticketing will happen after payment verification.",
      payment_status_label: "Unpaid",
      blocked_message: null,
    },
  };
}

async function shot(page: import("@playwright/test").Page, name: string) {
  fs.mkdirSync(outDir, { recursive: true });
  const file = path.join(outDir, name);
  await page.screenshot({ path: file, fullPage: true });
  const stat = fs.statSync(file);
  expect(stat.size, name).toBeGreaterThan(8_000);
}

test.describe("Wave-9 Review visual matrix", () => {
  test.setTimeout(180_000);

  let reviewMode: "direct" | "connected" | "multipax" | "farechange" = "direct";

  test.beforeEach(async ({ page }) => {
    reviewMode = "direct";
    await page.route("**/booking/review**", async (route) => {
      const request = route.request();
      const url = request.url();
      const wantsJson = url.includes("format=json") || (request.headers()["accept"] || "").includes("application/json");
      if (!wantsJson) {
        await route.continue();
        return;
      }
      if (request.method() !== "GET") {
        await route.fulfill({ status: 200, contentType: "application/json", body: JSON.stringify({ ok: true }) });
        return;
      }
      await route.fulfill({
        status: 200,
        contentType: "application/json",
        body: JSON.stringify(
          reviewContext({
            connected: reviewMode === "connected",
            multipax: reviewMode === "multipax",
            fareChange: reviewMode === "farechange",
            cardAvailable: true,
          }),
        ),
      });
    });
    await page.route("**/booking/checkout-state**", async (route) => {
      const request = route.request();
      const url = request.url();
      const wantsJson = url.includes("format=json") || (request.headers()["accept"] || "").includes("application/json");
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
  });

  test("capture review and payment screenshots", async ({ page }) => {
    await page.setViewportSize({ width: 1280, height: 900 });
    reviewMode = "direct";
    await page.goto("/booking/review", { waitUntil: "networkidle" });
    await expect(page.getByTestId("booking-review-page")).toBeVisible();
    await shot(page, "01-review-desktop.png");
    await page.getByTestId("review-selected-fare").scrollIntoViewIfNeeded();
    await shot(page, "04-review-selected-fare.png");
    const price = page.getByTestId("review-price-summary").or(page.getByTestId("order-summary-total"));
    await price.first().scrollIntoViewIfNeeded();
    await shot(page, "05-review-price-summary.png");
    await page.getByTestId("review-traveler-card").first().scrollIntoViewIfNeeded();
    await shot(page, "06-review-traveler-card.png");
    await page.getByText("••••8776").first().scrollIntoViewIfNeeded();
    await shot(page, "07-review-document-masked.png");
    await page.getByTestId("edit-traveler-details").scrollIntoViewIfNeeded();
    await shot(page, "08-review-edit-traveler.png");
    await page.getByTestId("review-contact").scrollIntoViewIfNeeded();
    await shot(page, "09-review-contact.png");
    await page.getByTestId("payment-method-selector").scrollIntoViewIfNeeded();
    await shot(page, "10-review-payment-options.png");
    await page.getByTestId("payment-method-manual").click();
    await shot(page, "11-review-manual-selected.png");
    await expect(page.getByTestId("review-continue-button")).toContainText("Confirm booking");
    await page.getByTestId("payment-method-card").click();
    await shot(page, "12-review-card-selected.png");
    await expect(page.getByTestId("review-continue-button")).toContainText("Continue to payment");

    reviewMode = "connected";
    await page.goto("/booking/review", { waitUntil: "networkidle" });
    await shot(page, "03-review-itinerary-connected.png");

    reviewMode = "direct";
    await page.goto("/booking/review", { waitUntil: "networkidle" });
    await page.getByTestId("review-itinerary").scrollIntoViewIfNeeded();
    await shot(page, "02-review-itinerary-direct.png");

    await page.goto("/booking/payment/card", { waitUntil: "networkidle" });
    await expect(page.getByTestId("card-payment-page")).toBeVisible();
    await shot(page, "13-payment-card-page.png");

    await page.setViewportSize({ width: 390, height: 844 });
    reviewMode = "direct";
    await page.goto("/booking/review", { waitUntil: "networkidle" });
    await shot(page, "14-review-mobile.png");

    await page.setViewportSize({ width: 1280, height: 900 });
    reviewMode = "multipax";
    await page.goto("/booking/review", { waitUntil: "networkidle" });
    await shot(page, "15-review-multipax.png");

    reviewMode = "farechange";
    await page.goto("/booking/review", { waitUntil: "networkidle" });
    await expect(page.getByTestId("fare-change-panel")).toBeVisible({ timeout: 15_000 });
    await shot(page, "16-fare-change-review.png");

    const index = [
      "# Wave-9 Visual Matrix",
      "",
      "Genuine mock Review/Payment screenshots (no live PNR/payment).",
      "",
      ...fs.readdirSync(outDir).filter((f) => f.endsWith(".png")).sort().map((f) => "- " + f),
      "",
    ].join("\n");
    fs.writeFileSync(path.join(outDir, "VISUAL-MATRIX-INDEX.md"), index);
  });
});
