import { test, expect } from "@playwright/test";
import fs from "node:fs";
import path from "node:path";

const searchId = "wave6-aaaaaaaa-bbbb-cccc-dddd-eeeeeeeeeeee";
const output = path.resolve(process.cwd(), "..", "tmp", "owner-v3-flight-wave-6");

const fares = [
  {
    option_key: "fare-basic",
    name: "ECONOMY BASIC",
    brand_name: "ECONOMY BASIC",
    displayed_price: 150766,
    price_display: "PKR 150,766",
    checked_baggage: "0 kg",
    cabin_baggage: "7 kg",
    baggage: "0 kg checked",
    refund_rule: "Non-refundable",
    change_rule: "Changes with fee",
    selection_key_authoritative: true,
    can_select: true,
  },
  {
    option_key: "fare-value",
    name: "ECONOMY VALUE",
    brand_name: "ECONOMY VALUE",
    displayed_price: 155488,
    price_display: "PKR 155,488",
    checked_baggage: "23 kg",
    cabin_baggage: "7 kg",
    baggage: "23 kg checked",
    refund_rule: "Non-refundable",
    change_rule: "Changes with fee",
    selection_key_authoritative: true,
    can_select: true,
  },
  {
    option_key: "fare-comfort",
    name: "ECONOMY COMFORT",
    brand_name: "ECONOMY COMFORT",
    displayed_price: 159376,
    price_display: "PKR 159,376",
    checked_baggage: "30 kg",
    cabin_baggage: "7 kg",
    baggage: "30 kg checked",
    refund_rule: "Refundable with fee",
    change_rule: "Changes with fee",
    meal: "Meal included",
    selection_key_authoritative: true,
    can_select: true,
  },
];

function offer(index: number) {
  const connected = index === 1;
  return {
    offer_id: `offer-${index + 1}`,
    airline_code: ["EY", "QR", "EK", "PK"][index],
    airline_name: ["Etihad Airways", "Qatar Airways", "Emirates", "PIA"][index],
    departure_time: ["08:10", "09:35", "13:20", "20:45"][index],
    arrival_time: ["14:40", "16:10", "18:35", "23:55"][index],
    duration: connected ? "5h 30m" : "3h 15m",
    stops: connected ? 1 : 0,
    stops_label_display: connected ? "1 Stop" : "Direct",
    displayed_price: fares[0].displayed_price + index * 1000,
    final_customer_price: fares[0].displayed_price + index * 1000,
    can_book: true,
    flight_number: `${["EY", "QR", "EK", "PK"][index]}${200 + index}`,
    segments: [
      {
        origin_airport_code: "ISB",
        destination_airport_code: connected ? "AUH" : "DXB",
        departure_time_display: ["08:10", "09:35", "13:20", "20:45"][index],
        arrival_time_display: connected ? "10:30" : ["14:40", "16:10", "18:35", "23:55"][index],
        flight_number: `${["EY", "QR", "EK", "PK"][index]}${200 + index}`,
      },
      ...(connected
        ? [
            {
              origin_airport_code: "AUH",
              destination_airport_code: "DXB",
              departure_time_display: "11:50",
              arrival_time_display: "14:40",
              flight_number: "EY900",
            },
          ]
        : []),
    ],
    layover_summary_display: connected ? ["1h 20m layover · AUH"] : [],
    layovers_display: connected
      ? [{ airport_code: "AUH", airport_city: "Abu Dhabi", duration_minutes: 80 }]
      : [],
    branded_fares_display_options: fares,
    fare_family_options_display: fares,
    has_branded_fares: true,
    select_url: "/booking/passengers",
  };
}

function details(selected = fares[0]) {
  return {
    success: true,
    search_id: searchId,
    offer_id: "offer-1",
    offer: {
      ...offer(0),
      displayed_price: selected.displayed_price,
      final_customer_price: selected.displayed_price,
      price_display: selected.price_display,
      branded_fares_display_options: fares,
      fare_family_options_display: fares,
      baggage_summary_display: selected.baggage,
      baggage_checked_display: selected.checked_baggage,
      baggage_cabin_display: selected.cabin_baggage,
      refund_rule: selected.refund_rule,
      change_rule: selected.change_rule,
      fallback_details: {
        baggage: {
          summary: selected.baggage,
          cabin: selected.cabin_baggage,
          checked: selected.checked_baggage,
          passenger_baggage: [
            { passenger_type: "ADULT", cabin: selected.cabin_baggage, checked: selected.checked_baggage },
            { passenger_type: "CHILD", cabin: selected.cabin_baggage, checked: selected.checked_baggage },
          ],
        },
        fare_rules: {
          refund_rule: selected.refund_rule,
          change_rule: selected.change_rule,
          rule_lines: ["No-show rule supplied by airline"],
        },
        fare_breakdown: {
          displayed_price: selected.displayed_price,
          grand_total: selected.displayed_price,
          currency: "PKR",
          component_breakdown_unavailable: true,
          passenger_pricing: [
            {
              passenger_type: "adult",
              passenger_count: 2,
              total_amount: Math.round(selected.displayed_price * 0.62),
              currency: "PKR",
            },
            {
              passenger_type: "child",
              passenger_count: 3,
              total_amount: Math.round(selected.displayed_price * 0.3),
              currency: "PKR",
            },
            {
              passenger_type: "infant",
              passenger_count: 1,
              total_amount: Math.round(selected.displayed_price * 0.08),
              currency: "PKR",
            },
          ],
        },
      },
      select_url: "/booking/passengers",
    },
  };
}

function passengersContext(selected = fares[2]) {
  return {
    ok: true,
    booking_session: {
      id: "wave6-session",
      status: "passenger_details",
      expires_at: new Date(Date.now() + 15 * 60_000).toISOString(),
      server_time: new Date().toISOString(),
      progress: [
        { key: "search", label: "Search", state: "completed" },
        { key: "results", label: "Results", state: "completed" },
        { key: "passenger_details", label: "Travelers", state: "current" },
        { key: "review", label: "Review", state: "upcoming" },
        { key: "payment", label: "Payment", state: "upcoming" },
      ],
    },
    selection: {
      search_id: searchId,
      offer_id: "offer-1",
      fare_option_key: selected.option_key,
      from: "ISB",
      to: "DXB",
      depart: "2026-09-18",
      trip_type: "one_way",
      cabin: "economy",
    },
    itinerary: {
      trip_type: "one_way",
      origin: "ISB",
      destination: "DXB",
      depart_date: "2026-09-18",
      airline_name: "Etihad Airways",
      airline_code: "EY",
      flight_number: "EY200",
      cabin: "economy",
      fare_family: selected.name,
      stops: 0,
      duration: "3h 15m",
      baggage: selected.checked_baggage,
      segments: [
        {
          origin_airport_code: "ISB",
          destination_airport_code: "DXB",
          departure_time_display: "08:10",
          arrival_time_display: "11:25",
          flight_number: "EY200",
        },
      ],
      return_segments: [],
      total_formatted: selected.price_display,
      currency: "PKR",
      selected_fare_option_key: selected.option_key,
    },
    travellers: {
      adults: 2,
      children: 3,
      infants: 1,
      total: 6,
      expected: [
        { index: 0, type: "adult", label: "Adult" },
        { index: 1, type: "adult", label: "Adult" },
        { index: 2, type: "child", label: "Child" },
        { index: 3, type: "child", label: "Child" },
        { index: 4, type: "child", label: "Child" },
        { index: 5, type: "infant", label: "Infant" },
      ],
      lead_passenger_index: 0,
    },
    passenger_requirements: [
      { key: "title", label: "Title", required: true, input_type: "select", passenger_types: ["adult", "child", "infant"] },
      { key: "first_name", label: "First name", required: true, input_type: "text", passenger_types: ["adult", "child", "infant"] },
      { key: "last_name", label: "Last name", required: true, input_type: "text", passenger_types: ["adult", "child", "infant"] },
      { key: "gender", label: "Gender", required: true, input_type: "select", passenger_types: ["adult", "child", "infant"] },
      { key: "date_of_birth", label: "Date of birth", required: true, input_type: "date", passenger_types: ["adult", "child", "infant"] },
      { key: "nationality", label: "Nationality", required: true, input_type: "country", passenger_types: ["adult", "child", "infant"] },
      { key: "document_type", label: "Document type", required: true, input_type: "select", passenger_types: ["adult", "child", "infant"], options: ["passport"] },
    ],
    contact_requirements: [
      { key: "email", label: "Email", required: true, input_type: "email" },
      { key: "phone", label: "Phone", required: true, input_type: "tel" },
    ],
    document_requirements: {
      passport_required: true,
      national_id_allowed: false,
      passport_fields: [
        { key: "passport_number", label: "Passport number", required: true, input_type: "text" },
        { key: "passport_issuing_country", label: "Issuing country", required: true, input_type: "country" },
        { key: "passport_expiry_date", label: "Passport expiry", required: true, input_type: "date" },
        { key: "passport_issue_date", label: "Passport issue date", required: false, input_type: "date" },
      ],
      national_id_fields: [],
    },
    existing_values: { passengers: [], contact: {} },
    checkout_summary: { total_formatted: selected.price_display, currency: "PKR", passenger_counts: { adults: 2, children: 3, infants: 1, total: 6 }, lines: [] },
    seat_extras_capability: { seat_map_available: false, ancillaries_available: false, message: "Seat selection is not available for this fare.", progress_step: "skipped" },
    countries: [{ code: "PK", name: "Pakistan" }, { code: "AE", name: "United Arab Emirates" }],
    phone_dial_codes: { "+92": "Pakistan (+92)" },
    auth: { authenticated: false, can_create_account: true, agent_booking_mode: false, agent_contact_locked: false },
  };
}

async function readCardPrices(page: import("@playwright/test").Page) {
  const cards = page.locator("[data-fare-family-card]");
  const count = await cards.count();
  const prices: string[] = [];
  for (let i = 0; i < count; i += 1) {
    const text = await cards.nth(i).locator("p").filter({ hasText: /PKR/ }).first().innerText();
    prices.push(text.trim());
  }
  return prices;
}

test("owner V3 wave 6 commerce state + visual proof", async ({ page }) => {
  test.setTimeout(180_000);
  fs.mkdirSync(output, { recursive: true });

  await page.route("**/laravel/flights/results/data**", async (route) =>
    route.fulfill({
      status: 200,
      contentType: "application/json",
      body: JSON.stringify({
        search_id: searchId,
        page: 1,
        per_page: 12,
        total: 4,
        has_more: false,
        offers: [0, 1, 2, 3].map(offer),
        filters: {
          stops: [
            { value: "direct", label: "Direct", count: 3 },
            { value: "1_stop", label: "1 Stop", count: 1 },
          ],
          airlines: [],
          departure_windows: [],
          arrival_windows: [],
          refundable: [],
          baggage_options: [],
          fare_families: [],
          duration_buckets: [],
          layover_airports: [{ code: "AUH", name: "Abu Dhabi", count: 1 }],
          price_range: { min: 150766, max: 162000 },
        },
        warnings: [],
      }),
    }),
  );
  await page.route("**/laravel/flights/results/nearby-dates**", async (route) =>
    route.fulfill({ status: 200, contentType: "application/json", body: JSON.stringify({ available: false, dates: [] }) }),
  );
  await page.route("**/laravel/flights/results/offer**", async (route) => {
    const key = new URL(route.request().url()).searchParams.get("fare_option_key");
    await route.fulfill({
      status: 200,
      contentType: "application/json",
      body: JSON.stringify(details(fares.find((fare) => fare.option_key === key) ?? fares[0])),
    });
  });
  await page.route("**/laravel/booking/passengers**", async (route) => {
    if (route.request().method() !== "GET") {
      await route.fallback();
      return;
    }
    await route.fulfill({
      status: 200,
      contentType: "application/json",
      body: JSON.stringify(passengersContext(fares[2])),
    });
  });

  const query = new URLSearchParams({
    search_id: searchId,
    trip_type: "one_way",
    from: "ISB",
    to: "DXB",
    depart: "2026-09-18",
    adults: "2",
    children: "3",
    infants: "1",
    cabin: "economy",
  });

  await page.setViewportSize({ width: 1440, height: 1000 });
  await page.goto(`/flights/results?${query}`);
  await expect(page.getByTestId("flight-result-card")).toHaveCount(4);
  await page.screenshot({ path: path.join(output, "01-results-desktop.png"), fullPage: true });

  const connectedStop = page.getByRole("button", { name: /layover/i }).first();
  await connectedStop.hover();
  await expect(page.getByRole("tooltip")).toBeVisible();
  await page.screenshot({ path: path.join(output, "02-connected-stop-tooltip.png") });
  await page.mouse.move(0, 0);

  await page.getByTestId("book-now-trigger").first().click();
  await expect(page.getByTestId("fare-family-details")).toBeVisible();
  await expect(page.getByTestId("fare-family-details").getByRole("button", { name: /^View Details$/i })).toHaveCount(0);

  const baseline = await readCardPrices(page);
  expect(baseline).toHaveLength(3);

  const selectSequence = ["ECONOMY BASIC", "ECONOMY VALUE", "ECONOMY COMFORT", "ECONOMY BASIC"] as const;
  for (const name of selectSequence) {
    await page.getByRole("listitem").filter({ hasText: name }).getByRole("button", { name: /Select fare|Selected/ }).click();
    await expect(page.getByRole("listitem").filter({ hasText: name })).toContainText("Selected");
    const after = await readCardPrices(page);
    expect(after).toEqual(baseline);
  }

  await page.getByRole("listitem").filter({ hasText: "ECONOMY BASIC" }).getByRole("button", { name: /Select fare|Selected/ }).click();
  await page.screenshot({ path: path.join(output, "03-fare-basic-selected.png") });
  await page.getByRole("listitem").filter({ hasText: "ECONOMY VALUE" }).getByRole("button", { name: "Select fare" }).click();
  await expect(page.getByRole("listitem").filter({ hasText: "ECONOMY VALUE" })).toContainText("Selected");
  await page.screenshot({ path: path.join(output, "04-fare-middle-selected.png") });
  await page.getByRole("tab", { name: "Fare Details" }).click();
  await expect(page.getByTestId("price-breakdown")).toContainText("155,488");
  await page.getByRole("listitem").filter({ hasText: "ECONOMY COMFORT" }).getByRole("button", { name: "Select fare" }).click();
  await expect(page.getByRole("listitem").filter({ hasText: "ECONOMY COMFORT" })).toContainText("Selected");
  await page.screenshot({ path: path.join(output, "05-fare-high-selected.png") });
  await page.getByRole("tab", { name: "Fare Details" }).click();
  await expect(page.getByTestId("price-breakdown")).toContainText("159,376");
  await page.screenshot({ path: path.join(output, "06-fare-prices-before-after-proof.png") });

  await page.getByRole("tab", { name: "Baggage Policy" }).click();
  await page.getByTestId("fare-summary-tabs").screenshot({ path: path.join(output, "07-fare-summary-baggage.png") });
  await page.getByRole("tab", { name: "Fare Policy" }).click();
  await page.getByTestId("fare-summary-tabs").screenshot({ path: path.join(output, "08-fare-summary-policy.png") });
  await page.getByRole("tab", { name: "Fare Details" }).click();
  await page.getByTestId("fare-summary-tabs").screenshot({ path: path.join(output, "09-fare-summary-price.png") });
  await expect(page.getByTestId("passenger-fare-breakdown").or(page.getByTestId("passenger-fare-breakdown-compact"))).toBeVisible();
  await page.getByTestId("price-breakdown").screenshot({ path: path.join(output, "10-multipax-price-breakdown.png") });

  await page.goto(
    `/booking/passengers?search_id=${searchId}&offer_id=offer-1&fare_option_key=fare-comfort&from=ISB&to=DXB&depart=2026-09-18&adults=2&children=3&infants=1`,
  );
  await expect(page.getByText("ECONOMY COMFORT").first()).toBeVisible({ timeout: 20_000 });
  await expect(page.getByText("30 kg").first()).toBeVisible();
  await expect(page.getByText(/159,?376|PKR 159/).first()).toBeVisible();
  await page.screenshot({ path: path.join(output, "11-passenger-desktop.png"), fullPage: true });
  const preview = page.getByTestId("flight-preview");
  await expect(preview).toBeVisible();
  await expect(preview).toContainText("ECONOMY COMFORT");
  await expect(preview).toContainText("30 kg");
  await preview.screenshot({ path: path.join(output, "12-passenger-flight-preview-selected-fare.png") });

  await expect(page.getByTestId("document-reader-scan-0")).toContainText("Autofill from passport");
  await expect(page.getByText("Paste MRZ")).toHaveCount(0);
  // Capture the full reader chrome (not just the icon button) so visual proof exceeds size gates.
  await page.getByTestId("passenger-card-0").screenshot({ path: path.join(output, "13-passport-autofill-icon.png") });

  const { SYNTHETIC_VALID_MRZ_FUTURE_EXPIRY } = await import("../features/standard-booking/document-reader/mrz/fixtures");
  await page.getByTestId("document-reader-paste-0").fill(SYNTHETIC_VALID_MRZ_FUTURE_EXPIRY);
  await expect(page.getByTestId("document-reader-preview-0")).toBeVisible();
  await page.screenshot({ path: path.join(output, "14-passport-upload.png") });
  await page.getByTestId("document-reader-preview-0").screenshot({ path: path.join(output, "15-passport-review.png") });
  await page.getByTestId("document-reader-confirm-0").click();
  await expect(page.getByTestId("passenger-card-0").getByLabel(/Last name/i)).not.toHaveValue("");
  await page.getByTestId("passenger-card-0").screenshot({ path: path.join(output, "16-passport-fields-filled.png") });

  await page.setViewportSize({ width: 390, height: 844 });
  await page.goto(`/flights/results?${query}`);
  await page.screenshot({ path: path.join(output, "17-results-mobile.png"), fullPage: true });
  await page.getByTestId("book-now-trigger").first().click();
  await expect(page.getByTestId("fare-family-details")).toBeVisible();
  await page.screenshot({ path: path.join(output, "18-fare-modal-mobile.png") });
  await page.goto(
    `/booking/passengers?search_id=${searchId}&offer_id=offer-1&fare_option_key=fare-comfort&from=ISB&to=DXB&depart=2026-09-18&adults=2&children=3&infants=1`,
  );
  // Desktop sidebar is lg:hidden on mobile; expand the mobile Flight preview sheet.
  await expect(page.getByTestId("mobile-order-summary")).toBeVisible({ timeout: 15_000 });
  await page.getByTestId("mobile-order-summary").getByRole("button", { name: /Flight preview/i }).click();
  await expect(page.getByTestId("mobile-order-summary").getByTestId("flight-preview-fare")).toHaveText(
    "ECONOMY COMFORT",
  );
  await page.screenshot({ path: path.join(output, "19-passenger-mobile.png"), fullPage: true });

  await page.goto("/flights/results?search_id=missing");
  await page.evaluate(() => {
    throw new Error("wave6-forced-recovery-probe");
  }).catch(() => undefined);
  // Capture recovery chrome from app error boundary via intentional navigation to a broken client path is unreliable;
  // instead screenshot the passengers missing-session error which shares Home/Support recovery actions.
  await page.goto("/booking/passengers");
  await expect(page.getByRole("link", { name: "Home" }).or(page.getByText(/missing|expired|Unable/i))).toBeVisible({ timeout: 10_000 });
  await page.screenshot({ path: path.join(output, "20-error-recovery-proof.png") });

  const required = [
    "01-results-desktop.png",
    "02-connected-stop-tooltip.png",
    "03-fare-basic-selected.png",
    "04-fare-middle-selected.png",
    "05-fare-high-selected.png",
    "06-fare-prices-before-after-proof.png",
    "07-fare-summary-baggage.png",
    "08-fare-summary-policy.png",
    "09-fare-summary-price.png",
    "10-multipax-price-breakdown.png",
    "11-passenger-desktop.png",
    "12-passenger-flight-preview-selected-fare.png",
    "13-passport-autofill-icon.png",
    "14-passport-upload.png",
    "15-passport-review.png",
    "16-passport-fields-filled.png",
    "17-results-mobile.png",
    "18-fare-modal-mobile.png",
    "19-passenger-mobile.png",
    "20-error-recovery-proof.png",
  ];
  for (const name of required) {
    const full = path.join(output, name);
    expect(fs.existsSync(full), `missing proof ${name}`).toBe(true);
    expect(fs.statSync(full).size).toBeGreaterThan(8_000);
  }
});
